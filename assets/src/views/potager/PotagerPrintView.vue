<script setup>
import { computed, onMounted, ref } from 'vue';
import http from '../../api/http';

// Groupes d'étiquettes (semis vivants + cultures directes), regroupés par (code, nom).
// Chaque groupe reçoit un index local servant de clé de sélection/quantité.
const groups = ref([]);
const loading = ref(true);
const selected = ref(new Set());
const quantities = ref(new Map());
const search = ref('');

const filtered = computed(() => groups.value.filter((g) => {
    if (!search.value) return true;
    const q = search.value.toLowerCase();
    return g.name.toLowerCase().includes(q) || (g.code ?? '').toLowerCase().includes(q);
}));

// Liste plate des étiquettes à imprimer : chaque groupe sélectionné répété selon sa quantité.
const toPrint = computed(() => {
    const out = [];
    for (const g of groups.value) {
        if (!selected.value.has(g.idx)) continue;
        const qty = Number(quantities.value.get(g.idx)) || 0;
        for (let i = 0; i < qty; i++) out.push({ code: g.code, name: g.name });
    }
    return out;
});

// Découpage en pages A4 : 21 étiquettes (3 colonnes × 7 lignes).
const PER_PAGE = 21;
const pages = computed(() => {
    const out = [];
    for (let i = 0; i < toPrint.value.length; i += PER_PAGE) {
        out.push(toPrint.value.slice(i, i + PER_PAGE));
    }
    return out;
});

function toggle(idx) {
    const next = new Set(selected.value);
    next.has(idx) ? next.delete(idx) : next.add(idx);
    selected.value = next;
}
function setQty(idx, value) {
    const next = new Map(quantities.value);
    next.set(idx, Math.max(0, Number(value) || 0));
    quantities.value = next;
}
function selectAllFiltered() {
    const next = new Set(selected.value);
    filtered.value.forEach((g) => next.add(g.idx));
    selected.value = next;
}
function clearAll() {
    selected.value = new Set();
}
function doPrint() {
    window.print();
}

onMounted(async () => {
    loading.value = true;
    try {
        const { data } = await http.get('/print/cultures');
        groups.value = data.items.map((g, idx) => ({ ...g, idx }));
        // Par défaut tout est sélectionné, quantité = nombre réel d'items du groupe.
        selected.value = new Set(groups.value.map((g) => g.idx));
        quantities.value = new Map(groups.value.map((g) => [g.idx, g.count]));
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="no-print">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">Impression d'étiquettes · Cultures</h1>
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
                    <input v-model="search" class="form-control" placeholder="Rechercher un code ou un nom">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary" @click="selectAllFiltered">Tout sélectionner</button>
                    <button class="btn btn-outline-secondary" @click="clearAll">Aucune</button>
                </div>
            </div>

            <div class="border rounded p-2 mb-4" style="max-height: 320px; overflow-y: auto;">
                <div v-for="g in filtered" :key="g.idx" class="d-flex align-items-center gap-2 py-1">
                    <input :id="'pick-' + g.idx" class="form-check-input mt-0" type="checkbox" :checked="selected.has(g.idx)" @change="toggle(g.idx)">
                    <label class="form-check-label flex-grow-1" :for="'pick-' + g.idx">
                        <strong v-if="g.code">{{ g.code }}</strong>
                        <span>{{ g.name }}</span>
                        <small class="text-muted">({{ g.count }})</small>
                    </label>
                    <input
                        class="form-control form-control-sm" type="number" min="0" style="width: 80px"
                        :value="quantities.get(g.idx)" @input="setQty(g.idx, $event.target.value)">
                </div>
                <p v-if="filtered.length === 0" class="text-muted mb-0">Aucune étiquette à imprimer pour la saison courante.</p>
            </div>

            <h2 class="h5">
                Aperçu · {{ toPrint.length }} étiquette(s),
                {{ pages.length }} page(s) A4
            </h2>
        </template>
    </div>

    <!-- Zone imprimée : pages A4 (cadre pointillé à l'écran), 3 colonnes -->
    <div v-for="(pageItems, p) in pages" :key="p" class="print-page">
        <div class="page-grid">
            <div v-for="(it, i) in pageItems" :key="i" class="culture-label card" :class="{ 'no-code': !it.code }">
                <div class="card-body">
                    <div v-if="it.code" class="label-code">{{ it.code }}</div>
                    <div class="label-name">{{ it.name }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Page A4 : cadre pointillé à l'écran représentant la feuille. */
.print-page {
    width: 21cm;
    max-width: 100%;
    border: 2px dashed #bbb;
    border-radius: 6px;
    padding: 1cm;
    margin: 0 auto 1.5rem;
}
.page-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.4cm;
}
.culture-label {
    min-height: 2.6cm;
    overflow: hidden;
    margin: 0;
}
.culture-label .card-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 0.3cm;
}
.label-code {
    font-size: 1.7rem;
    font-weight: 700;
    line-height: 1.1;
}
.label-name {
    font-size: 1rem;
    margin-top: 0.15cm;
}
/* Nom seul : centré et un peu plus gros. */
.culture-label.no-code .label-name {
    font-size: 1.3rem;
    font-weight: 600;
    margin-top: 0;
}
@media print {
    @page {
        size: A4 portrait;
        margin: 1cm;
    }
    .print-page {
        width: auto;
        max-width: none;
        border: 0;
        border-radius: 0;
        padding: 0;
        margin: 0;
        page-break-after: always;
    }
    .print-page:last-child {
        page-break-after: auto;
    }
    .culture-label {
        page-break-inside: avoid;
    }
}
</style>
