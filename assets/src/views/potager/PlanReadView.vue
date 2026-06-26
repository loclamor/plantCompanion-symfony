<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import http from '../../api/http';

const BASE_SCALE = 0.72;        // px par cm affiché comme 100 %
const scale = ref(BASE_SCALE);  // zoom courant

const bacSaisons = ref([]);
const cultures = ref([]);
const semisLeves = ref([]);
const saison = ref(null);
const loading = ref(false);
const error = ref(null);

// Libellé d'un semis : « TC<X> — Nom » de la graine si un lot est rattaché, sinon type.
function semisLabel(s) {
    const g = s.graineLot?.graine;
    if (g) return `${g.code} — ${g.name}`;
    const t = s.graineType;
    return t ? `${t.code} — ${t.name}` : '';
}

const readonly = computed(() => saison.value?.statut === 'cloturee');

function zoomIn() { scale.value = Math.min(2, +(scale.value + 0.15).toFixed(2)); }
function zoomOut() { scale.value = Math.max(0.2, +(scale.value - 0.15).toFixed(2)); }

const extent = computed(() => {
    let w = 200;
    let h = 200;
    for (const b of bacSaisons.value) {
        if (b.posX + b.largeur > w) w = b.posX + b.largeur;
        if (b.posY + b.longueur > h) h = b.posY + b.longueur;
    }
    return { w: w + 40, h: h + 40 };
});

function bacStyle(b) {
    return {
        left: `${b.posX * scale.value}px`,
        top: `${b.posY * scale.value}px`,
        width: `${b.largeur * scale.value}px`,
        height: `${b.longueur * scale.value}px`,
    };
}

function culturesOf(bacId) {
    return cultures.value.filter((c) => c.bacSaison?.id === bacId && c.statut === 'en_place');
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [bsRes, cRes, sRes] = await Promise.all([
            http.get('/bac-saisons'),
            http.get('/cultures'),
            http.get('/semis', { params: { statut: 'leve' } }),
        ]);
        bacSaisons.value = bsRes.data.items;
        cultures.value = cRes.data.items;
        semisLeves.value = sRes.data.items;
        saison.value = bsRes.data.saison ?? cRes.data.saison;
    } finally {
        loading.value = false;
    }
}

// ---- Popup de plantation ----
const modal = reactive({
    open: false,
    bac: null,
    name: '',
    posX: 0,
    posY: 0,
    semis: null,
    graineType: null,
    datePlantation: new Date().toISOString().slice(0, 10),
    dateRecolteTheorique: '',
    perenne: false,
    saving: false,
    error: null,
});

// Sélection d'un semis : préremplit le nom et le type de graine.
function onSemisChange() {
    const s = semisLeves.value.find((x) => x.id === modal.semis);
    if (s) {
        modal.name = semisLabel(s);
        modal.graineType = s.graineType?.id ?? null;
    }
}

const modalOccupied = computed(() => {
    if (!modal.bac) return new Set();
    const set = new Set();
    for (const c of culturesOf(modal.bac.id)) {
        for (let dx = 0; dx < c.largeurCases; dx++) {
            for (let dy = 0; dy < c.hauteurCases; dy++) {
                set.add(`${c.posX + dx},${c.posY + dy}`);
            }
        }
    }
    return set;
});

function cellState(x, y) {
    if (x === modal.posX && y === modal.posY) return 'selected';
    return modalOccupied.value.has(`${x},${y}`) ? 'occupied' : 'free';
}

function pickCell(x, y) {
    if (cellState(x, y) === 'occupied') return;
    modal.posX = x;
    modal.posY = y;
}

function openPlant(bac) {
    if (readonly.value) return;
    modal.open = true;
    modal.bac = bac;
    modal.name = '';
    modal.posX = 0;
    modal.posY = 0;
    modal.semis = null;
    modal.graineType = null;
    modal.datePlantation = new Date().toISOString().slice(0, 10);
    modal.dateRecolteTheorique = '';
    modal.perenne = false;
    modal.error = null;
}

function closePlant() {
    modal.open = false;
    modal.bac = null;
}

