<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import { resourceOr404 } from '../config/parametrage';

const route = useRoute();
const router = useRouter();

const resourceKey = computed(() => route.params.resource);
const config = computed(() => resourceOr404(resourceKey.value));
const id = computed(() => route.params.id ?? null);
const isEdit = computed(() => id.value != null);

const form = reactive({ name: '' });
const options = reactive({}); // { fieldName: [ {id,name}, ... ] }
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

async function loadOptions() {
    for (const field of config.value.fields) {
        if (field.kind === 'select') {
            const { data } = await http.get(field.optionsEndpoint);
            options[field.name] = data.items.filter((o) => o.id !== Number(id.value)); // pas soi-même comme parent
            if (!(field.name in form)) form[field.name] = null;
        }
    }
}

async function loadEntity() {
    const { data } = await http.get(`${config.value.endpoint}/${id.value}`);
    form.name = data.name ?? '';
    for (const field of config.value.fields) {
        form[field.name] = data[field.name]?.id ?? null;
    }
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        if (isEdit.value) {
            await http.put(`${config.value.endpoint}/${id.value}`, { ...form });
        } else {
            await http.post(config.value.endpoint, { ...form });
        }
        router.push({ name: 'parametrage-index', params: { resource: resourceKey.value } });
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
        if (config.value) {
            await loadOptions();
            if (isEdit.value) await loadEntity();
        }
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div v-if="!config" class="alert alert-warning">Ressource inconnue.</div>

    <template v-else>
        <h1 class="mb-3">{{ isEdit ? `Éditer — ${config.singular}` : `Nouveau — ${config.singular}` }}</h1>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <form v-else @submit.prevent="submit" class="row g-3" style="max-width: 600px">
            <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>

            <div class="col-12">
                <label class="form-label">Nom *</label>
                <input v-model="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" required autofocus>
                <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
            </div>

            <div v-for="field in config.fields" :key="field.name" class="col-12">
                <label class="form-label">{{ field.label }}{{ field.required ? ' *' : '' }}</label>
                <select v-model="form[field.name]" class="form-select" :class="{ 'is-invalid': errors[field.name] }" :required="field.required">
                    <option :value="null" :disabled="field.required">{{ field.required ? '— Choisir —' : '— Aucun —' }}</option>
                    <option v-for="o in options[field.name]" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <div v-if="errors[field.name]" class="invalid-feedback">{{ errors[field.name] }}</div>
            </div>

            <div class="col-12">
                <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
                <router-link class="btn btn-outline-secondary" :to="{ name: 'parametrage-index', params: { resource: resourceKey } }">Annuler</router-link>
            </div>
        </form>
    </template>
</template>
