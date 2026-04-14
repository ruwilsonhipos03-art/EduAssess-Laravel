<template>
    <div class="dashboard-container">
        <div class="content-header mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold text-dark">Welcome back, {{ firstName }}!</h1>
                <p class="text-muted mb-0">Here's what's happening today.</p>
            </div>

            <div class="text-end d-none d-md-block">
                <div class="fw-bold text-dark">{{ currentTime }}</div>
                <div class="small text-muted">System Status: Online</div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3" v-for="(stat, index) in stats" :key="index">
                <button
                    type="button"
                    class="card border-0 shadow-sm p-4 rounded-4 stat-card h-100 bg-white text-start"
                    :class="{ 'stat-clickable': Boolean(stat.route) }"
                    :disabled="!stat.route"
                    @click="goToStat(stat)"
                >
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div :class="['stat-icon', stat.colorClass]">
                            <i :class="stat.icon"></i>
                        </div>
                    </div>
                    <div class="stat-value h2 fw-bold mb-1">{{ stat.value }}</div>
                    <div class="stat-label text-muted fw-medium">{{ stat.label }}</div>
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">Recent Activity</h5>
                <button class="btn btn-sm btn-link text-decoration-none fw-bold text-emerald" @click="goToReports">See All</button>
            </div>

            <div class="card-body p-0">
                <div v-if="activities.length === 0" class="p-4 text-muted">No activity yet.</div>

                <div v-for="a in activities" :key="a.id" class="p-4 border-bottom d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3 bg-emerald-light text-emerald">
                        <i :class="actionIcon(a.action_type)"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark">{{ a.title }}</div>
                        <div class="small text-muted">{{ a.description }}</div>
                        <div class="small text-muted">{{ formatDateTime(a.created_at) }} | {{ prettyRole(a.actor_role) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const router = useRouter();
const currentTime = ref('');
const firstName = ref('Admin');
const activities = ref([]);
let statsRefreshTimer = null;

const stats = ref([
    { key: 'total_employees', label: 'Total Employees', value: '0', icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald', route: '/admin/users' },
    { key: 'total_students', label: 'Total Students', value: '0', icon: 'bi-person-badge-fill', colorClass: 'bg-emerald-light text-emerald', route: '/admin/users' },
    { key: 'colleges', label: 'Colleges', value: '0', icon: 'bi-building-fill', colorClass: 'bg-emerald-light text-emerald', route: '/admin/colleges' },
    { key: 'programs', label: 'Programs', value: '0', icon: 'bi-journal-bookmark-fill', colorClass: 'bg-emerald-light text-emerald', route: '/admin/programs' }
]);

const normalizeCount = (payload, ...keys) => {
    for (const key of keys) {
        const value = payload?.[key];
        const parsed = Number(value);

        if (Number.isFinite(parsed)) {
            return parsed.toLocaleString();
        }
    }

    return '0';
};

const prettyRole = (role) => {
    if (!role) return 'N/A';
    return String(role).replaceAll('_', ' ').toUpperCase();
};

const actionIcon = (action) => {
    const map = {
        student_registered: 'bi bi-person-plus-fill',
        student_created: 'bi bi-person-plus-fill',
        student_updated: 'bi bi-person-gear',
        student_deleted: 'bi bi-person-dash-fill',
        employee_created: 'bi bi-person-workspace',
        employee_updated: 'bi bi-person-gear',
        employee_deleted: 'bi bi-person-dash-fill',
        screening_exam_taken: 'bi bi-person-check-fill',
        exam_created: 'bi bi-file-earmark-plus-fill',
        exam_updated: 'bi bi-pencil-square',
        exam_deleted: 'bi bi-trash-fill',
        student_subject_assigned: 'bi bi-link-45deg',
        student_subject_unassigned: 'bi bi-unlink',
    };

    return map[action] || 'bi bi-activity';
};

const formatDateTime = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString();
};

const goToReports = () => {
    router.push('/admin/reports');
};

const goToStat = (stat) => {
    if (!stat?.route) return;
    router.push(stat.route);
};

const loadStats = async () => {
    try {
        const { data } = await axios.get('/api/admin/dashboard/stats');
        stats.value.forEach((stat) => {
            if (stat.key === 'total_employees') {
                stat.value = normalizeCount(data, 'total_employees', 'employees');
                return;
            }
            if (stat.key === 'total_students') {
                stat.value = normalizeCount(data, 'total_students', 'students');
                return;
            }
            if (stat.key === 'colleges') {
                stat.value = normalizeCount(data, 'colleges', 'total_colleges');
                return;
            }
            if (stat.key === 'programs') {
                stat.value = normalizeCount(data, 'programs', 'total_programs');
            }
        });

        activities.value = Array.isArray(data?.recent_activities) ? data.recent_activities : [];
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Failed to load dashboard stats',
            text: 'Please try refreshing the page.',
            confirmButtonColor: '#ef4444'
        });
    }
};

onMounted(() => {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentTime.value = new Date().toLocaleDateString(undefined, options);

    const savedUser = localStorage.getItem('user_data');
    if (savedUser) {
        const parsedUser = JSON.parse(savedUser);
        firstName.value = parsedUser?.first_name || 'Admin';
    }

    loadStats();
    statsRefreshTimer = setInterval(loadStats, 30000);
});

onBeforeUnmount(() => {
    if (statsRefreshTimer) {
        clearInterval(statsRefreshTimer);
    }
});
</script>

<style scoped>
.text-emerald {
    color: #10b981;
}

.bg-emerald-light {
    background-color: #ecfdf5;
}

.stat-card {
    transition: transform 0.2s ease-in-out;
    box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.08) !important;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-clickable {
    cursor: pointer;
    border: none;
    background: transparent;
    display: block;
    width: 100%;
    margin: 0;
    font: inherit;
    line-height: inherit;
}

.stat-clickable::-moz-focus-inner {
    border: 0;
    padding: 0;
}

.stat-clickable:disabled {
    cursor: default;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.action-card {
    border-left: 6px solid #10b981 !important;
}

.btn-scan {
    background-color: #10b981;
    color: white;
    border: none;
    transition: all 0.3s ease;
}

.btn-scan:hover {
    background-color: #059669;
    transform: scale(1.02);
    color: white;
}

.btn-scan:active {
    transform: scale(0.98);
}
</style>
