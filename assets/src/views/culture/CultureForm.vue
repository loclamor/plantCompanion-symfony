<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../../api/http';
import { useSeasonStore } from '../../stores/season';

const route = useRoute();
const router = useRouter();
const seasons = useSeasonStore();

const id = computed(() => route.params.id ?? null);
const isEdit = computed(() => id.value != null);

const form = reactive({
    bacSaison: null,
    name: '',
    posX: 0,
    posY: 0,
    largeurCases: 1,
    hauteurCases: 1,
    datePlantation: new Date().toISOString().slice(0, 10),
    dateRecolteTheorique: '',
    dateFin: '',
    statut: 'en_place',
    perenne: false,
    graineType: null,
    semis: null,
});

const bacSaisons = ref([]);
const graineTypes = ref([]);
const semisLeves = ref([]);
const recoltes = ref([]);
const newRecolte = reactive({ date: new Date().toISOString().slice(0, 10), quantite: null, unite: 'pieces', notes: '' });
const recolteError = ref(null);
// Cultures « en_place » du bac sélectionné (pour griser les cases occupées).
const occupied = ref([]);
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

const selectedBac = computed(() => bacSaisons.value.find((b) => b.id === form.bacSaison) ?? null);
const lignes = computed(() => selectedBac.value?.lignes ?? 0);
const colonnes = computed(() => selectedBac.value?.colonnes ?? 0);

// Ensemble des cases occupées par d'autres cultures (clé "x,y").
const occupiedCells = computed(() => {
    const set = new Set();
    for (const c of occupied.value) {
        if (isEdit.value && c.id === Number(id.value)) continue;
        for (let dx = 0; dx < c.largeurCases; dx++) {
            for (let dy = 0; dy < c.hauteurCases; dy++) {
                set.add(`${c.posX + dx},${c.posY + dy}`);
            }
        }
    }
    return set;
});

function cellState(x, y) {
    // Case couverte par l'emprise courante ?
    const inSelection = x >= form.posX && x < form.posX + form.largeurCases
        && y >= form.posY && y < form.posY + form.hauteurCases;
    if (inSelection) return 'selected';
    return occupiedCells.value.has(`${x},${y}`) ? 'occupied' : 'free';
}

function pickCell(x, y) {
    if (cellState(x, y) === 'occupied') return;
    form.posX = x;
    form.posY = y;
}

async function loadOccupied() {
    if (!form.bacSaison) {
        occupied.value = [];
        return;
    }
    const { data } = await http.get('/cultures', { params: { bacSaison: form.bacSaison } });
    occupied.value = data.items.filter((c) => c.statut === 'en_place');
}

watch(() => form.bacSaison, async () => {
    form.posX = 0;
    form.posY = 0;
    await loadOccupied();
});

// Libellé d'un semis : code « TC<X> — Nom » de la graine concrète si un lot est
// rattaché, sinon repli sur le type de graine (« TC — Nom du type »).
function semisLabel(s) {
    const g = s.graineLot?.graine;
    if (g) return `${g.code} — ${g.name}`;
    const t = s.graineType;
    return t ? `${t.code} — ${t.name}` : '';
}

// Pré-remplir le nom + type depuis le semis choisi comme origine.
watch(() => form.semis, () => {
    const s = semisLeves.value.find((x) => x.id === form.semis);
    if (s) {
        form.name = semisLabel(s);
        form.graineType = s.graineType?.id ?? form.graineType;
    }
});

async function loadRefs() {
    const [bsRes, gtRes, semisRes] = await Promise.all([
        http.get('/bac-saisons'),
        http.get('/graine-types'),
        http.get('/semis', { params: { statut: 'leve' } }),
    ]);
    bacSaisons.value = bsRes.data.items;
    graineTypes.value = gtRes.data.items;
    semisLeves.value = semisRes.data.items;
}

