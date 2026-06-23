<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../../api/http';

const route = useRoute();
const router = useRouter();

const id = computed(() => route.params.id ?? null);
const isEdit = computed(() => id.value != null);

const form = reactive({ name: '', code: '' });
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

async function loadEntity() {
    const { data } = await http.get(`/graine-types/${id.value}`);
    form.name = data.name ?? '';
    form.code = data.code ?? '';
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        if (isEdit.value) {
            await http.put(`/graine-types/${id.value}`, { ...form });
        } else {
            await http.post('/graine-types', { ...form });
        }
        router.push({ name: 'graine-type-index' });
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
    <h1 class="mb-3">{{ isEdit ? 'Éditer — type de graine' : 'Nouveau — type de graine' }}</h1>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <form v-else @submit.prevent="submit" class="row g-3" style="max-width: 520px">
        <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>

        <div class="col-8">
            <label class="form-label">Nom *</label>
            <input v-model="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" required autofocus placeholder="Tomate Cerise">
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
        </div>
        <div class="col-4">
            <label class="form-label">Préfixe *</label>
            <input v-model="form.code" class="form-control" :class="{ 'is-invalid': errors.code }" required placeholder="TC">
            <div v-if="errors.code" class="invalid-feedback">{{ errors.code }}</div>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
            <router-link class="btn btn-outline-secondary" :to="{ name: 'graine-type-index' }">Annuler</router-link>
        </div>
    </form>
</template>
