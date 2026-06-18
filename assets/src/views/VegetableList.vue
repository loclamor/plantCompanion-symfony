<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '../api/http';

const router = useRouter();
const PLACEHOLDER = '/plante.png';

const vegetables = ref([]);
const types = ref([]);
const total = ref(0);
const page = ref(1);
const pages = ref(1);
const loading = ref(false);

const filters = reactive({ q: '', type: '', sort: 'name', dir: 'asc' });

async function loadTypes() {
    const { data } = await http.get('/types');
    types.value = data.items ?? data;
}

async function load(targetPage = 1) {
    loading.value = true;
    try {
        const { data } = await http.get('/vegetables', {
            params: {
                q: filters.q || undefined,
                type: filters.type || undefined,
                sort: filters.sort,
                dir: filters.dir,
                page: targetPage,
            },
        });
        vegetables.value = data.items;
        total.value = data.total;
        page.value = data.page;
        pages.value = data.pages;
    } finally {
        loading.value = false;
    }
}

function reset() {
    filters.q = '';
    filters.type = '';
    filters.sort = 'name';
    filters.dir = 'asc';
    load(1);
}

function onImgError(event) {
    event.target.onerror = null;
    event.target.src = PLACEHOLDER;
}

// Sélection multiple → ajout d'une intervention sur plusieurs plantes.
const selected = ref(new Set());
const selectionCount = computed(() => selected.value.size);

function isSelected(id) {
    return selected.value.has(id);
}
function toggleSelect(id) {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
}
function clearSelection() {
    selected.value = new Set();
}
function addInterventionToSelection() {
    router.push({ name: 'action-new', query: { vegetables: [...selected.value].join(',') } });
}

onMounted(() => {
    loadTypes();
    load(1);
});
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Plantes</h1>
        <router-link class="btn btn-primary" :to="{ name: 'vegetable-new' }">
            <i class="bi bi-plus-lg"></i> Nouvelle plante
        </router-link>
    </div>

    <form class="row g-2 mb-3" @submit.prevent="load(1)">
        <div class="col-auto">
            <input v-model="filters.q" type="text" class="form-control" placeholder="Rechercher un nom">
        </div>
        <div class="col-auto">
            <select v-model="filters.type" class="form-select">
                <option value="">Tous les types</option>
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
        </div>
        <div class="col-auto">
            <select v-model="filters.sort" class="form-select">
                <option value="name">Nom</option>
                <option value="creationDate">Date de création</option>
                <option value="addDate">Date d'ajout</option>
                <option value="rusticite">Rusticité</option>
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

    <p class="text-muted">{{ total }} plante(s)</p>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
        <div v-for="v in vegetables" :key="v.id" class="col">
            <router-link class="card h-100 text-decoration-none text-dark" :class="{ 'border-success': isSelected(v.id) }" :to="{ name: 'vegetable-show', params: { id: v.id } }">
                <div class="position-relative">
                    <input
                        type="checkbox"
                        class="form-check-input vegetable-selection"
                        :checked="isSelected(v.id)"
                        title="Sélectionner pour une intervention groupée"
                        @click.stop
                        @change="toggleSelect(v.id)"
                    >
                    <img :src="v.defaultPhotoUrl || PLACEHOLDER" class="center-img card-img-top" alt="photo" @error="onImgError">
                    <span v-if="v.photoCount > 0" class="badge bg-secondary photo-count-badge">
                        <i class="bi bi-images"></i> {{ v.photoCount }}
                    </span>
                </div>
                <div class="card-body">
                    <h5 class="card-title mb-1">{{ v.name }}</h5>
                    <p class="card-text text-muted mb-0">
                        <small>{{ v.type?.name }}</small>
                    </p>
                    <p v-if="v.rusticite != null" class="card-text mb-0">
                        <small class="text-muted">Rusticité : {{ v.rusticite }}°C</small>
                    </p>
                </div>
            </router-link>
        </div>
        <div v-if="vegetables.length === 0" class="col-12">
            <p class="text-muted">Aucune plante.</p>
        </div>
    </div>

    <nav v-if="pages > 1" class="mt-3">
        <ul class="pagination">
            <li class="page-item" :class="{ disabled: page <= 1 }">
                <a class="page-link" href="#" @click.prevent="load(page - 1)">Précédent</a>
            </li>
            <li class="page-item disabled"><span class="page-link">{{ page }} / {{ pages }}</span></li>
            <li class="page-item" :class="{ disabled: page >= pages }">
                <a class="page-link" href="#" @click.prevent="load(page + 1)">Suivant</a>
            </li>
        </ul>
    </nav>

    <!-- Barre flottante d'action groupée -->
    <div v-if="selectionCount > 0" class="selection-bar shadow">
        <span class="me-3">{{ selectionCount }} plante(s) sélectionnée(s)</span>
        <button class="btn btn-primary btn-sm me-2" @click="addInterventionToSelection">
            <i class="bi bi-journal-plus"></i> Ajouter une intervention
        </button>
        <button class="btn btn-outline-secondary btn-sm" @click="clearSelection">Effacer</button>
    </div>
</template>

<style scoped>
.vegetable-selection {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 1.3rem;
    height: 1.3rem;
    z-index: 2;
    cursor: pointer;
    background-color: #fff;
}
.selection-bar {
    position: fixed;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    background: #fff;
    border: 1px solid var(--main-primary);
    border-radius: 2rem;
    padding: 0.5rem 1.25rem;
    display: flex;
    align-items: center;
    z-index: 1050;
}
</style>
