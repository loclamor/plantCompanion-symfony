<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../../api/http';
import { useSeasonStore } from '../../stores/season';

const route = useRoute();
const router = useRouter();
const seasons = useSeasonStore();

const id = computed(() => route.params.id ?? null);
const isEdit = computed(() => id.value != null);

const form = reactive({
    graineType: null,
    graine: null,
    graineLot: null,
    methode: 'godet',
    dateSemis: new Date().toISOString().slice(0, 10),
    dateLevee: '',
    datePlantation: '',
    datePlantationTheorique: '',
    dateRecolteTheorique: '',
    echec: false,
    notes: '',
});

const graineTypes = ref([]);
const graines = ref([]);
const lots = ref([]);
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

const rempotages = ref([]);
const newRempotage = reactive({ date: new Date().toISOString().slice(0, 10), notes: '' });
const rempotageError = ref(null);

async function loadGraineTypes() {
    const { data } = await http.get('/graine-types');
    graineTypes.value = data.items;
}

async function loadGraines() {
    if (!form.graineType || form.graineType === '') {
        graines.value = [];
        return;
    }
    const { data } = await http.get('/graines', { params: { graineType: form.graineType } });
    graines.value = data.items;
}

async function loadLots() {
    if (!form.graine) {
        lots.value = [];
        return;
    }
    const { data } = await http.get('/graine-lots', { params: { graine: form.graine } });
    lots.value = data.items;
}

// Cascade : changer le type recharge les graines et réinitialise graine/lot.
watch(() => form.graineType, async () => {
    form.graine = null;
    form.graineLot = null;
    lots.value = [];
    await loadGraines();
});
watch(() => form.graine, async () => {
    form.graineLot = null;
    await loadLots();
});

async function loadEntity() {
    const { data } = await http.get(`/semis/${id.value}`);
    form.graineType = data.graineType?.id ?? null;
    await loadGraines();
    form.graine = data.graineLot?.graine?.id ?? null;
    await loadLots();
    form.graineLot = data.graineLot?.id ?? null;
    form.methode = data.methode ?? 'godet';
    form.dateSemis = data.dateSemis ?? '';
    form.dateLevee = data.dateLevee ?? '';
    form.datePlantation = data.datePlantation ?? '';
    form.datePlantationTheorique = data.datePlantationTheorique ?? '';
    form.dateRecolteTheorique = data.dateRecolteTheorique ?? '';
    form.echec = data.echec ?? false;
    form.notes = data.notes ?? '';
    rempotages.value = data.rempotages ?? [];
}

async function addRempotage() {
    rempotageError.value = null;
    if (!newRempotage.date) return;
    try {
        const { data } = await http.post(`/semis/${id.value}/rempotages`, {
            date: newRempotage.date,
            notes: newRempotage.notes || null,
        });
        rempotages.value = data.rempotages ?? [];
        newRempotage.notes = '';
    } catch (e) {
        rempotageError.value = e.response?.data?.message ?? 'Ajout impossible.';
    }
}

