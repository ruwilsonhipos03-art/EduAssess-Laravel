<template>
    <div class="auth-wrapper d-flex align-items-center justify-content-center bg-light">
        <div class="card shadow-sm border-0 auth-card">
            <div class="card-body p-3">
                <div class="text-center mb-4">
                    <div class="brand-icon mb-3 mx-auto">
                        <img src="../../../../public/images/Logo.png" alt="EduAssess Logo" class="brand-logo">
                    </div>
                    <h3 class="fw-bold">Welcome to Edu Assess!</h3>
                    <p class="text-muted small">Please enter your details to sign in.</p>
                </div>

                <form @submit.prevent="handleLogin">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">USERNAME</label>
                        <input v-model="form.login" type="text" class="form-control shadow-none border-2" required
                            placeholder="Enter username" :disabled="loading">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">PASSWORD</label>
                        <div class="input-group">
                            <input v-model="form.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control shadow-none border-2" required placeholder="••••••••"
                                :disabled="loading">
                            <button class="btn btn-outline-secondary" type="button"
                                @click="showPassword = !showPassword" :disabled="loading"
                                aria-label="Toggle password visibility">
                                <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input id="rememberMe" v-model="rememberMe" class="form-check-input" type="checkbox"
                            :disabled="loading">
                        <label class="form-check-label small text-muted" for="rememberMe">
                            Remember me
                        </label>
                    </div>

                    <button type="submit" class="btn btn-emerald w-100 py-2 fw-bold mt-2 shadow-sm" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                        {{ loading ? 'SIGNING IN...' : 'SIGN IN' }}
                    </button>
                </form>

            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

const router = useRouter();
const loading = ref(false);
const rememberMe = ref(false);
const showPassword = ref(false);

const form = reactive({
    login: '',
    password: ''
});

/**
 * Role-to-Route Mapping
 * MUST match the 'path' values defined in your router/index.js exactly.
 */
const roleRoutes = {
    'admin': '/admin/dashboard',
    'college_dean': '/college-dean/dashboard',
    'instructor': '/instructor/dashboard',
    'entrance_examiner': '/entrance/dashboard'
};

const handleLogin = async () => {
    loading.value = true;

    try {
        // 1. Hit the Sanctum login endpoint
        const res = await axios.post('/api/login', {
            username: form.login,
            password: form.password
        });

        const { token, user } = res.data;

        const storage = rememberMe.value ? localStorage : sessionStorage;
        const otherStorage = rememberMe.value ? sessionStorage : localStorage;

        // 2. Persist Session
        storage.setItem('auth_token', token);
        storage.setItem('user_data', JSON.stringify(user));
        otherStorage.removeItem('auth_token');
        otherStorage.removeItem('user_data');

        // 3. Set Global Axios Authorization Header for immediate subsequent calls
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        // 4. Success Feedback using the names from your migration
        const fullName = `${user.first_name} ${user.last_name}`;



        // 5. Role-based Redirection
        const targetPath = roleRoutes[user.role];

        if (targetPath) {
            if (window.Toast) {
                window.Toast.fire({
                    icon: 'success',
                    title: `Welcome back, ${user.first_name}!`
                });
            }
            await router.push(targetPath);
        } else {
            console.error('Unknown role encountered:', user.role);
            // Fallback: If role is unknown, maybe they shouldn't be here
            await router.push('/login');
            Swal.fire({
                icon: 'warning',
                title: 'Access Restricted',
                text: 'This account role does not have an available user interface in the system.'
            });
        }

    } catch (error) {
        console.error('Login Error:', error);
        const errorMessage = error.response?.data?.message || 'Invalid credentials. Please check your username and password.';

        Swal.fire({
            icon: 'error',
            title: 'Authentication Failed',
            text: errorMessage,
            confirmButtonColor: '#10b981'
        });
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.auth-wrapper {
    min-height: 100vh;
    background: #f0f2f5;
    padding: 20px;
    height: fit-content;
    /* Slightly grayer background to make the card pop */
}

.auth-card {
    width: 100%;
    max-width: 420px;
    border-radius: 1.25rem;
}

.brand-icon {
    width: 96px;
    height: 96px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    overflow: hidden;
}

.brand-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.form-control {
    padding: 0.6rem 0.85rem;
    border-color: #e5e7eb;
}

.form-control:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.1);
}

.btn-emerald {
    background-color: #10b981;
    color: white;
    border: none;
    transition: all 0.2s ease;
    padding: 0.8rem;
}

.btn-emerald:hover {
    background-color: #059669;
    transform: translateY(-1px);
}

.btn-emerald:disabled {
    background-color: #6ee7b7;
    cursor: not-allowed;
}

.text-emerald {
    color: #10b981;
}
</style>
