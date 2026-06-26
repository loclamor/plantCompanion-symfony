<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import http from '../../api/http';
import BacGrid from '../../components/potager/BacGrid.vue';

const BASE_SCALE = 0.72;        // px par cm affiché comme 100 %
const scale = ref(BASE_SCALE);  // zoom courant
const SNAP = 10;                // aimantation : pas de 10 cm

function zoomIn() { scale.value = Math.min(2, +(scale.value + 0.15).toFixed(2)); }
function zoomOut() { scale.value = Math.max(0.2, +(scale.value - 0.15).toFixed(2)); }

// Popup de création d'un bac (ajouté d'office à la saison active par l'API).
const bacModal = reactive({
    open: false,
    name: '',
    largeurDefaut: 120,
    longueurDefaut: 80,
    lignesDefaut: 4,
    colonnesDefaut: 6,
    saving: false,
    errors: {},
});

function openBacModal() {
    bacModal.open = true;
    bacModal.name = '';
    bacModal.largeurDefaut = 120;
    bacModal.longueurDefaut = 80;
    bacModal.lignesDefaut = 4;
    bacModal.colonnesDefaut = 6;
    bacModal.errors = {};
}

function closeBacModal() {
    bacModal.open = false;
}

async function submitBac() {
    if (!bacModal.name) return;
    bacModal.saving = true;
    bacModal.errors = {};
    try {
        // Position libre en bas à gauche des bacs existants (sinon empilés en 0,0).
        const freeY = bacSaisons.value.reduce((m, b) => Math.max(m, b.posY + b.longueur), 0);
        const snapY = Math.ceil((freeY + (freeY > 0 ? 20 : 0)) / SNAP) * SNAP;
        const existingIds = new Set(bacSaisons.value.map((b) => b.id));

        await http.post('/bacs', {
            name: bacModal.name,
            largeurDefaut: bacModal.largeurDefaut,
            longueurDefaut: bacModal.longueurDefaut,
            lignesDefaut: bacModal.lignesDefaut,
            colonnesDefaut: bacModal.colonnesDefaut,
        });
        closeBacModal();
        await load();

        // Repositionner le snapshot nouvellement créé pour éviter le chevauchement.
        const created = bacSaisons.value.find((b) => !existingIds.has(b.id));
        if (created && snapY > 0) {
            created.posY = snapY;
            await http.put(`/bac-saisons/${created.id}`, { posX: 0, posY: snapY });
        }
    } catch (e) {
        if (e.response?.status === 422) {
            bacModal.errors = e.response.data?.errors ?? {};
        } else {
            bacModal.errors = { _global: 'Création impossible.' };
        }
    } finally {
        bacModal.saving = false;
    }
}

const bacSaisons = ref([]);
const cultures = ref([]);
const saison = ref(null);
const loading = ref(false);
const error = ref(null);
const selectedBacId = ref(null);

const readonly = computed(() => saison.value?.statut === 'cloturee');

// État de drag d'un bac (pointer events).
const drag = reactive({ id: null, posX: 0, posY: 0, pointerStartX: 0, pointerStartY: 0, originX: 0, originY: 0, moved: false });

// Étendue du plan (cm) → dimensionne le canvas.
const extent = computed(() => {
    let w = 200;
    let h = 200;
    for (const b of bacSaisons.value) {
        const x = (drag.id === b.id ? drag.posX : b.posX) + b.largeur;
        const y = (drag.id === b.id ? drag.posY : b.posY) + b.longueur;
        if (x > w) w = x;
        if (y > h) h = y;
    }
    return { w: w + 40, h: h + 40 };
});

const selectedBac = computed(() => bacSaisons.value.find((b) => b.id === selectedBacId.value) ?? null);
const selectedCultures = computed(() => cultures.value.filter((c) => c.bacSaison?.id === selectedBacId.value));

// Édition du découpage (lignes/colonnes) du bac sélectionné.
const decoupage = reactive({ lignes: 1, colonnes: 1, saving: false, error: null });
watch(selectedBac, (b) => {
    if (b) {
        decoupage.lignes = b.lignes;
        decoupage.colonnes = b.colonnes;
        decoupage.error = null;
    }
});

async function saveDecoupage() {
    if (!selectedBac.value) return;
    decoupage.saving = true;
    decoupage.error = null;
    try {
        await http.put(`/bac-saisons/${selectedBac.value.id}`, {
            lignes: decoupage.lignes,
            colonnes: decoupage.colonnes,
        });
        await load();
    } catch (e) {
        if (e.response?.status === 409) {
            decoupage.error = e.response.data?.message ?? 'Des cultures sortiraient de la grille.';
        } else if (e.response?.status === 422) {
            decoupage.error = 'Valeurs invalides (≥ 1).';
        } else {
            decoupage.error = 'Modification impossible.';
        }
    } finally {
        decoupage.saving = false;
    }
}

