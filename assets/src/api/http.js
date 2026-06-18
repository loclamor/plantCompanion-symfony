import axios from 'axios';
import router from '../router';

// Client HTTP unique. baseURL /api, cookie de session envoyé automatiquement
// (same-origin) → pas de token à gérer. Sur 401, on renvoie vers /login.
const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

http.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            const current = router.currentRoute.value;
            if (current.name !== 'login') {
                router.push({ name: 'login', query: { redirect: current.fullPath } });
            }
        }
        return Promise.reject(error);
    },
);

export default http;
