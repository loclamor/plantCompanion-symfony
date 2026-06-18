import { defineStore } from 'pinia';
import http from '../api/http';

// Store d'authentification : équivalent d'un service Angular injecté.
export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        ready: false, // /me résolu au moins une fois
    }),
    getters: {
        isAuthenticated: (state) => state.user !== null,
    },
    actions: {
        async fetchMe() {
            try {
                const { data } = await http.get('/me');
                this.user = data;
            } catch {
                this.user = null;
            } finally {
                this.ready = true;
            }
        },
        async login(name, password) {
            const { data } = await http.post('/login', { name, password });
            this.user = data;
        },
        async logout() {
            await http.post('/logout');
            this.user = null;
        },
    },
});
