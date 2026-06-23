<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import { useGroupStore } from './stores/group';

const auth = useAuthStore();
const groups = useGroupStore();
const router = useRouter();
const route = useRoute();

const isAuth = computed(() => auth.isAuthenticated);

// Contexte courant déduit du path : /potager* = potager, sinon jardin.
const context = computed(() => (route.path.startsWith('/potager') ? 'potager' : 'jardin'));

// Version injectée au build par Vite (tag Git en prod, 'dev' en local).
const appVersion = __APP_VERSION__;

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
            <router-link class="navbar-brand d-flex align-items-center gap-2 me-2" :to="{ name: 'vegetable-index' }">
                <img :src="'/logo_48.png'" alt="" width="28" height="28">
                <span class="d-none d-sm-inline">PlantCompanion</span>
            </router-link>

            <div class="dropdown me-auto me-lg-3">
                <button class="btn btn-sm btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i :class="context === 'potager' ? 'bi bi-basket2' : 'bi bi-flower1'"></i>
                    {{ context === 'potager' ? 'Potager' : 'Jardin' }}
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <router-link class="dropdown-item d-flex align-items-center gap-2" :class="{ active: context === 'jardin' }" :to="{ name: 'vegetable-index' }">
                            <i class="bi bi-flower1"></i> Jardin
                            <i v-if="context === 'jardin'" class="bi bi-check-lg ms-auto"></i>
                        </router-link>
                    </li>
                    <li>
                        <router-link class="dropdown-item d-flex align-items-center gap-2" :class="{ active: context === 'potager' }" :to="{ name: 'graine-index' }">
                            <i class="bi bi-basket2"></i> Potager
                            <i v-if="context === 'potager'" class="bi bi-check-lg ms-auto"></i>
                        </router-link>
                    </li>
                </ul>
            </div>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <template v-if="context === 'jardin'">
                        <li class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'vegetable-index' }"><i class="bi bi-flower1"></i> Plantes</router-link>
                        </li>
                        <li class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'action-index' }"><i class="bi bi-journal-text"></i> Interventions</router-link>
                        </li>
                        <li class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'calendar' }"><i class="bi bi-calendar3"></i> Calendrier</router-link>
                        </li>
                        <li class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'photo-import' }"><i class="bi bi-images"></i> Importer</router-link>
                        </li>
                        <li class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'print' }"><i class="bi bi-printer"></i> Impression</router-link>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-gear"></i> Paramétrage</a>
                            <ul class="dropdown-menu">
                                <li><router-link class="dropdown-item" :to="{ name: 'parametrage-index', params: { resource: 'types' } }">Types</router-link></li>
                                <li><router-link class="dropdown-item" :to="{ name: 'parametrage-index', params: { resource: 'porte-greffes' } }">Porte-greffes</router-link></li>
                                <li><router-link class="dropdown-item" :to="{ name: 'parametrage-index', params: { resource: 'groups' } }">Groupes</router-link></li>
                                <li><router-link class="dropdown-item" :to="{ name: 'parametrage-index', params: { resource: 'lieux' } }">Lieux</router-link></li>
                            </ul>
                        </li>
                    </template>

                    <template v-else>
                        <li class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'graine-index' }"><i class="bi bi-box-seam"></i> Grainothèque</router-link>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-gear"></i> Paramétrage</a>
                            <ul class="dropdown-menu">
                                <li><router-link class="dropdown-item" :to="{ name: 'graine-type-index' }">Types de graines</router-link></li>
                            </ul>
                        </li>
                    </template>
                </ul>
                <ul class="navbar-nav align-items-center">
                    <li v-if="context === 'jardin'" class="nav-item me-3 d-flex align-items-center text-secondary">
                        <i class="bi bi-collection me-1"></i>
                        <select class="form-select form-select-sm" :value="groups.currentId ?? ''" @change="onGroupChange" style="min-width: 160px">
                            <option value="">Tous les groupes</option>
                            <option v-for="g in groups.groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> {{ auth.user?.name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><router-link class="dropdown-item" :to="{ name: 'profile' }"><i class="bi bi-person-gear"></i> Profil</router-link></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" @click.prevent="logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <router-view />
    </div>

    <footer class="text-center text-muted small py-3 mt-4">
        PlantCompanion · <span class="font-monospace">{{ appVersion }}</span>
    </footer>
</template>

<style scoped>
/* Page active mise en évidence dans la navbar. */
.navbar-nav .nav-link.router-link-active {
    color: #fff;
    font-weight: 600;
    border-bottom: 2px solid var(--main-primary, #008000);
}
.navbar-brand.router-link-active {
    border: 0;
}
</style>
