<script setup>
import { onMounted, ref } from 'vue';
import http from '../api/http';

const MONTHS = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

const rows = ref([]);
const loading = ref(true);

function cellClass(row, m) {
    const fleur = row.fleur.includes(m);
    const fructi = row.fructi.includes(m);
    if (fleur && fructi) return 'bg-info';
    if (fleur) return 'bg-success';
    if (fructi) return 'bg-warning';
    return '';
}

onMounted(async () => {
    loading.value = true;
    try {
        const { data } = await http.get('/calendar/fructification');
        rows.value = data.rows;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <h1 class="mb-3">Calendrier floraison / récolte</h1>

    <p>
        <span class="badge bg-success">Floraison</span>
        <span class="badge bg-warning text-dark">Récolte</span>
        <span class="badge bg-info">Les deux</span>
    </p>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else class="table-responsive">
        <table class="table table-bordered text-center align-middle">
            <thead>
                <tr>
                    <th class="text-start">Plante</th>
                    <th v-for="(label, i) in MONTHS" :key="i">{{ label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.vegetable.id">
                    <td class="text-start">
                        <router-link :to="{ name: 'vegetable-show', params: { id: row.vegetable.id } }">{{ row.vegetable.name }}</router-link>
                    </td>
                    <td v-for="m in 12" :key="m" :class="cellClass(row, m)">&nbsp;</td>
                </tr>
                <tr v-if="rows.length === 0">
                    <td colspan="13" class="text-muted">Aucune plante.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
