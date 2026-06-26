<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../../api/http';
import { useSeasonStore } from '../../stores/season';

const route = useRoute();
const router = useRouter();
const seasons = useSeasonStore();

const SEMIS_STATUT = {
    seme: { label: 'Semé', color: 'secondary' },
    leve: { label: 'Levé', color: 'success' },
    plante: { label: 'Planté', color: 'primary' },
    echec: { label: 'Échec', color: 'danger' },
};

const items = ref([]);
const graineTypes = ref([]);
const loading = ref(false);
const error = ref(null);
const expanded = ref(new Set());

const filters = reactive({ statut: '', graineType: '' });

function syncFiltersFromQuery() {
    filters.statut = typeof route.query.statut === 'string' ? route.query.statut : '';
    filters.graineType = route.query.graineType != null ? String(route.query.graineType) : '';
}

async function loadGraineTypes() {
    const { data } = await http.get('/graine-types');
    graineTypes.value = data.items;
}

async function load() {
    if (!seasons.currentId) {
        items.value = [];
        return;
    }
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/semis', {
            params: {
                saison: seasons.currentId,
                statut: filters.statut || undefined,
                graineType: filters.graineType || undefined,
            },
        });
        items.value = data.items;
    } finally {
        loading.value = false;
    }
}

function buildQuery() {
    const q = {};
    if (filters.statut) q.statut = filters.statut;
    if (filters.graineType) q.graineType = String(filters.graineType);
    return q;
}
function submit() {
    router.push({ name: 'semis-index', query: buildQuery() }).catch(() => {});
}
function reset() {
    filters.statut = '';
    filters.graineType = '';
    submit();
}

// Regroupement par type + date de semis + méthode.
const groups = computed(() => {
    const map = new Map();
    for (const s of items.value) {
        const graine = s.graineLot?.graine ?? null;
        const idKey = graine ? `g${graine.id}` : `t${s.graineType?.id}`;
        const key = `${idKey}|${s.dateSemis}|${s.methode}`;
        if (!map.has(key)) {
            map.set(key, {
                key,
                graine,
                graineType: s.graineType,
                dateSemis: s.dateSemis,
                methode: s.methode,
                semis: [],
                counts: { seme: 0, leve: 0, plante: 0, echec: 0 },
            });
        }
        const g = map.get(key);
        g.semis.push(s);
        g.counts[s.statut] = (g.counts[s.statut] ?? 0) + 1;
    }
    return [...map.values()];
});

// Niveau supérieur : sections par type de graine (toujours visibles), contenant
// les groupes par graine. Décompte agrégé par type.
const typeSections = computed(() => {
    const map = new Map();
    for (const g of groups.value) {
        const tid = g.graineType?.id ?? 'none';
        if (!map.has(tid)) {
            map.set(tid, {
                tid,
                graineType: g.graineType,
                groups: [],
                total: 0,
                counts: { seme: 0, leve: 0, plante: 0, echec: 0 },
            });
        }
        const sec = map.get(tid);
        sec.groups.push(g);
        sec.total += g.semis.length;
        for (const k of Object.keys(sec.counts)) {
            sec.counts[k] += g.counts[k] ?? 0;
        }
    }
    return [...map.values()];
});

function toggle(key) {
    const next = new Set(expanded.value);
    next.has(key) ? next.delete(key) : next.add(key);
    expanded.value = next;
}

function payloadFrom(s) {
    return {
        saison: seasons.currentId,
        graineType: s.graineType?.id,
        graineLot: s.graineLot?.id ?? null,
        methode: s.methode,
        dateSemis: s.dateSemis,
        dateLevee: s.dateLevee,
        datePlantation: s.datePlantation,
        datePlantationTheorique: s.datePlantationTheorique,
        dateRecolteTheorique: s.dateRecolteTheorique,
        echec: s.echec,
        notes: s.notes,
    };
}

const today = () => new Date().toISOString().slice(0, 10);

