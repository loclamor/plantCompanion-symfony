<script setup>
import { computed, ref } from 'vue';
import http from '../api/http';

const props = defineProps({ photos: { type: Array, default: () => [] } });
const emit = defineEmits(['updated']);

const PLACEHOLDER = '/plante.png';
const error = ref(null);
const lightboxIndex = ref(null);

const current = computed(() =>
    lightboxIndex.value !== null ? props.photos[lightboxIndex.value] : null,
);

function onImgError(event) {
    event.target.onerror = null;
    event.target.src = PLACEHOLDER;
}

async function setDefault(photo) {
    error.value = null;
    try {
        const { data } = await http.put(`/photos/${photo.id}/default`);
        emit('updated', data.photos);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Action impossible.';
    }
}

async function remove(photo) {
    if (!window.confirm('Supprimer cette photo ?')) return;
    error.value = null;
    try {
        const { data } = await http.delete(`/photos/${photo.id}`);
        emit('updated', data.photos ?? []);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Suppression impossible.';
    }
}

function openLightbox(index) {
    lightboxIndex.value = index;
}
function closeLightbox() {
    lightboxIndex.value = null;
}
function prev() {
    lightboxIndex.value = (lightboxIndex.value - 1 + props.photos.length) % props.photos.length;
}
function next() {
    lightboxIndex.value = (lightboxIndex.value + 1) % props.photos.length;
}
</script>

<template>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div class="row">
        <div v-for="(p, i) in photos" :key="p.id" class="col-6 col-md-3 mb-3">
            <div class="card h-100" :class="{ 'border-success': p.isDefault }">
                <img
                    :src="p.url || PLACEHOLDER"
                    class="card-img-top center-img"
                    alt="photo"
                    style="cursor: zoom-in"
                    @click="openLightbox(i)"
                    @error="onImgError"
                >
                <div class="card-body p-2 d-flex flex-wrap gap-1 align-items-center">
                    <span v-if="p.isDefault" class="badge bg-success">Par défaut</span>
                    <button v-else class="btn btn-sm btn-outline-secondary" @click="setDefault(p)">Définir par défaut</button>
                    <button class="btn btn-sm btn-outline-danger ms-auto" @click="remove(p)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <p v-if="photos.length === 0" class="text-muted">Aucune photo.</p>
    </div>

    <!-- Lightbox (carousel contrôlé par Vue, pas de dépendance Bootstrap JS) -->
    <div v-if="current" class="photo-lightbox" @click.self="closeLightbox">
        <button class="btn btn-light position-absolute top-0 end-0 m-3" @click="closeLightbox">
            <i class="bi bi-x-lg"></i>
        </button>
        <button v-if="photos.length > 1" class="btn btn-light lightbox-nav start-0" @click.stop="prev">
            <i class="bi bi-chevron-left"></i>
        </button>
        <img :src="current.urlLarge || current.url || PLACEHOLDER" class="lightbox-img" alt="photo" @error="onImgError">
        <button v-if="photos.length > 1" class="btn btn-light lightbox-nav end-0" @click.stop="next">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</template>

<style scoped>
.photo-lightbox {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1080;
}
.lightbox-img {
    max-width: 90vw;
    max-height: 90vh;
    object-fit: contain;
}
.lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    margin: 0 1rem;
}
</style>
