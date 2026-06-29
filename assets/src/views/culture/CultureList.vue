<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import http from '../../api/http';

const items = ref([]);
const currentSaison = ref(null);
const loading = ref(false);
const error = ref(null);

const filterBac = ref('');
const filterStatut = ref('');

// Modale de saisie d'une récolte.
const recolteModal = reactive({
    open: false,
    culture: null,
    date: new Date().toISOString().slice(0, 10),
    quantite: null,
    unite: 'pieces',
    notes: '',
    marquerRecolte: false,
    saving: false,
    error: null,
});

function openRecolte(culture) {
    recolteModal.open = true;
    recolteModal.culture = culture;
    recolteModal.date = new Date().toISOString().slice(0, 10);
    recolteModal.quantite = null;
    recolteModal.unite = 'pieces';
    recolteModal.notes = '';
    recolteModal.marquerRecolte = false;
    recolteModal.error = null;
}

function closeRecolte() {
    recolteModal.open = false;
    recolteModal.culture = null;
}

async function submitRecolte() {
    const c = recolteModal.culture;
    if (!c || !recolteModal.date) return;
    recolteModal.saving = true;
    recolteModal.error = null;
    try {
        await http.post(`/cultures/${c.id}/recoltes`, {
            date: recolteModal.date,
            quantite: recolteModal.quantite,
            unite: recolteModal.unite,
            notes: recolteModal.notes || null,
        });
        if (recolteModal.marquerRecolte) {
            await http.put(`/cultures/${c.id}`, {
                bacSaison: c.bacSaison?.id,
                name: c.name,
                posX: c.posX,
                posY: c.posY,
                largeurCases: c.largeurCases,
                hauteurCases: c.hauteurCases,
                datePlantation: c.datePlantation,
                dateRecolteTheorique: c.dateRecolteTheorique || null,
                dateFin: c.dateFin || null,
                statut: 'recolte',
                perenne: c.perenne,
                graineType: c.graineType?.id ?? null,
                semis: c.semis?.id ?? null,
            });
        }
        closeRecolte();
        await load();
    } catch (e) {
        recolteModal.error = e.response?.data?.message ?? 'Enregistrement impossible.';
    } finally {
        recolteModal.saving = false;
    }
}

const saisonClosed = computed(() => currentSaison.value?.statut === 'cloturee');

const STATUT_LABELS = {
    en_place: 'En place',
    recolte: 'Récolté',
    mort: 'Mort',
};

// Bacs distincts présents dans la liste (pour le filtre).
const bacs = computed(() => {
    const map = new Map();
    for (const c of items.value) {
        const b = c.bacSaison?.bac;
        if (b && !map.has(b.id)) map.set(b.id, b);
    }
    return [...map.values()];
});

const filtered = computed(() => items.value.filter((c) => {
    if (filterBac.value !== '' && c.bacSaison?.bac?.id !== Number(filterBac.value)) return false;
    if (filterStatut.value !== '' && c.statut !== filterStatut.value) return false;
    return true;
}));

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const params = {};
        if (filterStatut.value !== '') params.statut = filterStatut.value;
        const { data } = await http.get('/cultures', { params });
        items.value = data.items;
        currentSaison.value = data.saison;
    } finally {
        loading.value = false;
    }
}

async function setStatut(culture, statut) {
    try {
        await http.put(`/cultures/${culture.id}`, {
            bacSaison: culture.bacSaison?.id,
            name: culture.name,
            posX: culture.posX,
            posY: culture.posY,
            largeurCases: culture.largeurCases,
            hauteurCases: culture.hauteurCases,
            datePlantation: culture.datePlantation,
            dateRecolteTheorique: culture.dateRecolteTheorique || null,
            dateFin: culture.dateFin || null,
            statut,
            perenne: culture.perenne,
            graineType: culture.graineType?.id ?? null,
            semis: culture.semis?.id ?? null,
        });
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Mise à jour impossible.';
    }
}

async function markDead(culture) {
    if (!window.confirm(`Marquer la culture « ${culture.name} » comme morte ?`)) return;
    await setStatut(culture, 'mort');
}

