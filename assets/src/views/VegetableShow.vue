<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '../api/http';
import PhotoUploader from '../components/PhotoUploader.vue';
import PhotoGallery from '../components/PhotoGallery.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });
const router = useRouter();

const PLACEHOLDER = '/plante.png';
const vegetable = ref(null);
const loading = ref(true);

function fmt(dt) {
    return dt ? new Date(dt).toLocaleString('fr-FR') : '';
}

function onImgError(event) {
    event.target.onerror = null;
    event.target.src = PLACEHOLDER;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await http.get(`/vegetables/${props.id}`);
        vegetable.value = data;
    } finally {
        loading.value = false;
    }
}

async function remove() {
    if (!window.confirm('Supprimer cette plante ?')) return;
    await http.delete(`/vegetables/${props.id}`);
    router.push({ name: 'vegetable-index' });
}

function onPhotosUpdated(photos) {
    if (vegetable.value) vegetable.value.photos = photos;
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="vegetable">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">{{ vegetable.name }}</h1>
            <div>
                <router-link class="btn btn-outline-primary" :to="{ name: 'vegetable-edit', params: { id: vegetable.id } }">Éditer</router-link>
                <button class="btn btn-outline-danger" @click="remove">Supprimer</button>
            </div>
        </div>

        <table class="table">
            <tbody>
                <tr><th>Nom latin</th><td>{{ vegetable.nomLatin }}</td></tr>
                <tr><th>Type</th><td>{{ vegetable.type?.name }}</td></tr>
                <tr><th>Groupe</th><td>{{ vegetable.group?.name }}</td></tr>
                <tr><th>Origine (type)</th><td>{{ vegetable.typeOrigine }}</td></tr>
                <tr><th>Porte-greffe</th><td>{{ vegetable.porteGreffe?.name }}</td></tr>
                <tr><th>Lieu d'origine</th><td>{{ vegetable.lieuOrigine?.name }}</td></tr>
                <tr><th>Rusticité</th><td>{{ vegetable.rusticite }}</td></tr>
                <tr><th>Fructification (mois)</th><td>{{ vegetable.moisFructiDebut }} → {{ vegetable.moisFructiFin }}</td></tr>
                <tr><th>Floraison (mois)</th><td>{{ vegetable.moisFleurDebut }} → {{ vegetable.moisFleurFin }}</td></tr>
                <tr><th>Pollinisateur fleur</th><td>{{ vegetable.pFleur }}</td></tr>
                <tr><th>Pollinisateur fructi</th><td>{{ vegetable.pFructi }}</td></tr>
                <tr><th>Date de création</th><td>{{ fmt(vegetable.creationDate) }}</td></tr>
                <tr><th>Date d'ajout</th><td>{{ fmt(vegetable.addDate) }}</td></tr>
            </tbody>
        </table>

        <h2 class="mt-4">Photos</h2>
        <PhotoUploader :vegetable-id="vegetable.id" @updated="onPhotosUpdated" />
        <PhotoGallery :photos="vegetable.photos ?? []" @updated="onPhotosUpdated" />

        <h2 class="mt-4">Interventions</h2>
        <table class="table">
            <thead><tr><th>Date</th><th>Type</th><th>Titre</th><th>Commentaire</th></tr></thead>
            <tbody>
                <tr v-for="a in vegetable.actions" :key="a.id">
                    <td>{{ fmt(a.date) }}</td>
                    <td>{{ a.typeAction }}</td>
                    <td>{{ a.title }}</td>
                    <td>{{ a.comment }}</td>
                </tr>
                <tr v-if="!vegetable.actions?.length"><td colspan="4" class="text-muted">Aucune intervention</td></tr>
            </tbody>
        </table>

        <h2 class="mt-4">Historique des modifications</h2>
        <table class="table">
            <thead><tr><th>Date</th><th>Champ</th><th>Ancienne valeur</th><th>Nouvelle valeur</th></tr></thead>
            <tbody>
                <tr v-for="(h, i) in vegetable.histories" :key="i">
                    <td>{{ fmt(h.date) }}</td>
                    <td>{{ h.key }}</td>
                    <td>{{ h.oldValue }}</td>
                    <td>{{ h.newValue }}</td>
                </tr>
                <tr v-if="!vegetable.histories?.length"><td colspan="4" class="text-muted">Aucune modification enregistrée</td></tr>
            </tbody>
        </table>

        <router-link class="btn btn-link ps-0" :to="{ name: 'vegetable-index' }">← Retour à la liste</router-link>
    </div>
</template>
