<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../../api/http';
import { useSeasonStore } from '../../stores/season';

const route = useRoute();
const router = useRouter();
const seasons = useSeasonStore();

const id = computed(() => route.params.id ?? null);
const isEdit = computed(() => id.value != null);

const form = reactive({
    name: '',
    annee: new Date().getFullYear(),
    dateDebut: '',
    dateFin: '',
});
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

async function loadEntity() {
    const { data } = await http.get(`/saisons/${id.value}`);
    form.name = data.name ?? '';
    form.annee = data.annee ?? new Date().getFullYear();
    form.dateDebut = data.dateDebut ?? '';
    form.dateFin = data.dateFin ?? '';
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = {
            name: form.name,
            annee: form.annee,
            dateDebut: form.dateDebut || null,
            dateFin: form.dateFin || null,
        };
        if (isEdit.value) {
            await http.put(`/saisons/${id.value}`, payload);
        } else {
            await http.post('/saisons', payload);
        }
        // La création (auto-clôture de l'ancienne) modifie la saison courante.
        await seasons.fetchSeasons();
        await seasons.fetchCurrent();
        router.push({ name: 'saison-index' });
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
        if (isEdit.value) await loadEntity();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <h1 class="mb-3">{{ isEdit ? 'Éditer — saison' : 'Nouvelle saison' }}</h1>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <form v-else @submit.prevent="submit" class="row g-3" style="max-width: 520px">
        <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>

        <div class="col-8">
            <label class="form-label">Nom *</label>
            <input v-model="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" required autofocus placeholder="Saison 2026">
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
        </div>
        <div class="col-4">
            <label class="form-label">Année *</label>
            <input v-model.number="form.annee" type="number" class="form-control" :class="{ 'is-invalid': errors.annee }" required>
            <div v-if="errors.annee" class="invalid-feedback">{{ errors.annee }}</div>
        </div>

        <div class="col-6">
            <label class="form-label">Date de début *</label>
            <input v-model="form.dateDebut" type="date" class="form-control" :class="{ 'is-invalid': errors.dateDebut }" required>
            <div v-if="errors.dateDebut" class="invalid-feedback">{{ errors.dateDebut }}</div>
        </div>
        <div class="col-6">
            <label class="form-label">Date de fin</label>
            <input v-model="form.dateFin" type="date" class="form-control" :class="{ 'is-invalid': errors.dateFin }">
            <div v-if="errors.dateFin" class="invalid-feedback">{{ errors.dateFin }}</div>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
            <router-link class="btn btn-outline-secondary" :to="{ name: 'saison-index' }">Annuler</router-link>
        </div>
    </form>
</template>
