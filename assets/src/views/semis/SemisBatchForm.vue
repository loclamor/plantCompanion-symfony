<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '../../api/http';
import { useSeasonStore } from '../../stores/season';

const router = useRouter();
const seasons = useSeasonStore();

const today = new Date().toISOString().slice(0, 10);

function newRow() {
    return {
        graineType: null,
        graine: null,
        graineLot: null,
        methode: 'godet',
        dateSemis: today,
        quantite: 1,
        graines: [],
        lots: [],
    };
}

const rows = reactive([newRow()]);
const graineTypes = ref([]);
const errors = ref({});
const saving = ref(false);

async function loadGraineTypes() {
    const { data } = await http.get('/graine-types');
    graineTypes.value = data.items;
}

function addRow() {
    rows.push(newRow());
}
function removeRow(i) {
    rows.splice(i, 1);
    if (rows.length === 0) rows.push(newRow());
}

async function onTypeChange(row) {
    row.graine = null;
    row.graineLot = null;
    row.graines = [];
    row.lots = [];
    if (!row.graineType) return;
    const { data } = await http.get('/graines', { params: { graineType: row.graineType } });
    row.graines = data.items;
}

async function onGraineChange(row) {
    row.graineLot = null;
    row.lots = [];
    if (!row.graine) return;
    const { data } = await http.get('/graine-lots', { params: { graine: row.graine } });
    row.lots = data.items;
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const entries = rows.map((r) => ({
            graineType: r.graineType,
            graineLot: r.graineLot || null,
            methode: r.methode,
            dateSemis: r.dateSemis || null,
            quantite: Number(r.quantite) || 0,
        }));
        await http.post('/semis/batch', { saison: seasons.currentId, entries });
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

function entryError(i, field) {
    return errors.value?.entries?.[i]?.[field];
}

onMounted(loadGraineTypes);
</script>

<template>
    <h1 class="mb-3">Semis multiples</h1>
    <p class="text-muted">Ajoutez une ligne par type/lot ; la quantité crée autant de semis individuels.</p>

    <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>
    <div v-if="errors.saison" class="alert alert-danger">{{ errors.saison }}</div>
    <div v-if="!seasons.currentId" class="alert alert-warning">Sélectionnez une saison dans la barre de navigation.</div>

    <form @submit.prevent="submit">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="min-width: 160px">Type *</th>
                        <th style="min-width: 140px">Graine</th>
                        <th style="min-width: 170px">Lot</th>
                        <th style="min-width: 130px">Méthode</th>
                        <th style="min-width: 150px">Date semis *</th>
                        <th style="width: 90px">Quantité *</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i">
                        <td>
                            <select v-model="row.graineType" class="form-select form-select-sm" :class="{ 'is-invalid': entryError(i, 'graineType') }" @change="onTypeChange(row)" required>
                                <option :value="null" disabled>—</option>
                                <option v-for="t in graineTypes" :key="t.id" :value="t.id">{{ t.name }} ({{ t.code }})</option>
                            </select>
                        </td>
                        <td>
                            <select v-model="row.graine" class="form-select form-select-sm" :disabled="!row.graineType || row.graines.length === 0" @change="onGraineChange(row)">
                                <option :value="null">—</option>
                                <option v-for="g in row.graines" :key="g.id" :value="g.id">{{ g.code }}</option>
                            </select>
                        </td>
                        <td>
                            <select v-model="row.graineLot" class="form-select form-select-sm" :disabled="!row.graine || row.lots.length === 0">
                                <option :value="null">—</option>
                                <option v-for="l in row.lots" :key="l.id" :value="l.id">{{ l.source }} · reste {{ l.quantiteRestante }}</option>
                            </select>
                        </td>
                        <td>
                            <select v-model="row.methode" class="form-select form-select-sm" :class="{ 'is-invalid': entryError(i, 'methode') }">
                                <option value="godet">Godet</option>
                                <option value="direct">Direct</option>
                            </select>
                        </td>
                        <td>
                            <input v-model="row.dateSemis" type="date" class="form-control form-control-sm" :class="{ 'is-invalid': entryError(i, 'dateSemis') }" required>
                        </td>
                        <td>
                            <input v-model.number="row.quantite" type="number" min="1" class="form-control form-control-sm" :class="{ 'is-invalid': entryError(i, 'quantite') }" required>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeRow(i)" title="Retirer"><i class="bi bi-x-lg"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" @click="addRow"><i class="bi bi-plus-lg"></i> Ajouter une ligne</button>

        <div>
            <button class="btn btn-primary" :disabled="saving || !seasons.currentId">{{ saving ? 'Enregistrement…' : 'Créer les semis' }}</button>
            <router-link class="btn btn-outline-secondary" :to="{ name: 'semis-index' }">Annuler</router-link>
        </div>
    </form>
</template>
