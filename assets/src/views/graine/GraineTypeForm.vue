<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../../api/http';
import { flattenGraineTypes, indentLabel } from '../../utils/graineTypeTree';

const route = useRoute();
const router = useRouter();

const id = computed(() => route.params.id ?? null);
const isEdit = computed(() => id.value != null);

const form = reactive({ name: '', code: '', parent: '' });
const errors = ref({});
const loading = ref(true);
const saving = ref(false);
const allTypes = ref([]);

// Parents possibles : tous les types sauf celui en cours d'édition. (La garde
// anti-cycle complète — exclusion des descendants — est faite côté backend.)
const parentOptions = computed(() =>
    flattenGraineTypes(allTypes.value.filter((t) => String(t.id) !== String(id.value)))
        .map((t) => ({ id: t.id, label: indentLabel(t) })),
);

async function loadTypes() {
    const { data } = await http.get('/graine-types');
    allTypes.value = data.items ?? data;
}

async function loadEntity() {
    const { data } = await http.get(`/graine-types/${id.value}`);
    form.name = data.name ?? '';
    form.code = data.code ?? '';
    form.parent = data.parentId ?? '';
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
        await loadTypes();
        if (isEdit.value) await loadEntity();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <h1 class="mb-3">{{ isEdit ? 'Éditer · type de graine' : 'Nouveau · type de graine' }}</h1>

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
            <label class="form-label">Type parent</label>
            <select v-model="form.parent" class="form-select" :class="{ 'is-invalid': errors.parent }">
                <option value="">— Aucun (type racine)</option>
                <option v-for="opt in parentOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
            <div v-if="errors.parent" class="invalid-feedback">{{ errors.parent }}</div>
            <div class="form-text">Regroupe ce type sous un autre (ex. « Pois nain » sous « Pois »). La recherche par le parent inclut ses sous-types.</div>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
            <router-link class="btn btn-outline-secondary" :to="{ name: 'graine-type-index' }">Annuler</router-link>
        </div>
    </form>
</template>
