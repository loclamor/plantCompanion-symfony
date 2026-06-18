<script setup>
import { ref } from 'vue';
import http from '../api/http';

const props = defineProps({ vegetableId: { type: [String, Number], required: true } });
const emit = defineEmits(['updated']);

const dragOver = ref(false);
const uploading = ref(false);
const progress = ref(0);
const error = ref(null);
const fileInput = ref(null);

async function send(files) {
    if (!files || files.length === 0) return;
    error.value = null;
    uploading.value = true;
    progress.value = 0;

    const formData = new FormData();
    for (const file of files) {
        formData.append('images', file);
    }

    try {
        const { data } = await http.post(`/vegetables/${props.vegetableId}/photos`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (e) => {
                progress.value = e.total ? Math.round((e.loaded * 100) / e.total) : 0;
            },
        });
        emit('updated', data.photos);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Échec de l\'upload.';
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

function onDrop(event) {
    dragOver.value = false;
    send(event.dataTransfer.files);
}

function onPick(event) {
    send(event.target.files);
}
</script>

<template>
    <div
        class="border border-2 rounded p-4 text-center mb-3"
        :class="dragOver ? 'border-primary bg-light' : 'border-dashed text-muted'"
        @dragover.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="onDrop"
        @click="fileInput.click()"
        style="cursor: pointer; border-style: dashed !important;"
    >
        <input ref="fileInput" type="file" accept="image/*" multiple class="d-none" @change="onPick">
        <i class="bi bi-cloud-arrow-up fs-2"></i>
        <p class="mb-0">Glissez des photos ici, ou cliquez pour choisir</p>

        <div v-if="uploading" class="progress mt-3">
            <div class="progress-bar" role="progressbar" :style="{ width: progress + '%' }">{{ progress }}%</div>
        </div>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>
</template>