async function loadEntity() {
    const { data } = await http.get(`/cultures/${id.value}`);
    form.bacSaison = data.bacSaison?.id ?? null;
    await loadOccupied();
    form.name = data.name ?? '';
    form.posX = data.posX ?? 0;
    form.posY = data.posY ?? 0;
    form.largeurCases = data.largeurCases ?? 1;
    form.hauteurCases = data.hauteurCases ?? 1;
    form.datePlantation = data.datePlantation ?? '';
    form.dateRecolteTheorique = data.dateRecolteTheorique ?? '';
    form.dateFin = data.dateFin ?? '';
    form.statut = data.statut ?? 'en_place';
    form.perenne = data.perenne ?? false;
    form.graineType = data.graineType?.id ?? null;
    form.semis = data.semis?.id ?? null;
    recoltes.value = data.recoltes ?? [];
}

async function addRecolte() {
    recolteError.value = null;
    if (!newRecolte.date) return;
    try {
        const { data } = await http.post(`/cultures/${id.value}/recoltes`, {
            date: newRecolte.date,
            quantite: newRecolte.quantite,
            unite: newRecolte.unite,
            notes: newRecolte.notes || null,
        });
        recoltes.value = data.recoltes ?? [];
        newRecolte.quantite = null;
        newRecolte.notes = '';
    } catch (e) {
        recolteError.value = e.response?.data?.message ?? 'Ajout impossible.';
    }
}

