<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const name = ref('');
const password = ref('');
const error = ref(null);
const loading = ref(false);

async function submit() {
    error.value = null;
    loading.value = true;
    try {
        await auth.login(name.value, password.value);
        router.push(route.query.redirect || { name: 'vegetable-index' });
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Identifiants invalides.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <h1 class="h3 mb-4 text-center">PlantCompanion</h1>
            <form @submit.prevent="submit" class="card card-body">
                <div v-if="error" class="alert alert-danger">{{ error }}</div>
                <div class="mb-3">
                    <label class="form-label" for="name">Nom d'utilisateur</label>
                    <input id="name" v-model="name" class="form-control" autocomplete="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input id="password" v-model="password" type="password" class="form-control" autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary" :disabled="loading">
                    {{ loading ? 'Connexion…' : 'Se connecter' }}
                </button>
            </form>
        </div>
    </div>
</template>