async function submitPlant() {
    if (!modal.bac || !modal.name || !modal.datePlantation) return;
    modal.saving = true;
    modal.error = null;
    try {
        await http.post('/cultures', {
            bacSaison: modal.bac.id,
            name: modal.name,
            posX: modal.posX,
            posY: modal.posY,
            largeurCases: 1,
            hauteurCases: 1,
            datePlantation: modal.datePlantation,
            dateRecolteTheorique: modal.dateRecolteTheorique || null,
            statut: 'en_place',
            perenne: modal.perenne,
            semis: modal.semis || null,
            graineType: modal.graineType || null,
        });
        closePlant();
        await load();
    } catch (e) {
        if (e.response?.status === 422) {
            modal.error = e.response.data?.errors?.placement ?? 'Placement invalide (case occupée ou hors grille).';
        } else if (e.response?.status === 409) {
            modal.error = e.response.data?.message ?? 'Saison clôturée.';
        } else {
            modal.error = 'Plantation impossible.';
        }
    } finally {
        modal.saving = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">
            <i class="bi bi-map"></i> Plan du potager
            <span v-if="saison" class="badge ms-2" :class="readonly ? 'text-bg-secondary' : 'text-bg-success'">
                {{ saison.name }}{{ readonly ? ' (clôturée)' : '' }}
            </span>
        </h1>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-if="!saison && !loading" class="alert alert-warning">Sélectionnez une saison dans la barre de navigation.</div>
    <p v-else-if="readonly" class="text-muted small">Saison clôturée : consultation seule.</p>
    <p v-else class="text-muted small">Cliquez un bac pour y planter. Le découpage et la position des bacs se règlent dans Paramétrage › Plan (édition).</p>

    <div v-if="saison" class="btn-group btn-group-sm mb-2" role="group">
        <button type="button" class="btn btn-outline-secondary" @click="zoomOut"><i class="bi bi-zoom-out"></i></button>
        <span class="btn btn-outline-secondary disabled">{{ Math.round(scale / BASE_SCALE * 100) }} %</span>
        <button type="button" class="btn btn-outline-secondary" @click="zoomIn"><i class="bi bi-zoom-in"></i></button>
    </div>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="saison" class="plan-canvas" :style="{ width: `${extent.w * scale}px`, height: `${extent.h * scale}px` }">
        <div
            v-for="b in bacSaisons"
            :key="b.id"
            class="plan-bac"
            :class="{ clickable: !readonly }"
            :style="bacStyle(b)"
            :title="`${b.bac?.name} — cliquer pour planter`"
            @click="openPlant(b)"
        >
            <div class="plan-bac-name">{{ b.bac?.name }}</div>
            <div
                class="plan-bac-grid"
                :style="{
                    gridTemplateColumns: `repeat(${b.colonnes}, 1fr)`,
                    gridTemplateRows: `repeat(${b.lignes}, 1fr)`,
                }"
            >
                <div
                    v-for="c in culturesOf(b.id)"
                    :key="c.id"
                    class="plan-bac-plant"
                    :title="c.name"
                    :style="{
                        gridColumn: `${c.posX + 1} / span ${c.largeurCases}`,
                        gridRow: `${c.posY + 1} / span ${c.hauteurCases}`,
                    }"
                >{{ c.name }}</div>
            </div>
        </div>
        <p v-if="bacSaisons.length === 0" class="text-muted p-3">Aucun bac dans cette saison.</p>
    </div>

    <!-- Popup de plantation -->
    <div v-if="modal.open" class="plan-modal-backdrop" @click.self="closePlant">
        <div class="card shadow plan-modal" role="dialog" aria-modal="true">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-flower1"></i> Planter dans {{ modal.bac?.bac?.name }}</span>
                <button type="button" class="btn-close" aria-label="Fermer" @click="closePlant"></button>
            </div>
            <div class="card-body">
                <div v-if="modal.error" class="alert alert-danger py-1 px-2 small">{{ modal.error }}</div>
                <form @submit.prevent="submitPlant">
                    <div class="mb-3">
                        <label class="form-label">Issu d'un semis (optionnel)</label>
                        <select v-model="modal.semis" class="form-select" @change="onSemisChange">
                            <option :value="null">— Ajout direct —</option>
                            <option v-for="s in semisLeves" :key="s.id" :value="s.id">
                                {{ semisLabel(s) }} — semé {{ s.dateSemis }}
                            </option>
                        </select>
                        <div class="form-text">Le semis choisi passera « planté ».</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom *</label>
                        <input v-model="modal.name" class="form-control" required autofocus placeholder="ex. Tomate cerise">
                    </div>

                    <label class="form-label">Case (cliquez une case libre)</label>
                    <div
                        class="pick-grid mb-1"
                        :style="{ gridTemplateColumns: `repeat(${modal.bac?.colonnes}, 28px)` }"
                    >
                        <template v-for="y in modal.bac?.lignes" :key="y">
                            <button
                                v-for="x in modal.bac?.colonnes"
                                :key="`${x}-${y}`"
                                type="button"
                                class="pick-cell"
                                :class="cellState(x - 1, y - 1)"
                                @click="pickCell(x - 1, y - 1)"
                            ></button>
                        </template>
                    </div>
                    <p class="small text-muted">Position : ({{ modal.posX }}, {{ modal.posY }})</p>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Plantation *</label>
                            <input v-model="modal.datePlantation" type="date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Récolte théorique</label>
                            <input v-model="modal.dateRecolteTheorique" type="date" class="form-control">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input id="modalPerenne" v-model="modal.perenne" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="modalPerenne">Pérenne (reportée chaque saison)</label>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-outline-secondary" @click="closePlant">Annuler</button>
                        <button type="submit" class="btn btn-primary" :disabled="modal.saving || !modal.name || !modal.datePlantation">
                            {{ modal.saving ? 'Plantation…' : 'Planter' }}
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
.plan-bac {
    position: absolute;
    background: #e8dcc0;
    border: 2px solid #8a6d3b;
    border-radius: 4px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.plan-bac.clickable {
    cursor: pointer;
}
.plan-bac.clickable:hover {
    outline: 2px solid var(--bs-success, #198754);
    outline-offset: -2px;
}
.plan-bac-name {
    font-size: 0.7rem;
    font-weight: 600;
    color: #3b2f1b;
    padding: 1px 3px;
    background: rgba(255, 255, 255, 0.5);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.plan-bac-grid {
    flex: 1;
    display: grid;
    gap: 1px;
    padding: 1px;
    min-height: 0;
}
.plan-bac-plant {
    background: var(--bs-success, #198754);
    color: #fff;
    border-radius: 2px;
    font-size: 0.6rem;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    text-align: center;
    padding: 1px;
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
.pick-grid {
    display: grid;
    gap: 2px;
    width: max-content;
}
.pick-cell {
    width: 28px;
    height: 28px;
    border: 1px solid var(--bs-border-color, #ced4da);
    background: #fff;
    border-radius: 3px;
    padding: 0;
    cursor: pointer;
}
.pick-cell.free:hover { background: #d1e7dd; }
.pick-cell.occupied { background: #adb5bd; cursor: not-allowed; }
.pick-cell.selected { background: var(--bs-success, #198754); border-color: var(--bs-success, #198754); }
</style>
