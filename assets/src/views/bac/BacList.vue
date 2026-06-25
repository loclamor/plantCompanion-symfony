<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import http from '../../api/http';
import { useSeasonStore } from '../../stores/season';

const seasons = useSeasonStore();

const bacs = ref([]);
const snapshots = ref([]);
const currentSaison = ref(null);
const loading = ref(false);
const error = ref(null);
// État d'édition inline du découpage par BacSaison id : { [id]: { lignes, colonnes } }.
const editing = reactive({});

const saisonClosed = computed(() => currentSaison.value?.statut === 'cloturee');
const saisonActive = computed(() => currentSaison.value != null && !saisonClosed.value);
// Ids des bacs déjà présents dans la saison courante.
const bacIdsInSeason = computed(() => new Set(snapshots.value.map((s) => s.bac?.id)));

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [bacsRes, snapRes] = await Promise.all([
            http.get('/bacs'),
            http.get('/bac-saisons'),
        ]);
        bacs.value = bacsRes.data.items;
        snapshots.value = snapRes.data.items;
        currentSaison.value = snapRes.data.saison;
    } finally {
        loading.value = false;
    }
}

async function toggleArchive(bac) {
    try {
        await http.put(`/bacs/${bac.id}/archiver`, { archived: !bac.archived });
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Opération impossible.';
    }
}

async function remove(bac) {
    if (!window.confirm(`Supprimer le bac « ${bac.name} » ?`)) return;
    try {
        await http.delete(`/bacs/${bac.id}`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

async function addToSeason(bac) {
    try {
        await http.post('/bac-saisons', { bac: bac.id });
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Ajout à la saison impossible.';
    }
}

function startEdit(snap) {
    editing[snap.id] = { lignes: snap.lignes, colonnes: snap.colonnes };
}

function cancelEdit(id) {
    delete editing[id];
}

async function saveDecoupage(snap) {
    const draft = editing[snap.id];
    try {
        await http.put(`/bac-saisons/${snap.id}`, { lignes: draft.lignes, colonnes: draft.colonnes });
        delete editing[snap.id];
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Modification du découpage impossible.';
    }
}

onMounted(load);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="bi bi-grid-3x3"></i> Bacs</h1>
        <router-link class="btn btn-primary" :to="{ name: 'bac-new' }">
            <i class="bi bi-plus-lg"></i> Nouveau bac
        </router-link>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <template v-else>
        <h2 class="h5 text-muted">Définition des bacs</h2>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Taille défaut</th>
                    <th>Découpage défaut</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="bac in bacs" :key="bac.id" :class="{ 'text-muted': bac.archived }">
                    <td>{{ bac.name }}</td>
                    <td>{{ bac.largeurDefaut }} × {{ bac.longueurDefaut }} cm</td>
                    <td>{{ bac.lignesDefaut }} × {{ bac.colonnesDefaut }} cases</td>
                    <td>
                        <span v-if="bac.archived" class="badge text-bg-secondary">Archivé</span>
                        <span v-else class="badge text-bg-success">Actif</span>
                    </td>
                    <td class="text-end">
                        <button
                            v-if="saisonActive && !bac.archived && !bacIdsInSeason.has(bac.id)"
                            class="btn btn-sm btn-success" @click="addToSeason(bac)">
                            <i class="bi bi-plus-lg"></i> Ajouter à la saison
                        </button>
                        <span v-else-if="saisonActive && !bac.archived" class="badge text-bg-light text-success me-1">
                            <i class="bi bi-check-lg"></i> Dans la saison
                        </span>
                        <router-link class="btn btn-sm btn-outline-primary" :to="{ name: 'bac-edit', params: { id: bac.id } }">Éditer</router-link>
                        <button class="btn btn-sm btn-outline-warning" @click="toggleArchive(bac)">
                            {{ bac.archived ? 'Désarchiver' : 'Archiver' }}
                        </button>
                        <button class="btn btn-sm btn-outline-danger" @click="remove(bac)">Supprimer</button>
                    </td>
                </tr>
                <tr v-if="bacs.length === 0">
                    <td colspan="5" class="text-muted">Aucun bac.</td>
                </tr>
            </tbody>
        </table>

        <h2 class="h5 text-muted mt-4">
            Découpage — saison courante
            <span v-if="currentSaison" class="badge" :class="saisonClosed ? 'text-bg-secondary' : 'text-bg-success'">
                {{ currentSaison.name }}{{ saisonClosed ? ' (clôturée)' : '' }}
            </span>
        </h2>
        <p v-if="!currentSaison" class="text-muted">Aucune saison sélectionnée.</p>
        <p v-else-if="saisonClosed" class="text-muted small">Saison clôturée : découpage en lecture seule.</p>

        <table v-if="currentSaison" class="table align-middle">
            <thead>
                <tr>
                    <th>Bac</th>
                    <th>Taille physique (figée)</th>
                    <th>Position</th>
                    <th>Découpage (lignes × colonnes)</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="snap in snapshots" :key="snap.id">
                    <td>{{ snap.bac?.name }}</td>
                    <td>{{ snap.largeur }} × {{ snap.longueur }} cm</td>
                    <td>({{ snap.posX }}, {{ snap.posY }})</td>
                    <td>
                        <template v-if="editing[snap.id]">
                            <div class="d-flex gap-1" style="max-width: 200px">
                                <input v-model.number="editing[snap.id].lignes" type="number" min="1" class="form-control form-control-sm">
                                <span class="align-self-center">×</span>
                                <input v-model.number="editing[snap.id].colonnes" type="number" min="1" class="form-control form-control-sm">
                            </div>
                        </template>
                        <template v-else>{{ snap.lignes }} × {{ snap.colonnes }}</template>
                    </td>
                    <td class="text-end">
                        <template v-if="editing[snap.id]">
                            <button class="btn btn-sm btn-success" @click="saveDecoupage(snap)">Enregistrer</button>
                            <button class="btn btn-sm btn-outline-secondary" @click="cancelEdit(snap.id)">Annuler</button>
                        </template>
                        <button v-else class="btn btn-sm btn-outline-primary" :disabled="saisonClosed" @click="startEdit(snap)">
                            Modifier le découpage
                        </button>
                    </td>
                </tr>
                <tr v-if="snapshots.length === 0">
                    <td colspan="5" class="text-muted">Aucun bac dans cette saison.</td>
                </tr>
            </tbody>
        </table>
    </template>
</template>
