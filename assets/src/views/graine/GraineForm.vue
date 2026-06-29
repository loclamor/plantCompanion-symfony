<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../../api/http';

const route = useRoute();
const router = useRouter();

const id = computed(() => route.params.id ?? null);
const isEdit = computed(() => id.value != null);

const MOIS = [
    { v: 1, l: 'Janvier' }, { v: 2, l: 'Février' }, { v: 3, l: 'Mars' },
    { v: 4, l: 'Avril' }, { v: 5, l: 'Mai' }, { v: 6, l: 'Juin' },
    { v: 7, l: 'Juillet' }, { v: 8, l: 'Août' }, { v: 9, l: 'Septembre' },
    { v: 10, l: 'Octobre' }, { v: 11, l: 'Novembre' }, { v: 12, l: 'Décembre' },
];

const form = reactive({
    graineType: null,
    code: '',
    name: '',
    methodeSemisConseillee: null,
    moisSemis: null,
    moisPlantationTheorique: null,
    moisRecolteTheorique: null,
    notes: '',
});
// Création inline d'un type (valeur sentinelle '-new-' du select).
const newType = reactive({ name: '', code: '' });
const isNewType = computed(() => form.graineType === '-new-');

const graineTypes = ref([]);
const codeTouched = ref(false);
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

async function loadTypes() {
    const { data } = await http.get('/graine-types');
    graineTypes.value = data.items;
}

async function loadEntity() {
    const { data } = await http.get(`/graines/${id.value}`);
    form.graineType = data.graineType?.id ?? null;
    form.code = data.code ?? '';
    form.name = data.name ?? '';
    form.methodeSemisConseillee = data.methodeSemisConseillee ?? null;
    form.moisSemis = data.moisSemis ?? null;
    form.moisPlantationTheorique = data.moisPlantationTheorique ?? null;
    form.moisRecolteTheorique = data.moisRecolteTheorique ?? null;
    form.notes = data.notes ?? '';
    codeTouched.value = true; // ne pas écraser le code existant
}

// Au changement de type (existant) en création : pré-remplir le code suggéré.
async function onTypeChange() {
    if (isEdit.value || codeTouched.value || isNewType.value || !form.graineType) return;
    try {
        const { data } = await http.get(`/graines/next-code?graineType=${form.graineType}`);
        form.code = data.code ?? '';
    } catch {
        /* silencieux : champ éditable manuellement */
    }
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        // Création inline du type de graine si demandé.
        if (isNewType.value) {
            const { data } = await http.post('/graine-types', { name: newType.name, code: newType.code });
            form.graineType = data.id;
            if (!form.code) {
                form.code = `${newType.code}1`;
            }
        }
        const payload = { ...form };
        if (isEdit.value) {
            await http.put(`/graines/${id.value}`, payload);
        } else {
            await http.post('/graines', payload);
        }
        router.push({ name: 'graine-index' });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
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
        await loadTypes();
        if (isEdit.value) await loadEntity();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <h1 class="mb-3">{{ isEdit ? 'Éditer · graine' : 'Nouvelle · graine' }}</h1>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <form v-else @submit.prevent="submit" style="max-width: 640px">
        <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>

        <div class="card mb-4 shadow-sm">
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label">Type de graine *</label>
                    <select v-model="form.graineType" class="form-select" :class="{ 'is-invalid': errors.graineType }" required @change="onTypeChange">
                        <option :value="null" disabled>— Choisir —</option>
                        <option v-for="t in graineTypes" :key="t.id" :value="t.id">{{ t.name }} ({{ t.code }})</option>
                        <option value="-new-">+ Nouveau type…</option>
                    </select>
                    <div v-if="errors.graineType" class="invalid-feedback">{{ errors.graineType }}</div>
                </div>

                <template v-if="isNewType">
                    <div class="col-8">
                        <label class="form-label">Nom du nouveau type *</label>
                        <input v-model="newType.name" class="form-control" required placeholder="Tomate Cerise">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Préfixe *</label>
                        <input v-model="newType.code" class="form-control" required placeholder="TC">
                    </div>
                </template>

                <div class="col-md-4">
                    <label class="form-label">Code *</label>
                    <input v-model="form.code" class="form-control" :class="{ 'is-invalid': errors.code }" placeholder="auto (TC1)" @input="codeTouched = true">
                    <div v-if="errors.code" class="invalid-feedback">{{ errors.code }}</div>
                    <div class="form-text">Laissé vide = généré automatiquement.</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Nom *</label>
                    <input v-model="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" required placeholder="Sweet">
                    <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Méthode de semis conseillée</label>
                    <select v-model="form.methodeSemisConseillee" class="form-select" :class="{ 'is-invalid': errors.methodeSemisConseillee }">
                        <option :value="null">— Aucune —</option>
                        <option value="pleine_terre">Pleine terre</option>
                        <option value="couvert">Couvert</option>
                    </select>
                    <div v-if="errors.methodeSemisConseillee" class="invalid-feedback">{{ errors.methodeSemisConseillee }}</div>
                </div>
            </div>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-header">Calendrier théorique</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Mois de semis</label>
                    <select v-model="form.moisSemis" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="m in MOIS" :key="m.v" :value="m.v">{{ m.l }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mois de plantation</label>
                    <select v-model="form.moisPlantationTheorique" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="m in MOIS" :key="m.v" :value="m.v">{{ m.l }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mois de récolte</label>
                    <select v-model="form.moisRecolteTheorique" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="m in MOIS" :key="m.v" :value="m.v">{{ m.l }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <label class="form-label">Notes</label>
                <textarea v-model="form.notes" class="form-control" rows="3"></textarea>
            </div>
        </div>

        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
        <router-link class="btn btn-outline-secondary" :to="{ name: 'graine-index' }">Annuler</router-link>
    </form>
</template>
