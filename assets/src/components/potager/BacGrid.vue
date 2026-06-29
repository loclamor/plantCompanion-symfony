<script setup>
import { computed, ref } from 'vue';
import http from '../../api/http';

const props = defineProps({
    bacSaison: { type: Object, required: true },
    cultures: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
});
const emit = defineEmits(['moved']);

const error = ref(null);
const dragId = ref(null);

const lignes = computed(() => props.bacSaison.lignes ?? props.bacSaison.bacSaison?.lignes ?? 1);
const colonnes = computed(() => props.bacSaison.colonnes ?? 1);

// Cultures « en_place » du bac (les seules placées sur la grille).
const placed = computed(() => props.cultures.filter((c) => c.statut === 'en_place'));

const CELL = 44; // px par case

function onDragStart(culture) {
    if (props.readonly) return;
    dragId.value = culture.id;
}

async function onDrop(x, y) {
    if (props.readonly || dragId.value == null) return;
    const id = dragId.value;
    dragId.value = null;
    error.value = null;
    try {
        await http.put(`/cultures/${id}/placement`, { posX: x, posY: y });
        emit('moved');
    } catch (e) {
        if (e.response?.status === 422) {
            error.value = e.response.data?.errors?.placement ?? 'Placement invalide (hors grille ou chevauchement).';
        } else if (e.response?.status === 409) {
            error.value = e.response.data?.message ?? 'Saison clôturée.';
        } else {
            error.value = 'Déplacement impossible.';
        }
    }
}
</script>

<template>
    <div>
        <div v-if="error" class="alert alert-danger py-1 px-2 small">{{ error }}</div>
        <div
            class="bacgrid"
            :style="{
                gridTemplateColumns: `repeat(${colonnes}, ${CELL}px)`,
                gridTemplateRows: `repeat(${lignes}, ${CELL}px)`,
            }"
        >
            <!-- Cases de fond : cibles de dépôt -->
            <div
                v-for="cell in lignes * colonnes"
                :key="`bg-${cell}`"
                class="bacgrid-cell"
                :style="{
                    gridColumn: ((cell - 1) % colonnes) + 1,
                    gridRow: Math.floor((cell - 1) / colonnes) + 1,
                }"
                @dragover.prevent
                @drop.prevent="onDrop((cell - 1) % colonnes, Math.floor((cell - 1) / colonnes))"
            ></div>

            <!-- Plants posés -->
            <div
                v-for="c in placed"
                :key="c.id"
                class="bacgrid-culture"
                :class="{ draggable: !readonly }"
                :draggable="!readonly"
                :title="c.name"
                :style="{
                    gridColumn: `${c.posX + 1} / span ${c.largeurCases}`,
                    gridRow: `${c.posY + 1} / span ${c.hauteurCases}`,
                }"
                @dragstart="onDragStart(c)"
            >
                <span class="bacgrid-label">{{ c.name }}</span>
            </div>
        </div>
        <p v-if="!readonly" class="text-muted small mt-2">Glissez un plant sur une case libre pour le déplacer.</p>
    </div>
</template>

<style scoped>
.bacgrid {
    display: grid;
    gap: 2px;
    width: max-content;
    background: #e9ecef;
    padding: 2px;
    border-radius: 4px;
    position: relative;
}
.bacgrid-cell {
    background: #fff;
    border-radius: 2px;
    min-width: 0;
}
.bacgrid-culture {
    background: var(--bs-success, #198754);
    color: #fff;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    overflow: hidden;
    padding: 2px;
    z-index: 1;
}
.bacgrid-culture.draggable {
    cursor: grab;
}
.bacgrid-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: center;
}
</style>