async function deleteRempotage(r) {
    rempotageError.value = null;
    try {
        const { data } = await http.delete(`/semis/${id.value}/rempotages/${r.id}`);
        rempotages.value = data.rempotages ?? [];
    } catch (e) {
        rempotageError.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = {
            saison: seasons.currentId,
            graineType: form.graineType,
            graineLot: form.graineLot || null,
            methode: form.methode,
            dateSemis: form.dateSemis || null,
            dateLevee: form.dateLevee || null,
            datePlantation: form.datePlantation || null,
            datePlantationTheorique: form.datePlantationTheorique || null,
            dateRecolteTheorique: form.dateRecolteTheorique || null,
            echec: form.echec,
            notes: form.notes,
        };
        if (isEdit.value) {
            await http.put(`/semis/${id.value}`, payload);
        } else {
            await http.post('/semis', payload);
        }
        router.push({ name: 'semis-index' });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else if (e.response?.status === 409) {
            errors.value = { _global: e.response.data.message ?? 'Saison clôturée (lecture seule).' };
        } else {
            errors.value = { _global: 'Erreur lors de l\'enregistrement.' };
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    try {
        await loadGraineTypes();
        if (isEdit.value) await loadEntity();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <h1 class="mb-3">{{ isEdit ? 'Éditer — semis' : 'Nouveau semis' }}</h1>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <form v-else @submit.prevent="submit" style="max-width: 640px">
        <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>
        <div v-if="!seasons.currentId" class="alert alert-warning">Sélectionnez une saison dans la barre de navigation.</div>

        <div class="card mb-4 shadow-sm">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Type de graine *</label>
                    <select v-model="form.graineType" class="form-select" :class="{ 'is-invalid': errors.graineType }" required>
                        <option :value="null" disabled>— Choisir —</option>
                        <option v-for="t in graineTypes" :key="t.id" :value="t.id">{{ t.name }} ({{ t.code }})</option>
                    </select>
                    <div v-if="errors.graineType" class="invalid-feedback">{{ errors.graineType }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Méthode *</label>
                    <select v-model="form.methode" class="form-select" :class="{ 'is-invalid': errors.methode }">
                        <option value="godet">Godet (couvert)</option>
                        <option value="direct">Semis direct (pleine terre)</option>
                    </select>
                    <div v-if="errors.methode" class="invalid-feedback">{{ errors.methode }}</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Graine (optionnel)</label>
                    <select v-model="form.graine" class="form-select" :disabled="!form.graineType || graines.length === 0">
                        <option :value="null">— Aucune —</option>
                        <option v-for="g in graines" :key="g.id" :value="g.id">{{ g.code }} — {{ g.name }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lot consommé (optionnel)</label>
                    <select v-model="form.graineLot" class="form-select" :disabled="!form.graine || lots.length === 0">
                        <option :value="null">— Aucun —</option>
                        <option v-for="l in lots" :key="l.id" :value="l.id">
                            {{ l.source }} {{ l.dateAcquisition }} — reste {{ l.quantiteRestante }}
                        </option>
                    </select>
                    <div class="form-text">Le lot choisi est décrémenté de 1.</div>
                </div>
            </div>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-header">Suivi</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Date de semis *</label>
                    <input v-model="form.dateSemis" type="date" class="form-control" :class="{ 'is-invalid': errors.dateSemis }" required>
                    <div v-if="errors.dateSemis" class="invalid-feedback">{{ errors.dateSemis }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de levée</label>
                    <input v-model="form.dateLevee" type="date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de plantation</label>
                    <input v-model="form.datePlantation" type="date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Plantation théorique</label>
                    <input v-model="form.datePlantationTheorique" type="date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Récolte théorique</label>
                    <input v-model="form.dateRecolteTheorique" type="date" class="form-control">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input v-model="form.echec" class="form-check-input" type="checkbox" id="echec">
                        <label class="form-check-label" for="echec">Échec</label>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isEdit" class="card mb-4 shadow-sm">
            <div class="card-header">Rempotages</div>
            <div class="card-body">
                <div v-if="rempotageError" class="alert alert-danger">{{ rempotageError }}</div>
                <table v-if="rempotages.length" class="table table-sm align-middle">
                    <thead>
                        <tr><th style="width: 150px">Date</th><th>Notes</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rempotages" :key="r.id">
                            <td>{{ r.date }}</td>
                            <td>{{ r.notes ?? '—' }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="deleteRempotage(r)"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-muted">Aucun rempotage.</p>

                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label">Date</label>
                        <input v-model="newRempotage.date" type="date" class="form-control">
                    </div>
                    <div class="col">
                        <label class="form-label">Notes</label>
                        <input v-model="newRempotage.notes" class="form-control" placeholder="ex. godet plus grand">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-primary" :disabled="!newRempotage.date" @click="addRempotage"><i class="bi bi-plus-lg"></i> Ajouter</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <label class="form-label">Notes</label>
                <textarea v-model="form.notes" class="form-control" rows="2"></textarea>
            </div>
        </div>

        <button class="btn btn-primary" :disabled="saving || !seasons.currentId">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
        <router-link class="btn btn-outline-secondary" :to="{ name: 'semis-index' }">Annuler</router-link>
    </form>
</template>
