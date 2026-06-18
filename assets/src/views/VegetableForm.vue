<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '../api/http';
import { useGroupStore } from '../stores/group';

const props = defineProps({ id: { type: [String, Number], default: null } });
const router = useRouter();
const groupStore = useGroupStore();

const isEdit = computed(() => props.id != null);
const loading = ref(true);
const saving = ref(false);
const errors = ref({});

// listes de référence pour les selects
const types = ref([]);
const groups = ref([]);
const porteGreffes = ref([]);
const lieux = ref([]);
const parents = ref([]);

// Valeurs fixes reprises du legacy (Model_Vegetable).
const TYPE_ORIGINES = [
    { value: 'bouture', label: 'Bouture' },
    { value: 'semis', label: 'Semis' },
    { value: 'greffe', label: 'Greffe' },
    { value: 'marcottage', label: 'Marcottage' },
];
const PERIODES = ['Printemps', 'Ete', 'Automne', 'Hivers', '4 Saisons', 'Printemps - Automne'];
const MONTHS = [
    { value: 1, label: 'Janvier' }, { value: 2, label: 'Février' }, { value: 3, label: 'Mars' },
    { value: 4, label: 'Avril' }, { value: 5, label: 'Mai' }, { value: 6, label: 'Juin' },
    { value: 7, label: 'Juillet' }, { value: 8, label: 'Août' }, { value: 9, label: 'Septembre' },
    { value: 10, label: 'Octobre' }, { value: 11, label: 'Novembre' }, { value: 12, label: 'Décembre' },
];

const NEW_PG = '-new-';

const form = reactive({
    name: '',
    creationDate: '',
    addDate: '',
    typeOrigine: null,
    nomLatin: '',
    rusticite: null,
    moisFructiDebut: null,
    moisFructiFin: null,
    moisFleurDebut: null,
    moisFleurFin: null,
    pFleur: null,
    pFructi: null,
    type: null,
    group: null,
    parent: null,
    porteGreffe: null,
    newPorteGreffe: '',
    lieuOrigine: null,
});

