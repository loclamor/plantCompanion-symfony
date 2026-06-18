<script setup>
import { computed, onMounted, ref } from 'vue';
import exifr from 'exifr';
import http from '../api/http';

// Import par lot de photos (principe uploadMultipleV2 legacy) : on dépose
// plusieurs photos, on affecte à chacune une plante + une intervention
// optionnelle, puis on importe le tout.
const vegetables = ref([]);
const typesAction = ref([]);
const titresObservation = ref([]);
const items = ref([]);
const loading = ref(true);
const importing = ref(false);
let nextId = 0;

const allDone = computed(() => items.value.length > 0 && items.value.every((i) => i.status === 'done'));

async function loadReferences() {
    const [v, t] = await Promise.all([
        http.get('/vegetables/names'),
        http.get('/action-types'),
    ]);
    vegetables.value = v.data.items ?? v.data;
    typesAction.value = t.data.typesAction;
    titresObservation.value = t.data.titresObservation;
}

function todayInput() {
    return new Date().toISOString().slice(0, 10);
}

async function addFiles(fileList) {
    for (const file of Array.from(fileList)) {
        if (!file.type.startsWith('image/')) continue;
        const item = {
            localId: nextId++,
            file,
            preview: URL.createObjectURL(file),
            vegetable: null,
            date: todayInput(),
            typeAction: 'none',
            title: '',
            comment: '',
            status: 'pending',
            error: null,
        };
        items.value.push(item);
        // EXIF : date de prise de vue.
        exifr.parse(file, ['DateTimeOriginal']).then((tags) => {
            if (tags?.DateTimeOriginal) {
                item.date = new Date(tags.DateTimeOriginal).toISOString().slice(0, 10);
            }
        }).catch(() => {});
    }
}

function onPick(event) {
    addFiles(event.target.files);
    event.target.value = '';
}
function onDrop(event) {
    addFiles(event.dataTransfer.files);
}

function removeItem(item) {
    URL.revokeObjectURL(item.preview);
    items.value = items.value.filter((i) => i.localId !== item.localId);
}

function onTypeChange(item) {
    if (item.typeAction === 'observation' && !titresObservation.value.includes(item.title)) {
        item.title = titresObservation.value[0] ?? '';
    }
}

async function importItem(item) {
    if (!item.vegetable) {
        item.status = 'error';
        item.error = 'Plante requise.';
        return;
    }
    item.status = 'uploading';
    item.error = null;
    try {
        const fd = new FormData();
        fd.append('image', item.file);
        fd.append('vegetable', item.vegetable);
        fd.append('date', item.date);
        fd.append('typeAction', item.typeAction);
        if (item.typeAction !== 'none') {
            fd.append('title', item.title);
            fd.append('comment', item.comment);
        }
        await http.post('/photos/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        item.status = 'done';
    } catch (e) {
        item.status = 'error';
        const errs = e.response?.data?.errors;
        item.error = errs ? Object.values(errs).join(' ') : (e.response?.data?.message ?? 'Erreur.');
    }
}

async function importAll() {
    importing.value = true;
    for (const item of items.value) {
        if (item.status !== 'done') {
            await importItem(item);
        }
    }
    importing.value = false;
}

onMounted(async () => {
    loading.value = true;
    try {
        await loadReferences();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Importer des photos</h1>
        <button v-if="items.length" class="btn btn-primary" :disabled="importing" @click="importAll">
            {{ importing ? 'Import en cours…' : 'Tout importer' }}
        </button>
    </div>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <template v-else>
        <div
            class="border border-2 rounded p-4 text-center mb-4 text-muted"
            style="border-style: dashed !important; cursor: pointer;"
            @dragover.prevent
            @drop.prevent="onDrop"
            @click="$refs.fileInput.click()"
        >
            <input ref="fileInput" type="file" accept="image/*" multiple class="d-none" @change="onPick">
            <i class="bi bi-images fs-2"></i>
            <p class="mb-0">Glissez des photos ici, ou cliquez pour en choisir</p>
        </div>

        <div v-if="allDone" class="alert alert-success">
            Toutes les photos ont été importées.
            <router-link :to="{ name: 'vegetable-index' }">Voir les plantes</router-link>
        </div>

        <div v-for="item in items" :key="item.localId" class="card mb-3" :class="{ 'border-success': item.status === 'done', 'border-danger': item.status === 'error' }">
            <div class="row g-0">
                <div class="col-md-3">
                    <img :src="item.preview" class="img-fluid rounded-start h-100" style="object-fit: cover" alt="aperçu">
                </div>
                <div class="col-md-9">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span v-if="item.status === 'done'" class="badge bg-success mb-2">Importée</span>
                            <span v-else-if="item.status === 'uploading'" class="badge bg-secondary mb-2">Envoi…</span>
                            <span v-else-if="item.status === 'error'" class="badge bg-danger mb-2">{{ item.error }}</span>
                            <button v-if="item.status !== 'done'" class="btn btn-sm btn-link text-danger ms-auto" @click="removeItem(item)">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Plante *</label>
                                <select v-model="item.vegetable" class="form-select" :disabled="item.status === 'done'" required>
                                    <option :value="null" disabled>— Choisir —</option>
                                    <option v-for="v in vegetables" :key="v.id" :value="v.id">{{ v.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date</label>
                                <input v-model="item.date" type="date" class="form-control" :disabled="item.status === 'done'">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Intervention</label>
                                <select v-model="item.typeAction" class="form-select" :disabled="item.status === 'done'" @change="onTypeChange(item)">
                                    <option value="none">Sans action</option>
                                    <option v-for="t in typesAction" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>

                            <template v-if="item.typeAction !== 'none'">
                                <div class="col-md-6">
                                    <label class="form-label">Titre</label>
                                    <select v-if="item.typeAction === 'observation'" v-model="item.title" class="form-select" :disabled="item.status === 'done'">
                                        <option v-for="o in titresObservation" :key="o" :value="o">{{ o }}</option>
                                    </select>
                                    <input v-else v-model="item.title" class="form-control" :disabled="item.status === 'done'">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Commentaire</label>
                                    <input v-model="item.comment" class="form-control" :disabled="item.status === 'done'">
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>
