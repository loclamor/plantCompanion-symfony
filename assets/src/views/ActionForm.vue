<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import exifr from 'exifr';
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
const titresObservation = ref([]);
const selectedFiles = ref([]);
const fileInput = ref(null);

const isObservation = computed(() => form.typeAction === 'observation');

// Mode multi : intervention appliquée à plusieurs plantes (depuis la sélection
// de la liste). Pas de plante unique ni de photos dans ce mode.
const multiIds = String(route.query.vegetables ?? '')
    .split(',')
    .filter(Boolean)
    .map(Number);
const isMulti = computed(() => !isEdit.value && multiIds.length > 0);
const multiNames = computed(() => vegetables.value.filter((v) => multiIds.includes(v.id)));

const form = reactive({
    vegetable: null,
    date: '',
    typeAction: null,
    title: '',
    comment: '',
});

function toLocalInput(value) {
    if (!value) return '';
    const d = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// Bascule titre : observation -> select des titres prédéfinis, sinon input libre.
watch(isObservation, (obs) => {
    if (obs && !titresObservation.value.includes(form.title)) {
        form.title = titresObservation.value[0] ?? '';
    }
});

async function loadReferences() {
    const [v, t] = await Promise.all([
        http.get('/vegetables/names'),
        http.get('/action-types'),
    ]);
    vegetables.value = v.data.items ?? v.data;
    typesAction.value = t.data.typesAction;
    titresObservation.value = t.data.titresObservation;
}

async function loadAction() {
    const { data } = await http.get(`/actions/${props.id}`);
    form.vegetable = data.vegetable?.id ?? null;
    form.date = toLocalInput(data.date);
    form.typeAction = data.typeAction;
    form.title = data.title ?? '';
    form.comment = data.comment ?? '';
}

// Lit la date EXIF de la première photo et pré-remplit le champ date (legacy).
async function onFilesChange(event) {
    selectedFiles.value = Array.from(event.target.files);
    if (selectedFiles.value.length === 0) return;
    try {
        const tags = await exifr.parse(selectedFiles.value[0], ['DateTimeOriginal']);
        if (tags?.DateTimeOriginal) {
            form.date = toLocalInput(tags.DateTimeOriginal);
        }
    } catch {
        // pas de métadonnée EXIF exploitable : on ignore
    }
}

async function uploadPhotos(actionId) {
    if (selectedFiles.value.length === 0) return;
    const fd = new FormData();
    for (const f of selectedFiles.value) fd.append('images', f);
    await http.post(`/actions/${actionId}/photos`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        if (isMulti.value) {
            await http.post('/actions/bulk', {
                vegetables: multiIds,
                date: form.date,
                typeAction: form.typeAction,
                title: form.title,
                comment: form.comment,
            });
            router.push({ name: 'action-index' });
            return;
        }

        let actionId = props.id;
        if (isEdit.value) {
            await http.put(`/actions/${props.id}`, { ...form });
        } else {
            const { data } = await http.post('/actions', { ...form });
            actionId = data.id;
        }
        await uploadPhotos(actionId);
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
            const pre = Number(route.query.vegetable);
            if (pre > 0) form.vegetable = pre;
            form.date = toLocalInput(new Date());
        }
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="mx-auto" style="max-width: 700px">
        <h1 class="mb-4">
            {{ isEdit ? 'Éditer l\'intervention' : (isMulti ? 'Intervention sur plusieurs plantes' : 'Nouvelle intervention') }}
        </h1>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <form v-else @submit.prevent="submit" class="card shadow-sm">
            <div class="card-body">
                <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>

                <div v-if="isMulti" class="mb-3">
                    <label class="form-label">Plantes ciblées ({{ multiNames.length }})</label>
                    <div>
                        <span v-for="v in multiNames" :key="v.id" class="badge bg-success me-1 mb-1">{{ v.name }}</span>
                    </div>
                    <div v-if="errors.vegetables" class="text-danger small mt-1">{{ errors.vegetables }}</div>
                </div>
                <div v-else class="mb-3">
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
                    <!-- observation -> select des titres prédéfinis ; sinon input libre -->
                    <select v-if="isObservation" v-model="form.title" class="form-select" :class="{ 'is-invalid': errors.title }">
                        <option v-for="o in titresObservation" :key="o" :value="o">{{ o }}</option>
                    </select>
                    <input v-else v-model="form.title" class="form-control" :class="{ 'is-invalid': errors.title }" required>
                    <div v-if="errors.title" class="invalid-feedback">{{ errors.title }}</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Commentaire</label>
                    <textarea v-model="form.comment" class="form-control" rows="3"></textarea>
                </div>

                <div v-if="!isMulti" class="mb-3">
                    <label class="form-label">Photos</label>
                    <input ref="fileInput" type="file" accept="image/*" multiple class="form-control" @change="onFilesChange">
                    <div v-if="selectedFiles.length" class="form-text">
                        {{ selectedFiles.length }} photo(s) — la date EXIF de la première pré-remplit le champ Date.
                    </div>
                </div>

                <button class="btn btn-primary px-4" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
                <router-link class="btn btn-outline-secondary" :to="{ name: 'action-index' }">Annuler</router-link>
            </div>
        </form>
    </div>
</template>
