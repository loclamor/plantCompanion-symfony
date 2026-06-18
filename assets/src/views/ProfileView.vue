<script setup>
import { reactive, ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import http from '../api/http';

const auth = useAuthStore();

const form = reactive({ currentPassword: '', newPassword: '', confirm: '' });
const errors = ref({});
const success = ref(null);
const saving = ref(false);

async function submit() {
    errors.value = {};
    success.value = null;

    if (form.newPassword !== form.confirm) {
        errors.value = { confirm: 'La confirmation ne correspond pas.' };
        return;
    }

    saving.value = true;
    try {
        const { data } = await http.put('/me/password', {
            currentPassword: form.currentPassword,
            newPassword: form.newPassword,
        });
        success.value = data.message ?? 'Mot de passe mis à jour.';
        form.currentPassword = '';
        form.newPassword = '';
        form.confirm = '';
    } catch (e) {
        errors.value = e.response?.status === 422
            ? (e.response.data.errors ?? {})
            : { _global: 'Erreur lors de la mise à jour.' };
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="mx-auto" style="max-width: 560px">
        <h1 class="mb-4">Mon profil</h1>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-subtitle text-success text-uppercase mb-3">Compte</h6>
                <p class="mb-0">
                    <i class="bi bi-person-circle"></i>
                    Utilisateur : <strong>{{ auth.user?.name }}</strong>
                </p>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="card-subtitle text-success text-uppercase mb-3">Changer le mot de passe</h6>

                <div v-if="success" class="alert alert-success">{{ success }}</div>
                <div v-if="errors._global" class="alert alert-danger">{{ errors._global }}</div>

                <form @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label">Mot de passe actuel</label>
                        <input v-model="form.currentPassword" type="password" class="form-control" :class="{ 'is-invalid': errors.currentPassword }" autocomplete="current-password" required>
                        <div v-if="errors.currentPassword" class="invalid-feedback">{{ errors.currentPassword }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input v-model="form.newPassword" type="password" class="form-control" :class="{ 'is-invalid': errors.newPassword }" autocomplete="new-password" required>
                        <div v-if="errors.newPassword" class="invalid-feedback">{{ errors.newPassword }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmer le nouveau mot de passe</label>
                        <input v-model="form.confirm" type="password" class="form-control" :class="{ 'is-invalid': errors.confirm }" autocomplete="new-password" required>
                        <div v-if="errors.confirm" class="invalid-feedback">{{ errors.confirm }}</div>
                    </div>
                    <button class="btn btn-primary px-4" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Mettre à jour' }}</button>
                </form>
            </div>
        </div>
    </div>
</template>
