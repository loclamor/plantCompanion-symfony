import { defineStore } from 'pinia';
import http from '../api/http';

// Groupe courant (équivalent du sélecteur de groupe de la navbar legacy).
// Le backend mémorise le choix en session ; on garde ici une copie pour l'UI.
export const useGroupStore = defineStore('group', {
    state: () => ({
        groups: [],
        currentId: null,
    }),
    actions: {
        async fetchGroups() {
            const { data } = await http.get('/groups');
            this.groups = data.items ?? data;
        },
        async fetchCurrent() {
            const { data } = await http.get('/current-group');
            this.currentId = data.id ?? null;
        },
        async setCurrent(id) {
            await http.put('/current-group', { id });
            this.currentId = id;
        },
    },
});