function bacPos(b) {
    const x = drag.id === b.id ? drag.posX : b.posX;
    const y = drag.id === b.id ? drag.posY : b.posY;
    return { left: `${x * scale.value}px`, top: `${y * scale.value}px`, width: `${b.largeur * scale.value}px`, height: `${b.longueur * scale.value}px` };
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [bsRes, cRes] = await Promise.all([
            http.get('/bac-saisons'),
            http.get('/cultures'),
        ]);
        bacSaisons.value = bsRes.data.items;
        cultures.value = cRes.data.items;
        saison.value = bsRes.data.saison ?? cRes.data.saison;
        if (selectedBacId.value && !bacSaisons.value.some((b) => b.id === selectedBacId.value)) {
            selectedBacId.value = null;
        }
    } finally {
        loading.value = false;
    }
}

function onPointerDown(b, ev) {
    if (readonly.value) {
        selectedBacId.value = b.id;
        return;
    }
    ev.preventDefault();
    drag.id = b.id;
    drag.originX = b.posX;
    drag.originY = b.posY;
    drag.posX = b.posX;
    drag.posY = b.posY;
    drag.pointerStartX = ev.clientX;
    drag.pointerStartY = ev.clientY;
    drag.moved = false;
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp);
}

function onPointerMove(ev) {
    if (drag.id == null) return;
    const dx = (ev.clientX - drag.pointerStartX) / scale.value;
    const dy = (ev.clientY - drag.pointerStartY) / scale.value;
    if (Math.abs(ev.clientX - drag.pointerStartX) > 3 || Math.abs(ev.clientY - drag.pointerStartY) > 3) {
        drag.moved = true;
    }
    drag.posX = Math.max(0, drag.originX + dx);
    drag.posY = Math.max(0, drag.originY + dy);
}

async function onPointerUp() {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    const id = drag.id;
    const moved = drag.moved;
    drag.id = null;
    if (id == null) return;

    if (!moved) {
        selectedBacId.value = id; // simple clic = sélection
        return;
    }

    const snapX = Math.round(drag.posX / SNAP) * SNAP;
    const snapY = Math.round(drag.posY / SNAP) * SNAP;
    const bac = bacSaisons.value.find((b) => b.id === id);
    const prev = { posX: bac.posX, posY: bac.posY };
    bac.posX = snapX;
    bac.posY = snapY;
    try {
        await http.put(`/bac-saisons/${id}`, { posX: snapX, posY: snapY });
    } catch (e) {
        bac.posX = prev.posX; // rollback
        bac.posY = prev.posY;
        error.value = e.response?.data?.message ?? 'Déplacement du bac impossible.';
    }
}

