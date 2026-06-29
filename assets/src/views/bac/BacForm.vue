<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../../api/http';

const route = useRoute();
const router = useRouter();

const id = computed(() => route.params.id ?? null);
const isEdit = computed(() => id.value != null);

const form = reactive({
    name: '',
    largeurDefaut: 120,
    longueurDefaut: 80,
    lignesDefaut: 4,
    colonnesDefaut: 6,
    archived: false,
});
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

async function loadEntity() {
    const { data } = await http.get(`/bacs/${id.value}`);
    form.name = data.name ?? '';
    form.largeurDefaut = data.largeurDefaut ?? 0;
    form.longueurDefaut = data.longueurDefaut ?? 0;
    form.lignesDefaut = data.lignesDefaut ?? 1;
    form.colonnesDefaut = data.colonnesDefaut ?? 1;
    form.archived = data.archived ?? false;
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = { ...form };
        if (isEdit.value) {
            await http.put(`/bacs/${id.value}`, payload);
        } else {
            await http.post('/bacs', payload);
        }
        router.push({ name: 'bac-index' });
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
    <h1 class="mb-3">{{ isEdit ? 'Éditer · bac' : 'Nouveau bac' }}</h1>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <form v-else @submit.prevent="submit" class="row g-3" style="max-width: 560px">
        <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>

        <div class="col-12">
            <label class="form-label">Nom *</label>
            <input v-model="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" required autofocus placeholder="Carré nord">
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
        </div>

        <p class="text-muted small mb-0 mt-2">Taille physique et découpage <strong>par défaut</strong> : recopiés dans chaque nouvelle saison (modifiables ensuite par saison).</p>

        <div class="col-6">
            <label class="form-label">Largeur (cm) *</label>
            <input v-model.number="form.largeurDefaut" type="number" min="1" class="form-control" :class="{ 'is-invalid': errors.largeurDefaut }" required>
            <div v-if="errors.largeurDefaut" class="invalid-feedback">{{ errors.largeurDefaut }}</div>
        </div>
        <div class="col-6">
            <label class="form-label">Longueur (cm) *</label>
            <input v-model.number="form.longueurDefaut" type="number" min="1" class="form-control" :class="{ 'is-invalid': errors.longueurDefaut }" required>
            <div v-if="errors.longueurDefaut" class="invalid-feedback">{{ errors.longueurDefaut }}</div>
        </div>

        <div class="col-6">
            <label class="form-label">Lignes *</label>
            <input v-model.number="form.lignesDefaut" type="number" min="1" class="form-control" :class="{ 'is-invalid': errors.lignesDefaut }" required>
            <div v-if="errors.lignesDefaut" class="invalid-feedback">{{ errors.lignesDefaut }}</div>
        </div>
        <div class="col-6">
            <label class="form-label">Colonnes *</label>
            <input v-model.number="form.colonnesDefaut" type="number" min="1" class="form-control" :class="{ 'is-invalid': errors.colonnesDefaut }" required>
            <div v-if="errors.colonnesDefaut" class="invalid-feedback">{{ errors.colonnesDefaut }}</div>
        </div>

        <div class="col-12 form-check ms-2">
            <input v-model="form.archived" type="checkbox" class="form-check-input" id="archived">
            <label class="form-check-label" for="archived">Archivé (ne sera plus reporté dans les nouvelles saisons)</label>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
            <router-link class="btn btn-outline-secondary" :to="{ name: 'bac-index' }">Annuler</router-link>
        </div>
    </form>
</template>
