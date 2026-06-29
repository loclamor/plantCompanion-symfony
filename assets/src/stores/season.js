import { defineStore } from 'pinia';
import http from '../api/http';

// Saison courante (sélecteur de saison de la navbar du module Potager).
// Le backend mémorise le choix en session ; on garde ici une copie pour l'UI.
export const useSeasonStore = defineStore('season', {
    state: () => ({
        seasons: [],
        currentId: null,
    }),
    actions: {
        async fetchSeasons() {
            const { data } = await http.get('/saisons');
            this.seasons = data.items ?? data;
        },
        async fetchCurrent() {
            const { data } = await http.get('/current-season');
            this.currentId = data.id ?? null;
        },
        async setCurrent(id) {
            await http.put('/current-season', { id });
            this.currentId = id;
        },
    },
});
