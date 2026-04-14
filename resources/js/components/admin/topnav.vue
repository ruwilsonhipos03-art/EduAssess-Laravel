<template>
    <nav class="top-nav border-bottom bg-white d-flex align-items-center justify-content-between px-4">
        <div class="d-flex align-items-center">
            <button @click="$emit('toggle-sidebar')" class="btn btn-link text-dark p-0 me-3">
                <i class="bi bi-list fs-3"></i>
            </button>

            <div class="d-flex align-items-center gap-2">
                <div class="logo-box">
                    <img src="../../../../public/images/Logo.png" alt="EduAssess Logo" class="logo-img">
                </div>
                <span class="fw-bold fs-5 mb-0">Welcome back, {{ user.first_name }}!</span>
            </div>
        </div>

        <div class="profile-section d-flex align-items-center gap-2 dropdown">
            <button class="btn d-flex align-items-center gap-2 border-0" data-bs-toggle="dropdown">
                <div class="avatar">{{ userInitials }}</div>

                <span class="fw-semibold d-none d-md-inline text-dark">
                    {{ user.first_name }} {{ user.last_name }}
                </span>
                <i class="bi bi-chevron-down small text-muted"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li class="px-3 py-2 border-bottom">
                    <div class="small text-muted text-uppercase fw-bold">Role</div>
                    <div class="text-dark small">{{ user.role }}</div>
                </li>
                <li>
                    <router-link class="dropdown-item py-2" :to="`${baseRoute}/profile`">
                        <i class="bi bi-person me-2"></i> Profile
                    </router-link>
                </li>
                <li>
                    <router-link class="dropdown-item py-2" :to="`${baseRoute}/settings`">
                        <i class="bi bi-gear me-2"></i> Settings
                    </router-link>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item py-2 text-danger" href="#" @click.prevent="handleLogout">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const user = ref({});

onMounted(() => {
    // Pull user data from localStorage
    const savedUser = localStorage.getItem('user_data') || sessionStorage.getItem('user_data');
    if (savedUser) {
        user.value = JSON.parse(savedUser);
    }
});

// Computed property to get First and Last initials (e.g., John Doe -> JD)
const userInitials = computed(() => {
    const first = user.value.first_name ? user.value.first_name.charAt(0) : '';
    const last = user.value.last_name ? user.value.last_name.charAt(0) : '';
    return (first + last).toUpperCase() || '?';
});

const baseRoute = computed(() => {
    const routes = {
        admin: '/admin',
        college_dean: '/college-dean',
        entrance_examiner: '/entrance',
        instructor: '/instructor',
        student: '/student'
    };

    return routes[user.value.role] || '/login';
});

const clearLocalSession = () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_data');
    sessionStorage.removeItem('auth_token');
    sessionStorage.removeItem('user_data');
    delete axios.defaults.headers.common['Authorization'];
};

const handleLogout = async () => {
    const result = await window.Swal.fire({
        title: 'Logout?',
        text: "Are you sure you want to sign out?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Yes, logout',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !window.Swal.isLoading(),
        preConfirm: async () => {
            try {
                await axios.post('/api/logout');
            } catch (error) {
                console.error('Logout API failed:', error);
            }
            return true;
        }
    });

    if (result.isConfirmed) {
        clearLocalSession();
        await router.push('/login');
        window.Toast.fire({ icon: 'success', title: 'Signed out' });
    }
};
</script>

<style scoped>
/* Your existing styles... */
.top-nav {
    height: 70px;
    z-index: 1030;
    width: 100%;
}

.logo-box {
    width: 52px;
    height: 52px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.logo-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar {
    width: 35px;
    height: 35px;
    background: #10b981;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: bold;
}
</style>
