<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';

const route = useRoute();
const PLACEHOLDER = '/plante.png';

const items = ref([]);
const types = ref([]);
const loading = ref(true);
const selected = ref(new Set());
const filterType = ref('');
const search = ref('');

const filtered = computed(() => items.value.filter((it) => {
    if (filterType.value && it.type?.id !== Number(filterType.value)) return false;
    if (search.value && !it.name.toLowerCase().includes(search.value.toLowerCase())) return false;
    return true;
}));

// Plantes à imprimer (dans l'ordre de la liste).
const toPrint = computed(() => items.value.filter((it) => selected.value.has(it.id)));

function toggle(id) {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
}
function selectAllFiltered() {
    const next = new Set(selected.value);
    filtered.value.forEach((it) => next.add(it.id));
    selected.value = next;
}
function clearAll() {
    selected.value = new Set();
}
function onImgError(event) {
    event.target.onerror = null;
    event.target.src = PLACEHOLDER;
}
function doPrint() {
    window.print();
}

onMounted(async () => {
    loading.value = true;
    try {
        const { data } = await http.get('/print/labels');
        items.value = data.items;
        // types présents, pour le filtre
        const map = new Map();
        for (const it of items.value) if (it.type) map.set(it.type.id, it.type.name);
        types.value = [...map].map(([id, name]) => ({ id, name }));
        // pré-sélection depuis la sélection de la liste
        const pre = String(route.query.vegetables ?? '').split(',').filter(Boolean).map(Number);
        if (pre.length) selected.value = new Set(pre);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="no-print">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">Impression d'étiquettes</h1>
            <button class="btn btn-primary" :disabled="toPrint.length === 0" @click="doPrint">
                <i class="bi bi-printer"></i> Imprimer ({{ toPrint.length }})
            </button>
        </div>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <template v-else>
            <div class="row g-2 mb-3">
                <div class="col-auto">
                    <select v-model="filterType" class="form-select">
                        <option value="">Tous les types</option>
                        <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <input v-model="search" class="form-control" placeholder="Rechercher un nom">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary" @click="selectAllFiltered">Tout sélectionner</button>
                    <button class="btn btn-outline-secondary" @click="clearAll">Aucune</button>
                </div>
            </div>

            <div class="border rounded p-2 mb-4" style="max-height: 260px; overflow-y: auto;">
                <div v-for="it in filtered" :key="it.id" class="form-check">
                    <input :id="'pick-' + it.id" class="form-check-input" type="checkbox" :checked="selected.has(it.id)" @change="toggle(it.id)">
                    <label class="form-check-label" :for="'pick-' + it.id">
                        {{ it.name }} <small class="text-muted">{{ it.type?.name }}</small>
                    </label>
                </div>
                <p v-if="filtered.length === 0" class="text-muted mb-0">Aucune plante.</p>
            </div>

            <h2 class="h5">Aperçu ({{ toPrint.length }} étiquette(s))</h2>
        </template>
    </div>

    <!-- Zone imprimée : étiquettes (mise en page conservée du legacy) -->
    <div class="labels">
        <div v-for="it in toPrint" :key="it.id" class="plant-label">
            <strong>{{ it.name }}</strong><br>
            <em v-if="it.nomLatin">{{ it.nomLatin }}</em>
            <span v-if="it.nomLatin"><br></span>
            <span v-if="it.rusticite != null">Rusticité : {{ it.rusticite }}°C<br></span>
            <img :src="it.defaultPhotoUrl || PLACEHOLDER" alt="photo" class="label-img" @error="onImgError">
        </div>
    </div>
</template>

<style scoped>
.plant-label {
    width: 9.5cm;
    min-height: 6cm;
    border: 1px solid #ccc;
    padding: 0.5cm;
    margin: 0.3cm;
    display: inline-block;
    vertical-align: top;
}
.label-img {
    max-width: 100%;
    max-height: 4cm;
    object-fit: contain;
}
@media print {
    .plant-label {
        page-break-inside: avoid;
    }
}
</style>
