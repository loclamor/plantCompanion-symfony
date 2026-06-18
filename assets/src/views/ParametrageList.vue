<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import { resourceOr404 } from '../config/parametrage';

const route = useRoute();
const router = useRouter();

const resourceKey = computed(() => route.params.resource);
const config = computed(() => resourceOr404(resourceKey.value));

const items = ref([]);
const loading = ref(false);
const error = ref(null);

async function load() {
    if (!config.value) return;
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get(config.value.endpoint);
        items.value = data.items;
    } finally {
        loading.value = false;
    }
}

async function remove(item) {
    if (!window.confirm(`Supprimer « ${item.name} » ?`)) return;
    try {
        await http.delete(`${config.value.endpoint}/${item.id}`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

// recharge quand on navigue d'une ressource à l'autre
watch(resourceKey, load, { immediate: true });
</script>

<template>
    <div v-if="!config" class="alert alert-warning">Ressource inconnue.</div>

    <template v-else>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">{{ config.title }}</h1>
            <router-link class="btn btn-primary" :to="{ name: 'parametrage-new', params: { resource: resourceKey } }">
                <i class="bi bi-plus-lg"></i> Nouveau
            </router-link>
        </div>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <table v-else class="table table-hover">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th v-for="col in config.columns" :key="col.label">{{ col.label }}</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in items" :key="item.id">
                    <td>{{ item.name }}</td>
                    <td v-for="col in config.columns" :key="col.label">{{ col.value(item) }}</td>
                    <td class="text-end">
                        <router-link class="btn btn-sm btn-outline-primary" :to="{ name: 'parametrage-edit', params: { resource: resourceKey, id: item.id } }">Éditer</router-link>
                        <button class="btn btn-sm btn-outline-danger" @click="remove(item)">Supprimer</button>
                    </td>
                </tr>
                <tr v-if="items.length === 0">
                    <td :colspan="2 + config.columns.length" class="text-muted">Aucun élément.</td>
                </tr>
            </tbody>
        </table>
    </template>
</template>
