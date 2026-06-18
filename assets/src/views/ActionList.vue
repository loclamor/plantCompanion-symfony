<script setup>
import { onMounted, ref } from 'vue';
import http from '../api/http';

const actions = ref([]);
const loading = ref(false);
const error = ref(null);

function fmt(dt) {
    return dt ? new Date(dt).toLocaleString('fr-FR') : '';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await http.get('/actions');
        actions.value = data.items;
    } finally {
        loading.value = false;
    }
}

async function remove(action) {
    if (!window.confirm('Supprimer cette intervention ?')) return;
    error.value = null;
    try {
        await http.delete(`/actions/${action.id}`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

onMounted(load);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Interventions</h1>
        <router-link class="btn btn-primary" :to="{ name: 'action-new' }">
            <i class="bi bi-plus-lg"></i> Nouvelle intervention
        </router-link>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <table v-else class="table table-hover">
        <thead>
            <tr>
                <th>Date</th>
                <th>Plante</th>
                <th>Type</th>
                <th>Titre</th>
                <th>Commentaire</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="a in actions" :key="a.id">
                <td>{{ fmt(a.date) }}</td>
                <td>
                    <router-link v-if="a.vegetable" :to="{ name: 'vegetable-show', params: { id: a.vegetable.id } }">
                        {{ a.vegetable.name }}
                    </router-link>
                </td>
                <td>{{ a.typeAction }}</td>
                <td>{{ a.title }}</td>
                <td>{{ a.comment }}</td>
                <td class="text-end">
                    <router-link class="btn btn-sm btn-outline-primary" :to="{ name: 'action-edit', params: { id: a.id } }">Éditer</router-link>
                    <button class="btn btn-sm btn-outline-danger" @click="remove(a)">Supprimer</button>
                </td>
            </tr>
            <tr v-if="actions.length === 0">
                <td colspan="6" class="text-muted">Aucune intervention.</td>
            </tr>
        </tbody>
    </table>
</template>
