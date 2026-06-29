<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import http from '../../api/http';
import { flattenGraineTypes, indentLabel } from '../../utils/graineTypeTree';

const router = useRouter();
const route = useRoute();

const METHODES = { pleine_terre: 'Pleine terre', couvert: 'Couvert' };

const items = ref([]);
const graineTypes = ref([]);
const total = ref(0);
const page = ref(1);
const pages = ref(1);
const loading = ref(false);
const error = ref(null);

// Brouillon des filtres lié au formulaire ; l'état appliqué vit dans l'URL (query).
const filters = reactive({ q: '', graineType: '', stock: '', sort: 'code', dir: 'asc' });

function syncFiltersFromQuery() {
    filters.q = typeof route.query.q === 'string' ? route.query.q : '';
    filters.graineType = route.query.graineType != null ? String(route.query.graineType) : '';
    filters.stock = typeof route.query.stock === 'string' ? route.query.stock : '';
    filters.sort = typeof route.query.sort === 'string' ? route.query.sort : 'code';
    filters.dir = route.query.dir === 'desc' ? 'desc' : 'asc';
}

function queryPage() {
    const p = parseInt(route.query.page, 10);
    return Number.isInteger(p) && p > 0 ? p : 1;
}

async function loadTypes() {
    const { data } = await http.get('/graine-types');
    graineTypes.value = data.items ?? data;
}

// Options du filtre, ordonnées en arbre et indentées (le backend inclut les descendants).
const typeOptions = computed(() =>
    flattenGraineTypes(graineTypes.value).map((t) => ({ id: t.id, label: indentLabel(t) })),
);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/graines', {
            params: {
                q: filters.q || undefined,
                graineType: filters.graineType || undefined,
                stock: filters.stock || undefined,
                sort: filters.sort,
                dir: filters.dir,
                page: queryPage(),
            },
        });
        items.value = data.items;
        total.value = data.total;
        page.value = data.page;
        pages.value = data.pages;
    } finally {
        loading.value = false;
    }
}

// Query URL à partir des filtres courants (valeurs par défaut omises → URL propre).
function buildQuery(targetPage) {
    const query = {};
    if (filters.q) query.q = filters.q;
    if (filters.graineType) query.graineType = String(filters.graineType);
    if (filters.stock) query.stock = filters.stock;
    if (filters.sort !== 'code') query.sort = filters.sort;
    if (filters.dir !== 'asc') query.dir = filters.dir;
    if (targetPage > 1) query.page = String(targetPage);
    return query;
}

// Pousse l'état dans l'URL ; le watcher sur route.query recharge la liste.
function navigate(targetPage) {
    router.push({ name: 'graine-index', query: buildQuery(targetPage) }).catch(() => {});
}

function submit() {
    navigate(1);
}

function reset() {
    filters.q = '';
    filters.graineType = '';
    filters.stock = '';
    filters.sort = 'code';
    filters.dir = 'asc';
    navigate(1);
}

function goPage(targetPage) {
    if (targetPage < 1 || targetPage > pages.value) return;
    navigate(targetPage);
}

async function remove(item) {
    if (!window.confirm(`Supprimer la graine « ${item.code} · ${item.name} » ?`)) return;
    try {
        await http.delete(`/graines/${item.id}`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

loadTypes();
// La query URL pilote la liste : au montage (immediate) et à chaque changement
// (filtre, pagination, back/forward navigateur).
watch(
    () => route.query,
    () => {
        syncFiltersFromQuery();
        load();
    },
    { immediate: true },
);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="bi bi-basket2"></i> Grainothèque</h1>
        <div class="d-flex gap-2">
            <router-link class="btn btn-outline-secondary" :to="{ name: 'graine-type-index' }">
                <i class="bi bi-tags"></i> Types
            </router-link>
            <router-link class="btn btn-primary" :to="{ name: 'graine-new' }">
                <i class="bi bi-plus-lg"></i> Nouvelle graine
            </router-link>
        </div>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <form class="row g-2 mb-3" @submit.prevent="submit">
        <div class="col-auto">
            <input v-model="filters.q" type="text" class="form-control" placeholder="Code, nom ou type">
        </div>
        <div class="col-auto">
            <select v-model="filters.graineType" class="form-select">
                <option value="">Tous les types</option>
                <option v-for="t in typeOptions" :key="t.id" :value="t.id">{{ t.label }}</option>
            </select>
        </div>
        <div class="col-auto">
            <select v-model="filters.stock" class="form-select">
                <option value="">Tout stock</option>
                <option value="rachat">À racheter</option>
                <option value="faible">Stock faible</option>
                <option value="ok">En quantité</option>
            </select>
        </div>
        <div class="col-auto">
            <select v-model="filters.sort" class="form-select">
                <option value="code">Code</option>
                <option value="name">Nom</option>
                <option value="stock">Stock</option>
            </select>
        </div>
        <div class="col-auto">
            <select v-model="filters.dir" class="form-select">
                <option value="asc">Croissant</option>
                <option value="desc">Décroissant</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">Filtrer</button>
            <button class="btn btn-outline-secondary" type="button" @click="reset">Réinitialiser</button>
        </div>
    </form>

    <p class="text-muted">{{ total }} graine(s)</p>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <template v-else>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Type</th>
                    <th>Semis conseillé</th>
                    <th class="text-center">Stock</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in items" :key="item.id">
                    <td><router-link :to="{ name: 'graine-detail', params: { id: item.id } }" class="fw-semibold text-decoration-none">{{ item.code }}</router-link></td>
                    <td>{{ item.name }}</td>
                    <td>{{ item.graineType?.name ?? '—' }}</td>
                    <td>{{ METHODES[item.methodeSemisConseillee] ?? '—' }}</td>
                    <td class="text-center">
                        <span :class="item.stockRestant === 0 ? 'badge text-bg-warning' : 'badge text-bg-success'">
                            {{ item.stockRestant }}
                            <template v-if="item.stockRestant === 0"> · à racheter</template>
                        </span>
                    </td>
                    <td class="text-end">
                        <router-link class="btn btn-sm btn-outline-secondary" :to="{ name: 'graine-detail', params: { id: item.id } }">Détail</router-link>
                        <router-link class="btn btn-sm btn-outline-primary" :to="{ name: 'graine-edit', params: { id: item.id } }">Éditer</router-link>
                        <button class="btn btn-sm btn-outline-danger" @click="remove(item)">Supprimer</button>
                    </td>
                </tr>
                <tr v-if="items.length === 0">
                    <td colspan="6" class="text-muted">Aucune graine.</td>
                </tr>
            </tbody>
        </table>

        <nav v-if="pages > 1" class="mt-3">
            <ul class="pagination">
                <li class="page-item" :class="{ disabled: page <= 1 }">
                    <a class="page-link" href="#" @click.prevent="goPage(page - 1)">Précédent</a>
                </li>
                <li class="page-item disabled"><span class="page-link">{{ page }} / {{ pages }}</span></li>
                <li class="page-item" :class="{ disabled: page >= pages }">
                    <a class="page-link" href="#" @click.prevent="goPage(page + 1)">Suivant</a>
                </li>
            </ul>
        </nav>
    </template>
</template>
