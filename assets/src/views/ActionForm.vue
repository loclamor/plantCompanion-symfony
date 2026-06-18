<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../api/http';

const props = defineProps({ id: { type: [String, Number], default: null } });
const route = useRoute();
const router = useRouter();

const isEdit = computed(() => props.id != null);
const loading = ref(true);
const saving = ref(false);
const errors = ref({});

const vegetables = ref([]);
const typesAction = ref([]);

const form = reactive({
    vegetable: null,
    date: '',
    typeAction: null,
    title: '',
    comment: '',
});

function toLocalInput(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

async function loadReferences() {
    const [v, t] = await Promise.all([
        http.get('/vegetables/names'),
        http.get('/action-types'),
    ]);
    vegetables.value = v.data.items ?? v.data;
    typesAction.value = t.data.typesAction;
}

async function loadAction() {
    const { data } = await http.get(`/actions/${props.id}`);
    form.vegetable = data.vegetable?.id ?? null;
    form.date = toLocalInput(data.date);
    form.typeAction = data.typeAction;
    form.title = data.title ?? '';
    form.comment = data.comment ?? '';
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        if (isEdit.value) {
            await http.put(`/actions/${props.id}`, { ...form });
        } else {
            await http.post('/actions', { ...form });
        }
        router.push({ name: 'action-index' });
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
        await loadReferences();
        if (isEdit.value) {
            await loadAction();
        } else {
            // Pré-sélection éventuelle de la plante via ?vegetable=ID (depuis la fiche).
            const pre = Number(route.query.vegetable);
            if (pre > 0) form.vegetable = pre;
            form.date = toLocalInput(new Date().toISOString());
        }
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="mx-auto" style="max-width: 700px">
        <h1 class="mb-4">{{ isEdit ? 'Éditer l\'intervention' : 'Nouvelle intervention' }}</h1>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <form v-else @submit.prevent="submit" class="card shadow-sm">
            <div class="card-body">
                <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>

                <div class="mb-3">
                    <label class="form-label">Plante *</label>
                    <select v-model="form.vegetable" class="form-select" :class="{ 'is-invalid': errors.vegetable }" required>
                        <option :value="null" disabled>— Choisir —</option>
                        <option v-for="v in vegetables" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                    <div v-if="errors.vegetable" class="invalid-feedback">{{ errors.vegetable }}</div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input v-model="form.date" type="datetime-local" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type d'intervention *</label>
                        <select v-model="form.typeAction" class="form-select" :class="{ 'is-invalid': errors.typeAction }" required>
                            <option :value="null" disabled>— Choisir —</option>
                            <option v-for="t in typesAction" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <div v-if="errors.typeAction" class="invalid-feedback">{{ errors.typeAction }}</div>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label">Titre *</label>
                    <input v-model="form.title" class="form-control" :class="{ 'is-invalid': errors.title }" required>
                    <div v-if="errors.title" class="invalid-feedback">{{ errors.title }}</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Commentaire</label>
                    <textarea v-model="form.comment" class="form-control" rows="3"></textarea>
                </div>

                <button class="btn btn-primary px-4" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
                <router-link class="btn btn-outline-secondary" :to="{ name: 'action-index' }">Annuler</router-link>
            </div>
        </form>
    </div>
</template>
