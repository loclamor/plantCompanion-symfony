<script setup>
import { onMounted, ref } from 'vue';
import http from '../../api/http';
import { useSeasonStore } from '../../stores/season';

const seasons = useSeasonStore();

const items = ref([]);
const loading = ref(false);
const error = ref(null);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/saisons');
        items.value = data.items;
    } finally {
        loading.value = false;
    }
}

async function cloturer(item) {
    if (!window.confirm(`Clôturer la saison « ${item.name} » ? Elle passera en lecture seule.`)) return;
    try {
        await http.put(`/saisons/${item.id}/cloturer`);
        await load();
        await seasons.fetchSeasons();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Clôture impossible.';
    }
}

async function remove(item) {
    if (!window.confirm(`Supprimer la saison « ${item.name} » ?`)) return;
    try {
        await http.delete(`/saisons/${item.id}`);
        await load();
        await seasons.fetchSeasons();
        await seasons.fetchCurrent();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

onMounted(load);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="bi bi-calendar-range"></i> Saisons</h1>
        <router-link class="btn btn-primary" :to="{ name: 'saison-new' }">
            <i class="bi bi-plus-lg"></i> Nouvelle saison
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
                <th>Année</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Statut</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in items" :key="item.id">
                <td>{{ item.name }}</td>
                <td>{{ item.annee }}</td>
                <td>{{ item.dateDebut ?? '—' }}</td>
                <td>{{ item.dateFin ?? '—' }}</td>
                <td>
                    <span class="badge" :class="item.statut === 'active' ? 'text-bg-success' : 'text-bg-secondary'">
                        {{ item.statut === 'active' ? 'Active' : 'Clôturée' }}
                    </span>
                </td>
                <td class="text-end">
                    <button v-if="item.statut === 'active'" class="btn btn-sm btn-outline-warning" @click="cloturer(item)">Clôturer</button>
                    <router-link class="btn btn-sm btn-outline-primary" :to="{ name: 'saison-edit', params: { id: item.id } }">Éditer</router-link>
                    <button class="btn btn-sm btn-outline-danger" @click="remove(item)">Supprimer</button>
                </td>
            </tr>
            <tr v-if="items.length === 0">
                <td colspan="6" class="text-muted">Aucune saison.</td>
            </tr>
        </tbody>
    </table>
</template>