// datetime ISO -> valeur input datetime-local (yyyy-MM-ddTHH:mm)
function toLocalInput(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

async function loadReferences() {
    const [t, g, pg, l, names] = await Promise.all([
        http.get('/types'),
        http.get('/groups'),
        http.get('/porte-greffes'),
        http.get('/lieux'),
        http.get('/vegetables/names'),
    ]);
    types.value = t.data.items ?? t.data;
    groups.value = g.data.items ?? g.data;
    porteGreffes.value = pg.data.items ?? pg.data;
    lieux.value = l.data.items ?? l.data;
    parents.value = names.data.items ?? names.data;
}

async function loadVegetable() {
    const { data } = await http.get(`/vegetables/${props.id}`);
    form.name = data.name ?? '';
    form.creationDate = toLocalInput(data.creationDate);
    form.addDate = toLocalInput(data.addDate);
    form.typeOrigine = data.typeOrigine ?? null;
    form.nomLatin = data.nomLatin ?? '';
    form.rusticite = data.rusticite;
    form.moisFructiDebut = data.moisFructiDebut;
    form.moisFructiFin = data.moisFructiFin;
    form.moisFleurDebut = data.moisFleurDebut;
    form.moisFleurFin = data.moisFleurFin;
    form.pFleur = data.pFleur ?? null;
    form.pFructi = data.pFructi ?? null;
    form.type = data.type?.id ?? null;
    form.group = data.group?.id ?? null;
    form.parent = data.parent?.id ?? null;
    form.porteGreffe = data.porteGreffe?.id ?? null;
    form.lieuOrigine = data.lieuOrigine?.id ?? null;
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        if (isEdit.value) {
            await http.put(`/vegetables/${props.id}`, { ...form });
            router.push({ name: 'vegetable-show', params: { id: props.id } });
        } else {
            const { data } = await http.post('/vegetables', { ...form });
            router.push({ name: 'vegetable-show', params: { id: data.id } });
        }
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            errors.value = { _global: 'Erreur lors de l\'enregistrement.' };
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    try {
        await loadReferences();
        if (isEdit.value) {
            await loadVegetable();
        } else if (groupStore.currentId != null) {
            // Création : pré-sélection du groupe courant (comportement legacy).
            form.group = groupStore.currentId;
        }
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <h1 class="mb-3">{{ isEdit ? 'Éditer la plante' : 'Nouvelle plante' }}</h1>

    <div v-if="loading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <form v-else @submit.prevent="submit" class="row g-3">
        <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>

        <div class="col-md-6">
            <label class="form-label">Nom *</label>
            <input v-model="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" required>
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Nom latin</label>
            <input v-model="form.nomLatin" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Type *</label>
            <select v-model="form.type" class="form-select" :class="{ 'is-invalid': errors.type }" required>
                <option :value="null" disabled>— Choisir —</option>
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <div v-if="errors.type" class="invalid-feedback">{{ errors.type }}</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Groupe</label>
            <select v-model="form.group" class="form-select">
                <option :value="null">Sans groupe</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Plante parente</label>
            <select v-model="form.parent" class="form-select">
                <option :value="null">Sans parent</option>
                <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Porte-greffe</label>
            <select v-model="form.porteGreffe" class="form-select">
                <option :value="null">Non connu</option>
                <option v-for="pg in porteGreffes" :key="pg.id" :value="pg.id">{{ pg.name }}</option>
                <option :value="NEW_PG">Nouveau…</option>
            </select>
            <input
                v-if="form.porteGreffe === NEW_PG"
                v-model="form.newPorteGreffe"
                class="form-control mt-2"
                placeholder="Nouveau porte-greffe… (rattaché au type sélectionné)"
            >
        </div>
        <div class="col-md-4">
            <label class="form-label">Lieu d'origine</label>
            <select v-model="form.lieuOrigine" class="form-select">
                <option :value="null">Non connu</option>
                <option v-for="l in lieux" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Type d'origine</label>
            <select v-model="form.typeOrigine" class="form-select">
                <option :value="null">Aucun</option>
                <option v-for="t in TYPE_ORIGINES" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Rusticité (°C)</label>
            <input v-model.number="form.rusticite" type="number" class="form-control">
        </div>

        <div class="col-md-3">
            <label class="form-label">Début récolte</label>
            <select v-model.number="form.moisFructiDebut" class="form-select">
                <option :value="null">Choisir…</option>
                <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Fin récolte</label>
            <select v-model.number="form.moisFructiFin" class="form-select">
                <option :value="null">Choisir…</option>
                <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Ou période de récolte</label>
            <select v-model="form.pFructi" class="form-select">
                <option :value="null">—</option>
                <option v-for="p in PERIODES" :key="p" :value="p">{{ p }}</option>
            </select>
        </div>
        <div class="col-md-3"></div>

        <div class="col-md-3">
            <label class="form-label">Début floraison</label>
            <select v-model.number="form.moisFleurDebut" class="form-select">
                <option :value="null">Choisir…</option>
                <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Fin floraison</label>
            <select v-model.number="form.moisFleurFin" class="form-select">
                <option :value="null">Choisir…</option>
                <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Ou période de floraison</label>
            <select v-model="form.pFleur" class="form-select">
                <option :value="null">—</option>
                <option v-for="p in PERIODES" :key="p" :value="p">{{ p }}</option>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Date de création</label>
            <input v-model="form.creationDate" type="datetime-local" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Date d'ajout</label>
            <input v-model="form.addDate" type="datetime-local" class="form-control">
        </div>

        <div class="col-12">
            <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
            <router-link class="btn btn-outline-secondary" :to="{ name: 'vegetable-index' }">Annuler</router-link>
        </div>
    </form>
</template>