onMounted(load);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">
            <i class="bi bi-pencil-square"></i> Plan du potager — édition
            <span v-if="saison" class="badge ms-2" :class="readonly ? 'text-bg-secondary' : 'text-bg-success'">
                {{ saison.name }}{{ readonly ? ' (clôturée)' : '' }}
            </span>
        </h1>
        <button v-if="saison && !readonly" type="button" class="btn btn-primary" @click="openBacModal">
            <i class="bi bi-plus-lg"></i> Nouveau bac
        </button>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-if="!saison && !loading" class="alert alert-warning">Sélectionnez une saison dans la barre de navigation.</div>
    <p v-else-if="readonly" class="text-muted small">Saison clôturée : plan en lecture seule.</p>
    <p v-else class="text-muted small">Glissez un bac pour le repositionner (aimantation {{ SNAP }} cm). Cliquez un bac pour voir sa grille.</p>

    <div v-if="saison" class="btn-group btn-group-sm mb-2" role="group">
        <button type="button" class="btn btn-outline-secondary" @click="zoomOut"><i class="bi bi-zoom-out"></i></button>
        <span class="btn btn-outline-secondary disabled">{{ Math.round(scale / BASE_SCALE * 100) }} %</span>
        <button type="button" class="btn btn-outline-secondary" @click="zoomIn"><i class="bi bi-zoom-in"></i></button>
    </div>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <template v-else-if="saison">
        <div class="plan-canvas" :style="{ width: `${extent.w * scale}px`, height: `${extent.h * scale}px` }">
            <div
                v-for="b in bacSaisons"
                :key="b.id"
                class="plan-bac"
                :class="{ selected: b.id === selectedBacId, draggable: !readonly }"
                :style="bacPos(b)"
                @pointerdown="onPointerDown(b, $event)"
            >
                <span class="plan-bac-label">{{ b.bac?.name }}<br><small>{{ b.largeur }}×{{ b.longueur }} cm</small></span>
            </div>
            <p v-if="bacSaisons.length === 0" class="text-muted p-3">Aucun bac dans cette saison.</p>
        </div>

        <div v-if="selectedBac" class="card mt-4 shadow-sm" style="max-width: 640px">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-grid-3x3"></i> {{ selectedBac.bac?.name }} — {{ selectedBac.lignes }}×{{ selectedBac.colonnes }} cases</span>
                <button type="button" class="btn-close" aria-label="Fermer" @click="selectedBacId = null"></button>
            </div>
            <div class="card-body">
                <div v-if="!readonly" class="row g-2 align-items-end mb-3" style="max-width: 360px">
                    <div class="col">
                        <label class="form-label mb-0 small">Lignes</label>
                        <input v-model.number="decoupage.lignes" type="number" min="1" class="form-control form-control-sm">
                    </div>
                    <div class="col-auto pb-1">×</div>
                    <div class="col">
                        <label class="form-label mb-0 small">Colonnes</label>
                        <input v-model.number="decoupage.colonnes" type="number" min="1" class="form-control form-control-sm">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-outline-primary" :disabled="decoupage.saving" @click="saveDecoupage">
                            {{ decoupage.saving ? '…' : 'Appliquer' }}
                        </button>
                    </div>
                </div>
                <div v-if="decoupage.error" class="alert alert-danger py-1 px-2 small">{{ decoupage.error }}</div>

                <BacGrid :bac-saison="selectedBac" :cultures="selectedCultures" :readonly="readonly" @moved="load" />
            </div>
        </div>
    </template>

    <!-- Popup de création d'un bac -->
    <div v-if="bacModal.open" class="plan-modal-backdrop" @click.self="closeBacModal">
        <div class="card shadow plan-modal" role="dialog" aria-modal="true">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-plus-square"></i> Nouveau bac</span>
                <button type="button" class="btn-close" aria-label="Fermer" @click="closeBacModal"></button>
            </div>
            <div class="card-body">
                <div v-if="bacModal.errors._global" class="alert alert-danger py-1 px-2 small">{{ bacModal.errors._global }}</div>
                <form @submit.prevent="submitBac" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nom *</label>
                        <input v-model="bacModal.name" class="form-control" :class="{ 'is-invalid': bacModal.errors.name }" required autofocus placeholder="Carré nord">
                        <div v-if="bacModal.errors.name" class="invalid-feedback">{{ bacModal.errors.name }}</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Largeur (cm) *</label>
                        <input v-model.number="bacModal.largeurDefaut" type="number" min="1" class="form-control" :class="{ 'is-invalid': bacModal.errors.largeurDefaut }" required>
                        <div v-if="bacModal.errors.largeurDefaut" class="invalid-feedback">{{ bacModal.errors.largeurDefaut }}</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Longueur (cm) *</label>
                        <input v-model.number="bacModal.longueurDefaut" type="number" min="1" class="form-control" :class="{ 'is-invalid': bacModal.errors.longueurDefaut }" required>
                        <div v-if="bacModal.errors.longueurDefaut" class="invalid-feedback">{{ bacModal.errors.longueurDefaut }}</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Lignes *</label>
                        <input v-model.number="bacModal.lignesDefaut" type="number" min="1" class="form-control" :class="{ 'is-invalid': bacModal.errors.lignesDefaut }" required>
                        <div v-if="bacModal.errors.lignesDefaut" class="invalid-feedback">{{ bacModal.errors.lignesDefaut }}</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Colonnes *</label>
                        <input v-model.number="bacModal.colonnesDefaut" type="number" min="1" class="form-control" :class="{ 'is-invalid': bacModal.errors.colonnesDefaut }" required>
                        <div v-if="bacModal.errors.colonnesDefaut" class="invalid-feedback">{{ bacModal.errors.colonnesDefaut }}</div>
                    </div>
                    <div class="col-12 text-end">
                        <button type="button" class="btn btn-outline-secondary" @click="closeBacModal">Annuler</button>
                        <button type="submit" class="btn btn-primary" :disabled="bacModal.saving || !bacModal.name">
                            {{ bacModal.saving ? 'Création…' : 'Créer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.plan-canvas {
    position: relative;
    background: #f1f3f5;
    border: 1px solid var(--bs-border-color, #ced4da);
    border-radius: 6px;
    min-width: 120px;
    min-height: 120px;
    overflow: auto;
}
.plan-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
}
.plan-modal {
    width: 100%;
    max-width: 460px;
    max-height: 90vh;
    overflow: auto;
}
.plan-bac {
    position: absolute;
    background: #c9a66b;
    border: 2px solid #8a6d3b;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b2f1b;
    font-size: 0.75rem;
    text-align: center;
    box-sizing: border-box;
    user-select: none;
    touch-action: none;
}
.plan-bac.draggable {
    cursor: grab;
}
.plan-bac.selected {
    outline: 3px solid var(--bs-success, #198754);
    outline-offset: 1px;
}
.plan-bac-label {
    line-height: 1.1;
    pointer-events: none;
}
</style>
