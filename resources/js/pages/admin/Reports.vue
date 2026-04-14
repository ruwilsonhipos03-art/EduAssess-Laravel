<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center g-3">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">Activity Feed</h4>
                        <p class="text-muted small mb-0">Track who did what across exams, assignments, and registration.</p>
                    </div>
                    <div class="col-auto">
                        <button @click="loadActivities" class="btn btn-emerald fw-bold px-4" :disabled="isLoading">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-light border-bottom p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input
                            type="text"
                            v-model.trim="filters.search"
                            class="form-control form-control-sm"
                            placeholder="Search actor, title, or details"
                            @input="onFiltersChanged"
                        >
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Action</label>
                        <select v-model="filters.action_type" class="form-select form-select-sm" @change="onFiltersChanged">
                            <option value="">All Actions</option>
                            <option v-for="action in actionOptions" :key="action" :value="action">{{ prettyAction(action) }}</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Role</label>
                        <select v-model="filters.actor_role" class="form-select form-select-sm" @change="onFiltersChanged">
                            <option value="">All Roles</option>
                            <option v-for="role in roleOptions" :key="role" :value="role">{{ prettyRole(role) }}</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">From</label>
                        <input type="date" v-model="filters.date_from" class="form-control form-control-sm" @change="onFiltersChanged">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">To</label>
                        <input type="date" v-model="filters.date_to" class="form-control form-control-sm" @change="onFiltersChanged">
                    </div>

                    <div class="col-md-1">
                        <button @click="resetFilters" class="btn btn-link btn-sm text-decoration-none text-muted fw-bold">RESET</button>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">When</th>
                                <th class="py-3 text-secondary small fw-bold">Actor</th>
                                <th class="py-3 text-secondary small fw-bold">Role</th>
                                <th class="py-3 text-secondary small fw-bold">Action</th>
                                <th class="pe-4 py-3 text-secondary small fw-bold">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="5" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                    <div class="mt-2 text-muted small">Loading activity...</div>
                                </td>
                            </tr>

                            <template v-else>
                                <tr v-for="item in activities" :key="item.id">
                                    <td class="ps-4 small text-muted">{{ formatDateTime(item.created_at) }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ item.actor_name || 'System' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-light text-dark border px-3">{{ prettyRole(item.actor_role) }}</span>
                                    </td>
                                    <td>
                                        <span :class="['badge rounded-pill px-3', actionBadgeClass(item.action_type)]">
                                            <i :class="[actionIcon(item.action_type), 'me-1']"></i>{{ prettyAction(item.action_type) }}
                                        </span>
                                    </td>
                                    <td class="pe-4">
                                        <div class="fw-semibold text-dark">{{ item.title }}</div>
                                        <div class="small text-muted">{{ item.description }}</div>
                                    </td>
                                </tr>

                                <tr v-if="activities.length === 0">
                                    <td colspan="5" class="text-center py-5 text-muted">No activities found.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-white border-top p-3" v-if="meta.total > meta.per_page">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Showing {{ rangeStart }} to {{ rangeEnd }} of {{ meta.total }} records
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item" :class="{ disabled: meta.current_page <= 1 }">
                                <button class="page-link" @click="goToPage(meta.current_page - 1)">Previous</button>
                            </li>
                            <li class="page-item" :class="{ disabled: meta.current_page >= meta.last_page }">
                                <button class="page-link" @click="goToPage(meta.current_page + 1)">Next</button>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const isLoading = ref(false);
const activities = ref([]);
const allActionOptions = ref([
    'student_registered',
    'student_created',
    'student_updated',
    'student_deleted',
    'employee_created',
    'employee_updated',
    'employee_deleted',
    'screening_exam_taken',
    'exam_created',
    'exam_updated',
    'exam_deleted',
    'student_subject_assigned',
    'student_subject_unassigned',
]);
const allRoleOptions = ref(['admin', 'college_dean', 'instructor', 'student']);

const meta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

const filters = reactive({
    search: '',
    action_type: '',
    actor_role: '',
    date_from: '',
    date_to: '',
});

const actionOptions = computed(() => allActionOptions.value);
const roleOptions = computed(() => allRoleOptions.value);

const rangeStart = computed(() => {
    if (meta.total === 0) return 0;
    return ((meta.current_page - 1) * meta.per_page) + 1;
});

const rangeEnd = computed(() => {
    if (meta.total === 0) return 0;
    return Math.min(meta.current_page * meta.per_page, meta.total);
});

const prettyRole = (role) => {
    if (!role) return 'N/A';
    return String(role).replaceAll('_', ' ').toUpperCase();
};

const prettyAction = (action) => {
    if (!action) return 'N/A';
    return String(action).replaceAll('_', ' ').replace(/\b\w/g, (m) => m.toUpperCase());
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

const actionBadgeClass = (action) => {
    if (String(action).includes('created') || String(action).includes('registered') || String(action).includes('assigned')) {
        return 'bg-success-subtle text-success border border-success-subtle';
    }

    if (String(action).includes('updated')) {
        return 'bg-warning-subtle text-warning border border-warning-subtle';
    }

    if (String(action).includes('deleted') || String(action).includes('unassigned')) {
        return 'bg-danger-subtle text-danger border border-danger-subtle';
    }

    return 'bg-light text-dark border';
};

const formatDateTime = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString();
};

const updateOptionsFromRows = (rows) => {
    const actions = new Set(allActionOptions.value);
    const roles = new Set(allRoleOptions.value);

    rows.forEach((row) => {
        if (row?.action_type) actions.add(String(row.action_type));
        if (row?.actor_role) roles.add(String(row.actor_role));
    });

    allActionOptions.value = Array.from(actions).sort((a, b) => a.localeCompare(b));
    allRoleOptions.value = Array.from(roles).sort((a, b) => a.localeCompare(b));
};

const loadActivities = async (page = 1) => {
    isLoading.value = true;
    try {
        const params = {
            page,
            per_page: meta.per_page,
        };

        if (filters.search) params.search = filters.search;
        if (filters.action_type) params.action_type = filters.action_type;
        if (filters.actor_role) params.actor_role = filters.actor_role;
        if (filters.date_from) params.date_from = filters.date_from;
        if (filters.date_to) params.date_to = filters.date_to;

        const { data } = await axios.get('/api/admin/activities', { params });
        activities.value = Array.isArray(data?.data) ? data.data : [];

        const payloadMeta = data?.meta || {};
        meta.current_page = Number(payloadMeta.current_page || page);
        meta.last_page = Number(payloadMeta.last_page || 1);
        meta.per_page = Number(payloadMeta.per_page || meta.per_page);
        meta.total = Number(payloadMeta.total || 0);

        updateOptionsFromRows(activities.value);
    } catch (error) {
        activities.value = [];
        meta.current_page = 1;
        meta.last_page = 1;
        meta.total = 0;

        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load activities',
            text: error?.response?.data?.message || 'Please refresh and try again.',
        });
    } finally {
        isLoading.value = false;
    }
};

const onFiltersChanged = () => {
    loadActivities(1);
};

const goToPage = (page) => {
    if (page < 1 || page > meta.last_page || page === meta.current_page) {
        return;
    }

    loadActivities(page);
};

const resetFilters = () => {
    filters.search = '';
    filters.action_type = '';
    filters.actor_role = '';
    filters.date_from = '';
    filters.date_to = '';
    loadActivities(1);
};

onMounted(() => {
    loadActivities(1);
});
</script>

<style scoped>
.btn-emerald {
    background-color: #10b981;
    color: white;
    border: none;
}

.btn-emerald:hover {
    background-color: #059669;
    color: white;
}

.page-link {
    color: #10b981;
    border-color: #dee2e6;
}

.page-link:hover {
    background-color: #ecfdf5;
    color: #059669;
}

.page-item.disabled .page-link {
    color: #6c757d;
}

.text-emerald {
    color: #10b981;
}
</style>
