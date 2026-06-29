<script setup>
import { onMounted, ref } from 'vue';
import http from '../../api/http';

const METHODES = { pleine_terre: 'Pleine terre', couvert: 'Couvert' };

const items = ref([]);
const loading = ref(false);
const error = ref(null);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/graines');
        items.value = data.items;
    } finally {
        loading.value = false;
    }
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

onMounted(load);
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

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <table v-else class="table table-hover align-middle">
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
</template>
