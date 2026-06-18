<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import { useGroupStore } from './stores/group';

const auth = useAuthStore();
const groups = useGroupStore();
const router = useRouter();

const isAuth = computed(() => auth.isAuthenticated);

async function loadGroupData() {
    if (isAuth.value) {
        await Promise.all([groups.fetchGroups(), groups.fetchCurrent()]);
    }
}

onMounted(loadGroupData);
watch(isAuth, loadGroupData);

async function onGroupChange(event) {
    const value = event.target.value;
    await groups.setCurrent(value === '' ? null : Number(value));
    // recharge la liste courante si on est dessus
    if (router.currentRoute.value.name === 'vegetable-index') {
        router.go(0);
    }
}

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <nav v-if="isAuth" class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <router-link class="navbar-brand" :to="{ name: 'vegetable-index' }">PlantCompanion</router-link>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <router-link class="nav-link" :to="{ name: 'vegetable-index' }">Plantes</router-link>
                    </li>
                    <!-- Interventions / Calendrier / Impression : ajoutés plus tard -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Paramétrage</a>
                        <ul class="dropdown-menu">
                            <li><router-link class="dropdown-item" :to="{ name: 'parametrage-index', params: { resource: 'types' } }">Types</router-link></li>
                            <li><router-link class="dropdown-item" :to="{ name: 'parametrage-index', params: { resource: 'porte-greffes' } }">Porte-greffes</router-link></li>
                            <li><router-link class="dropdown-item" :to="{ name: 'parametrage-index', params: { resource: 'groups' } }">Groupes</router-link></li>
                            <li><router-link class="dropdown-item" :to="{ name: 'parametrage-index', params: { resource: 'lieux' } }">Lieux</router-link></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item me-3">
                        <select class="form-select form-select-sm" :value="groups.currentId ?? ''" @change="onGroupChange">
                            <option value="">Tous les groupes</option>
                            <option v-for="g in groups.groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </li>
                    <li class="nav-item">
                        <span class="navbar-text me-3">Connecté en tant que {{ auth.user?.name }}</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" @click.prevent="logout">Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <router-view />
    </div>
</template>
