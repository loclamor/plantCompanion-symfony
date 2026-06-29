<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import http from '../../api/http';

const route = useRoute();
const id = computed(() => route.params.id);

const MOIS = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
const METHODES = { pleine_terre: 'Pleine terre', couvert: 'Couvert' };

const graine = ref(null);
const lots = ref([]);
const loading = ref(true);
const error = ref(null);

const stockTotal = computed(() => lots.value.reduce((s, l) => s + l.quantiteRestante, 0));

// Formulaire de lot (ajout / édition inline)
const editingId = ref(null); // null = aucun, 'new' = ajout, sinon id du lot
const lotErrors = ref({});
const lotSaving = ref(false);
const lotForm = reactive({
    source: 'achat',
    dateAcquisition: '',
    quantiteInitiale: null,
    quantiteRestante: null,
    fournisseur: '',
    notes: '',
});

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [g, lotsRes] = await Promise.all([
            http.get(`/graines/${id.value}`),
            http.get(`/graine-lots?graine=${id.value}`),
        ]);
        graine.value = g.data;
        lots.value = lotsRes.data.items;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Chargement impossible.';
    } finally {
        loading.value = false;
    }
}

function resetLotForm() {
    lotForm.source = 'achat';
    lotForm.dateAcquisition = new Date().toISOString().slice(0, 10);
    lotForm.quantiteInitiale = null;
    lotForm.quantiteRestante = null;
    lotForm.fournisseur = '';
    lotForm.notes = '';
    lotErrors.value = {};
}

function startAdd() {
    resetLotForm();
    editingId.value = 'new';
}

function startEdit(lot) {
    lotForm.source = lot.source;
    lotForm.dateAcquisition = lot.dateAcquisition;
    lotForm.quantiteInitiale = lot.quantiteInitiale;
    lotForm.quantiteRestante = lot.quantiteRestante;
    lotForm.fournisseur = lot.fournisseur ?? '';
    lotForm.notes = lot.notes ?? '';
    lotErrors.value = {};
    editingId.value = lot.id;
}

function cancelLot() {
    editingId.value = null;
    lotErrors.value = {};
}

async function saveLot() {
    lotSaving.value = true;
    lotErrors.value = {};
    const payload = { ...lotForm, graine: Number(id.value) };
    try {
        if (editingId.value === 'new') {
            await http.post('/graine-lots', payload);
        } else {
            await http.put(`/graine-lots/${editingId.value}`, payload);
        }
        editingId.value = null;
        await load();
    } catch (e) {
        if (e.response?.status === 422) {
            lotErrors.value = e.response.data.errors ?? {};
        } else {
            lotErrors.value = { _global: 'Erreur lors de l\'enregistrement du lot.' };
        }
    } finally {
        lotSaving.value = false;
    }
}