// Modale de saisie d'une date (remplace window.prompt).
const dateModal = reactive({ open: false, title: '', date: '', action: null });
function askDate(title, defaultDate, action) {
    dateModal.title = title;
    dateModal.date = defaultDate || today();
    dateModal.action = action;
    dateModal.open = true;
}
function closeDateModal() {
    dateModal.open = false;
    dateModal.action = null;
}
async function confirmDateModal() {
    if (!dateModal.date || !dateModal.action) return;
    const action = dateModal.action;
    const date = dateModal.date;
    closeDateModal();
    await action(date);
}

async function patch(s, changes) {
    error.value = null;
    try {
        await http.put(`/semis/${s.id}`, { ...payloadFrom(s), ...changes });
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Action impossible.';
    }
}

function markLeve(s) {
    askDate('Date de levée', s.dateLevee, (d) => patch(s, { dateLevee: d, echec: false }));
}
function markPlante(s) {
    askDate('Date de plantation', s.datePlantation, (d) => patch(s, { datePlantation: d, echec: false }));
}
function markEchec(s) {
    if (window.confirm('Marquer ce semis en échec ?')) patch(s, { echec: true });
}
function rempoter(s) {
    askDate('Date de rempotage', null, async (d) => {
        error.value = null;
        try {
            await http.post(`/semis/${s.id}/rempotages`, { date: d });
            await load();
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Rempotage impossible.';
        }
    });
}
async function removeSemis(s) {
    const msg = s.graineLot
        ? 'Supprimer ce semis ? La graine sera remise dans la grainothèque (lot recrédité de 1).'
        : 'Supprimer ce semis ?';
    if (!window.confirm(msg)) return;
    error.value = null;
    try {
        await http.delete(`/semis/${s.id}`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

loadGraineTypes();
watch(
    () => [route.query, seasons.currentId],
    () => {
        syncFiltersFromQuery();
        load();
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="bi bi-flower3"></i> Semis</h1>
        <div>
            <router-link class="btn btn-outline-primary" :to="{ name: 'semis-batch' }"><i class="bi bi-grid-3x3-gap"></i> Semis multiples</router-link>
            <router-link class="btn btn-primary" :to="{ name: 'semis-new' }"><i class="bi bi-plus-lg"></i> Nouveau semis</router-link>
        </div>
    </div>

    <div v-if="!seasons.currentId" class="alert alert-warning">Sélectionnez une saison dans la barre de navigation pour voir et créer des semis.</div>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-if="seasons.currentId">
        <form class="row g-2 mb-3" @submit.prevent="submit">
            <div class="col-auto">
                <select v-model="filters.statut" class="form-select form-select-sm">
                    <option value="">Tous les statuts</option>
                    <option v-for="(v, k) in SEMIS_STATUT" :key="k" :value="k">{{ v.label }}</option>
                </select>
            </div>
            <div class="col-auto">
                <select v-model="filters.graineType" class="form-select form-select-sm">
                    <option value="">Tous les types</option>
                    <option v-for="t in graineTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary" type="submit">Filtrer</button>
                <button class="btn btn-sm btn-link" type="button" @click="reset">Réinitialiser</button>
            </div>
        </form>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <table v-else class="table align-middle">
            <thead>
                <tr>
                    <th></th>
                    <th>Graine</th>
                    <th>Date de semis</th>
                    <th>Méthode</th>
                    <th>Suivi</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody v-for="sec in typeSections" :key="sec.tid">
                <tr class="table-secondary">
                    <td></td>
                    <td><strong>{{ sec.graineType?.name }}</strong> <span class="badge text-bg-secondary">{{ sec.graineType?.code }}</span></td>
                    <td colspan="3">
                        <span v-for="(n, k) in sec.counts" :key="k">
                            <span v-if="n > 0" class="badge me-1" :class="`text-bg-${SEMIS_STATUT[k].color}`">{{ n }} {{ SEMIS_STATUT[k].label.toLowerCase() }}</span>
                        </span>
                    </td>
                    <td class="text-end fw-bold">{{ sec.total }}</td>
                </tr>
                <template v-for="g in sec.groups" :key="g.key">
                <tr class="table-light" role="button" @click="toggle(g.key)">
                    <td><i :class="expanded.has(g.key) ? 'bi bi-chevron-down' : 'bi bi-chevron-right'"></i></td>
                    <td>
                        <template v-if="g.graine">
                            <span class="badge text-bg-secondary">{{ g.graine.code }}</span> {{ g.graine.name }}
                            <span class="text-muted small">· {{ g.graineType?.name }}</span>
                        </template>
                        <template v-else>
                            {{ g.graineType?.name }} <span class="badge text-bg-light text-dark">sans lot</span>
                        </template>
                    </td>
                    <td>{{ g.dateSemis }}</td>
                    <td>{{ g.methode === 'godet' ? 'Godet' : 'Direct' }}</td>
                    <td>
                        <span v-for="(n, k) in g.counts" :key="k">
                            <span v-if="n > 0" class="badge me-1" :class="`text-bg-${SEMIS_STATUT[k].color}`">{{ n }} {{ SEMIS_STATUT[k].label.toLowerCase() }}</span>
                        </span>
                    </td>
                    <td class="text-end fw-bold">{{ g.semis.length }}</td>
                </tr>
                <template v-if="expanded.has(g.key)">
                <tr v-for="s in g.semis" :key="s.id">
                    <td></td>
                    <td colspan="2">
                        <span class="badge me-2" :class="`text-bg-${SEMIS_STATUT[s.statut].color}`">{{ SEMIS_STATUT[s.statut].label }}</span>
                        <span v-if="s.graineLot" class="text-muted small">lot {{ s.graineLot.graine?.code }}</span>
                    </td>
                    <td colspan="2" class="small text-muted">
                        <span v-if="s.dateLevee">levé {{ s.dateLevee }} · </span>
                        <span v-if="s.rempotages.length">{{ s.rempotages.length }} rempotage(s) · </span>
                        <span v-if="s.datePlantation">planté {{ s.datePlantation }}</span>
                    </td>
                    <td class="text-end text-nowrap">
                        <button v-if="s.statut !== 'plante' && !s.dateLevee" class="btn btn-sm btn-outline-success" title="Marquer levé" @click="markLeve(s)"><i class="bi bi-arrow-up-circle"></i></button>
                        <button v-if="s.statut !== 'plante'" class="btn btn-sm btn-outline-secondary" :disabled="!s.dateLevee" :title="s.dateLevee ? 'Rempoter' : 'Le semis doit être levé'" @click="rempoter(s)"><i class="bi bi-arrow-repeat"></i></button>
                        <button v-if="s.statut !== 'plante'" class="btn btn-sm btn-outline-primary" :disabled="!s.dateLevee" :title="s.dateLevee ? 'Marquer planté' : 'Le semis doit être levé'" @click="markPlante(s)"><i class="bi bi-tree"></i></button>
                        <button v-if="s.statut !== 'plante'" class="btn btn-sm btn-outline-danger" title="Échec" @click="markEchec(s)"><i class="bi bi-x-octagon"></i></button>
                        <router-link class="btn btn-sm btn-outline-dark" :to="{ name: 'semis-edit', params: { id: s.id } }" title="Éditer"><i class="bi bi-pencil"></i></router-link>
                        <button class="btn btn-sm btn-outline-danger" title="Supprimer" @click="removeSemis(s)"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
                </template>
                </template>
            </tbody>
            <tbody v-if="groups.length === 0">
                <tr><td colspan="6" class="text-muted">Aucun semis pour cette saison.</td></tr>
            </tbody>
        </table>
    </template>

    <!-- Modale de saisie d'une date -->
    <template v-if="dateModal.open">
        <div class="modal d-block" tabindex="-1" @click.self="closeDateModal">
            <div class="modal-dialog">
                <form class="modal-content" @submit.prevent="confirmDateModal">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ dateModal.title }}</h5>
                        <button type="button" class="btn-close" @click="closeDateModal"></button>
                    </div>
                    <div class="modal-body">
                        <input v-model="dateModal.date" type="date" class="form-control" required autofocus>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" @click="closeDateModal">Annuler</button>
                        <button type="submit" class="btn btn-primary" :disabled="!dateModal.date">Valider</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    </template>
</template>