async function deleteRecolte(r) {
    recolteError.value = null;
    try {
        const { data } = await http.delete(`/cultures/${id.value}/recoltes/${r.id}`);
        recoltes.value = data.recoltes ?? [];
    } catch (e) {
        recolteError.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = {
            bacSaison: form.bacSaison,
            name: form.name,
            posX: form.posX,
            posY: form.posY,
            largeurCases: form.largeurCases,
            hauteurCases: form.hauteurCases,
            datePlantation: form.datePlantation || null,
            dateRecolteTheorique: form.dateRecolteTheorique || null,
            dateFin: form.dateFin || null,
            statut: form.statut,
            perenne: form.perenne,
            graineType: form.graineType || null,
            semis: form.semis || null,
        };
        if (isEdit.value) {
            await http.put(`/cultures/${id.value}`, payload);
        } else {
            await http.post('/cultures', payload);
        }
        router.push({ name: 'culture-index' });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else if (e.response?.status === 409) {
            errors.value = { _global: e.response.data.message ?? 'Saison clôturée (lecture seule).' };
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
        await loadRefs();
        if (isEdit.value) await loadEntity();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <h1 class="mb-3">{{ isEdit ? 'Éditer — culture' : 'Nouvelle culture' }}</h1>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <form v-else @submit.prevent="submit" style="max-width: 720px">
        <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>
        <div v-if="errors.placement" class="alert alert-danger">{{ errors.placement }}</div>
        <div v-if="!seasons.currentId" class="alert alert-warning">Sélectionnez une saison dans la barre de navigation.</div>

        <div class="card mb-4 shadow-sm">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Bac *</label>
                    <select v-model="form.bacSaison" class="form-select" :class="{ 'is-invalid': errors.bacSaison }" required>
                        <option :value="null" disabled>— Choisir —</option>
                        <option v-for="b in bacSaisons" :key="b.id" :value="b.id">
                            {{ b.bac?.name }} ({{ b.lignes }}×{{ b.colonnes }})
                        </option>
                    </select>
                    <div v-if="errors.bacSaison" class="invalid-feedback">{{ errors.bacSaison }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nom *</label>
                    <input v-model="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" required>
                    <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Issu d'un semis (optionnel)</label>
                    <select v-model="form.semis" class="form-select">
                        <option :value="null">— Ajout direct —</option>
                        <option v-for="s in semisLeves" :key="s.id" :value="s.id">
                            {{ semisLabel(s) }} — semé {{ s.dateSemis }}
                        </option>
                    </select>
                    <div class="form-text">Le semis choisi passera « planté ».</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Type de graine (optionnel)</label>
                    <select v-model="form.graineType" class="form-select">
                        <option :value="null">— Aucun —</option>
                        <option v-for="t in graineTypes" :key="t.id" :value="t.id">{{ t.name }} ({{ t.code }})</option>
                    </select>
                </div>
            </div>
        </div>

        <div v-if="selectedBac" class="card mb-4 shadow-sm">
            <div class="card-header">Placement dans le bac</div>
            <div class="card-body">
                <div class="row g-3 mb-3" style="max-width: 280px">
                    <div class="col">
                        <label class="form-label">Largeur (cases)</label>
                        <input v-model.number="form.largeurCases" type="number" min="1" :max="colonnes" class="form-control form-control-sm">
                    </div>
                    <div class="col">
                        <label class="form-label">Hauteur (cases)</label>
                        <input v-model.number="form.hauteurCases" type="number" min="1" :max="lignes" class="form-control form-control-sm">
                    </div>
                </div>

                <p class="text-muted small mb-2">Cliquez une case libre pour poser le coin haut-gauche du plant.</p>
                <div class="culture-grid" :style="{ gridTemplateColumns: `repeat(${colonnes}, 32px)` }">
                    <template v-for="y in lignes" :key="y">
                        <button
                            v-for="x in colonnes"
                            :key="`${x}-${y}`"
                            type="button"
                            class="culture-cell"
                            :class="cellState(x - 1, y - 1)"
                            :title="`(${x - 1}, ${y - 1})`"
                            @click="pickCell(x - 1, y - 1)"
                        ></button>
                    </template>
                </div>
                <p class="small mt-2">Position : ({{ form.posX }}, {{ form.posY }})</p>
            </div>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-header">Suivi</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Date de plantation *</label>
                    <input v-model="form.datePlantation" type="date" class="form-control" :class="{ 'is-invalid': errors.datePlantation }" required>
                    <div v-if="errors.datePlantation" class="invalid-feedback">{{ errors.datePlantation }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Récolte théorique</label>
                    <input v-model="form.dateRecolteTheorique" type="date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de fin</label>
                    <input v-model="form.dateFin" type="date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Statut</label>
                    <select v-model="form.statut" class="form-select">
                        <option value="en_place">En place</option>
                        <option value="recolte">Récolté</option>
                        <option value="mort">Mort</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input v-model="form.perenne" class="form-check-input" type="checkbox" id="perenne">
                        <label class="form-check-label" for="perenne">Pérenne (reportée chaque saison)</label>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isEdit" class="card mb-4 shadow-sm">
            <div class="card-header">Récoltes</div>
            <div class="card-body">
                <div v-if="recolteError" class="alert alert-danger">{{ recolteError }}</div>
                <table v-if="recoltes.length" class="table table-sm align-middle">
                    <thead>
                        <tr><th style="width: 150px">Date</th><th>Quantité</th><th>Notes</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in recoltes" :key="r.id">
                            <td>{{ r.date }}</td>
                            <td>{{ r.quantite != null ? `${r.quantite} ${r.unite}` : '—' }}</td>
                            <td>{{ r.notes ?? '—' }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="deleteRecolte(r)"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-muted">Aucune récolte.</p>

                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label">Date</label>
                        <input v-model="newRecolte.date" type="date" class="form-control">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Quantité</label>
                        <input v-model.number="newRecolte.quantite" type="number" step="any" min="0" class="form-control" style="max-width: 110px">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Unité</label>
                        <select v-model="newRecolte.unite" class="form-select">
                            <option value="pieces">pièces</option>
                            <option value="g">g</option>
                            <option value="kg">kg</option>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label">Notes</label>
                        <input v-model="newRecolte.notes" class="form-control" placeholder="ex. première cueillette">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-primary" :disabled="!newRecolte.date" @click="addRecolte"><i class="bi bi-plus-lg"></i> Ajouter</button>
                    </div>
                </div>
                <p class="form-text mt-2">La culture reste « en place » ; passez-la « récolté » manuellement une fois terminée.</p>
            </div>
        </div>

        <button class="btn btn-primary" :disabled="saving || !seasons.currentId">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
        <router-link class="btn btn-outline-secondary" :to="{ name: 'culture-index' }">Annuler</router-link>
    </form>
</template>

<style scoped>
.culture-grid {
    display: grid;
    gap: 2px;
    width: max-content;
}
.culture-cell {
    width: 32px;
    height: 32px;
    border: 1px solid var(--bs-border-color, #ced4da);
    background: #fff;
    border-radius: 3px;
    padding: 0;
    cursor: pointer;
}
.culture-cell.free:hover {
    background: #d1e7dd;
}
.culture-cell.occupied {
    background: #adb5bd;
    cursor: not-allowed;
}
.culture-cell.selected {
    background: var(--bs-success, #198754);
    border-color: var(--bs-success, #198754);
}
</style>