async function removeLot(lot) {
    if (!window.confirm('Supprimer ce lot ?')) return;
    try {
        await http.delete(`/graine-lots/${lot.id}`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-else-if="graine">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <router-link :to="{ name: 'graine-index' }" class="text-decoration-none small">&larr; Grainothèque</router-link>
                <h1 class="mb-0">{{ graine.code }} · {{ graine.name }}</h1>
            </div>
            <router-link class="btn btn-outline-primary" :to="{ name: 'graine-edit', params: { id: graine.id } }">
                <i class="bi bi-pencil"></i> Éditer
            </router-link>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Type</dt>
                    <dd class="col-sm-9">{{ graine.graineType?.name ?? '—' }} <span v-if="graine.graineType" class="badge text-bg-secondary">{{ graine.graineType.code }}</span></dd>
                    <dt class="col-sm-3">Semis conseillé</dt>
                    <dd class="col-sm-9">{{ METHODES[graine.methodeSemisConseillee] ?? '—' }}</dd>
                    <dt class="col-sm-3">Calendrier</dt>
                    <dd class="col-sm-9">
                        Semis : {{ MOIS[graine.moisSemis] || '—' }} ·
                        Plantation : {{ MOIS[graine.moisPlantationTheorique] || '—' }} ·
                        Récolte : {{ MOIS[graine.moisRecolteTheorique] || '—' }}
                    </dd>
                    <template v-if="graine.notes">
                        <dt class="col-sm-3">Notes</dt>
                        <dd class="col-sm-9" style="white-space: pre-line">{{ graine.notes }}</dd>
                    </template>
                </dl>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h5 mb-0">
                Lots
                <span :class="stockTotal === 0 ? 'badge text-bg-warning' : 'badge text-bg-success'">
                    stock {{ stockTotal }}<template v-if="stockTotal === 0"> · à racheter</template>
                </span>
            </h2>
            <button v-if="editingId !== 'new'" class="btn btn-sm btn-primary" @click="startAdd">
                <i class="bi bi-plus-lg"></i> Ajouter un lot
            </button>
        </div>

        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Date</th>
                    <th>Fournisseur</th>
                    <th class="text-center">Initiale</th>
                    <th class="text-center">Restante</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="editingId === 'new'">
                    <td colspan="6">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Source</label>
                                <select v-model="lotForm.source" class="form-select form-select-sm">
                                    <option value="achat">Achat</option>
                                    <option value="recolte">Récolte</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Date</label>
                                <input type="date" v-model="lotForm.dateAcquisition" class="form-control form-control-sm" :class="{ 'is-invalid': lotErrors.dateAcquisition }">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0">Fournisseur</label>
                                <input v-model="lotForm.fournisseur" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Qté initiale</label>
                                <input type="number" min="0" v-model="lotForm.quantiteInitiale" class="form-control form-control-sm" :class="{ 'is-invalid': lotErrors.quantiteInitiale }">
                            </div>
                            <div class="col-md-3 text-end">
                                <button class="btn btn-sm btn-success" :disabled="lotSaving" @click="saveLot">Enregistrer</button>
                                <button class="btn btn-sm btn-outline-secondary" @click="cancelLot">Annuler</button>
                            </div>
                            <div v-if="lotErrors._global" class="col-12 text-danger small">{{ lotErrors._global }}</div>
                        </div>
                    </td>
                </tr>

                <template v-for="lot in lots" :key="lot.id">
                    <tr v-if="editingId === lot.id">
                        <td colspan="6">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Source</label>
                                    <select v-model="lotForm.source" class="form-select form-select-sm">
                                        <option value="achat">Achat</option>
                                        <option value="recolte">Récolte</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Date</label>
                                    <input type="date" v-model="lotForm.dateAcquisition" class="form-control form-control-sm" :class="{ 'is-invalid': lotErrors.dateAcquisition }">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Fournisseur</label>
                                    <input v-model="lotForm.fournisseur" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Qté initiale</label>
                                    <input type="number" min="0" v-model="lotForm.quantiteInitiale" class="form-control form-control-sm" :class="{ 'is-invalid': lotErrors.quantiteInitiale }">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Qté restante</label>
                                    <input type="number" min="0" v-model="lotForm.quantiteRestante" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2 text-end">
                                    <button class="btn btn-sm btn-success" :disabled="lotSaving" @click="saveLot">Enregistrer</button>
                                    <button class="btn btn-sm btn-outline-secondary" @click="cancelLot">Annuler</button>
                                </div>
                                <div v-if="lotErrors._global" class="col-12 text-danger small">{{ lotErrors._global }}</div>
                            </div>
                        </td>
                    </tr>
                    <tr v-else>
                        <td>{{ lot.source === 'achat' ? 'Achat' : 'Récolte' }}</td>
                        <td>{{ lot.dateAcquisition }}</td>
                        <td>{{ lot.fournisseur ?? '—' }}</td>
                        <td class="text-center">{{ lot.quantiteInitiale }}</td>
                        <td class="text-center">
                            <span :class="lot.quantiteRestante === 0 ? 'badge text-bg-warning' : ''">{{ lot.quantiteRestante }}</span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" @click="startEdit(lot)">Éditer</button>
                            <button class="btn btn-sm btn-outline-danger" @click="removeLot(lot)">Supprimer</button>
                        </td>
                    </tr>
                </template>

                <tr v-if="lots.length === 0 && editingId !== 'new'">
                    <td colspan="6" class="text-muted">Aucun lot. Pensez à en ajouter un.</td>
                </tr>
            </tbody>
        </table>
    </template>
</template>