async function remove(culture) {
    if (!window.confirm(`Supprimer la culture « ${culture.name} » ?`)) return;
    try {
        await http.delete(`/cultures/${culture.id}`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

function statutBadge(statut) {
    return { en_place: 'text-bg-success', recolte: 'text-bg-secondary', mort: 'text-bg-dark' }[statut] ?? 'text-bg-light';
}

onMounted(load);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">
            <i class="bi bi-tree"></i> Cultures
            <span v-if="currentSaison" class="badge ms-2" :class="saisonClosed ? 'text-bg-secondary' : 'text-bg-success'">
                {{ currentSaison.name }}{{ saisonClosed ? ' (clôturée)' : '' }}
            </span>
        </h1>
        <router-link v-if="!saisonClosed && currentSaison" class="btn btn-primary" :to="{ name: 'culture-new' }">
            <i class="bi bi-plus-lg"></i> Nouvelle culture
        </router-link>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-if="!currentSaison && !loading" class="alert alert-warning">Sélectionnez une saison dans la barre de navigation.</div>
    <p v-else-if="saisonClosed" class="text-muted small">Saison clôturée : cultures en lecture seule.</p>

    <div v-if="currentSaison" class="row g-2 mb-3" style="max-width: 520px">
        <div class="col">
            <select v-model="filterBac" class="form-select form-select-sm">
                <option value="">Tous les bacs</option>
                <option v-for="b in bacs" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
        </div>
        <div class="col">
            <select v-model="filterStatut" class="form-select form-select-sm" @change="load">
                <option value="">Tous les statuts</option>
                <option value="en_place">En place</option>
                <option value="recolte">Récolté</option>
                <option value="mort">Mort</option>
            </select>
        </div>
    </div>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <table v-else-if="currentSaison" class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Bac</th>
                <th>Plantation</th>
                <th>Statut</th>
                <th></th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="c in filtered" :key="c.id">
                <td>{{ c.name }}</td>
                <td>{{ c.bacSaison?.bac?.name ?? '—' }}</td>
                <td>{{ c.datePlantation }}</td>
                <td><span class="badge" :class="statutBadge(c.statut)">{{ STATUT_LABELS[c.statut] }}</span></td>
                <td><span v-if="c.perenne" class="badge text-bg-info">Pérenne</span></td>
                <td class="text-end">
                    <template v-if="!saisonClosed">
                        <button v-if="c.statut === 'en_place'" class="btn btn-sm btn-outline-success" @click="openRecolte(c)" title="Saisir une récolte">
                            <i class="bi bi-basket"></i>
                        </button>
                        <button v-if="c.statut === 'en_place'" class="btn btn-sm btn-outline-dark" @click="markDead(c)" title="Marquer mort">
                            <i class="bi bi-x-circle"></i>
                        </button>
                        <router-link class="btn btn-sm btn-outline-primary" :to="{ name: 'culture-edit', params: { id: c.id } }">Éditer</router-link>
                        <button class="btn btn-sm btn-outline-danger" @click="remove(c)">Supprimer</button>
                    </template>
                    <span v-else class="text-muted small">—</span>
                </td>
            </tr>
            <tr v-if="filtered.length === 0">
                <td colspan="6" class="text-muted">Aucune culture.</td>
            </tr>
        </tbody>
    </table>

    <!-- Modale de saisie d'une récolte -->
    <div v-if="recolteModal.open" class="culture-modal-backdrop" @click.self="closeRecolte">
        <div class="card shadow culture-modal" role="dialog" aria-modal="true">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-basket"></i> Récolte · {{ recolteModal.culture?.name }}</span>
                <button type="button" class="btn-close" aria-label="Fermer" @click="closeRecolte"></button>
            </div>
            <div class="card-body">
                <div v-if="recolteModal.error" class="alert alert-danger">{{ recolteModal.error }}</div>
                <form @submit.prevent="submitRecolte">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Date *</label>
                            <input v-model="recolteModal.date" type="date" class="form-control" required>
                        </div>
                        <div class="col-7">
                            <label class="form-label">Quantité</label>
                            <input v-model.number="recolteModal.quantite" type="number" step="any" min="0" class="form-control">
                        </div>
                        <div class="col-5">
                            <label class="form-label">Unité</label>
                            <select v-model="recolteModal.unite" class="form-select">
                                <option value="pieces">pièces</option>
                                <option value="g">g</option>
                                <option value="kg">kg</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <input v-model="recolteModal.notes" class="form-control" placeholder="ex. première cueillette">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input id="marquerRecolte" v-model="recolteModal.marquerRecolte" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="marquerRecolte">
                                    Marquer la culture « récolté » (statut final)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-outline-secondary" @click="closeRecolte">Annuler</button>
                        <button type="submit" class="btn btn-primary" :disabled="recolteModal.saving || !recolteModal.date">
                            {{ recolteModal.saving ? 'Enregistrement…' : 'Enregistrer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.culture-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
}
.culture-modal {
    width: 100%;
    max-width: 420px;
}
</style>
