import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import axios from 'axios';

// Bootstrap & Icons
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';

// SweetAlert2 Setup
import Swal from 'sweetalert2';

// --- AUTH LOGIC START ---

// 1. Configure Axios Defaults
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Helper to manage the token
 */
const setAuthHeader = (token) => {
    if (token) {
        window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
        delete window.axios.defaults.headers.common['Authorization'];
    }
};

// 2. Load token from LocalStorage or SessionStorage immediately on page refresh
const savedToken = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
if (savedToken) {
    setAuthHeader(savedToken);
}

// 3. Global Interceptor to handle session expiration (401)
window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 401) {
            // Token is invalid or expired
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_data');
            sessionStorage.removeItem('auth_token');
            sessionStorage.removeItem('user_data');
            setAuthHeader(null);
            // Only redirect if we aren't already on the login page to avoid loops
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

// --- AUTH LOGIC END ---

// Global Toast instance
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// Attach to window object
window.Swal = Swal;
window.Toast = Toast;

const app = createApp(App);

app.use(router);
app.mount('#app');
