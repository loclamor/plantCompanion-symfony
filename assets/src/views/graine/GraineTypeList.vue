<script setup>
import { computed, onMounted, ref } from 'vue';
import http from '../../api/http';
import { flattenGraineTypes } from '../../utils/graineTypeTree';

const items = ref([]);
const loading = ref(false);
const error = ref(null);

// Ordonnés en arbre (parent → enfants) avec leur profondeur pour l'indentation.
const orderedItems = computed(() => flattenGraineTypes(items.value));

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/graine-types');
        items.value = data.items;
    } finally {
        loading.value = false;
    }
}

async function remove(item) {
    if (!window.confirm(`Supprimer le type « ${item.name} » ?`)) return;
    try {
        await http.delete(`/graine-types/${item.id}`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

onMounted(load);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="bi bi-tags"></i> Types de graines</h1>
        <router-link class="btn btn-primary" :to="{ name: 'graine-type-new' }">
            <i class="bi bi-plus-lg"></i> Nouveau type
        </router-link>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <table v-else class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Préfixe</th>
                <th class="text-center">Graines</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in orderedItems" :key="item.id">
                <td>
                    <span :style="{ paddingLeft: item.depth * 1.5 + 'rem' }">
                        <i v-if="item.depth > 0" class="bi bi-arrow-return-right text-muted me-1"></i>{{ item.name }}
                    </span>
                </td>
                <td><span class="badge text-bg-secondary">{{ item.code }}</span></td>
                <td class="text-center">{{ item.nbGraines }}</td>
                <td class="text-end">
                    <router-link class="btn btn-sm btn-outline-primary" :to="{ name: 'graine-type-edit', params: { id: item.id } }">Éditer</router-link>
                    <button class="btn btn-sm btn-outline-danger" @click="remove(item)">Supprimer</button>
                </td>
            </tr>
            <tr v-if="items.length === 0">
                <td colspan="4" class="text-muted">Aucun type de graine.</td>
            </tr>
        </tbody>
    </table>
</template>
