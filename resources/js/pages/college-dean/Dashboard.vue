<template>
    <div class="dashboard-container">
        <div class="content-header mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold text-dark">Welcome back, College Dean! 👋</h1>
                <p class="text-muted mb-0">Manage your exams and students here.</p>
            </div>

            <div class="text-end d-none d-md-block">
                <div class="fw-bold text-dark">{{ currentTime }}</div>
                <div class="small text-muted">System Status: Online</div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="action-card card border-0 shadow-sm p-4 rounded-4 bg-white">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="action-text">
                            <h3 class="fw-bold mb-1">Ready to check exams?</h3>
                            <p class="text-muted mb-0">Upload answer sheet images to check and process results.
                            </p>
                        </div>
                        <button class="btn btn-scan d-flex align-items-center gap-3 px-5 py-3 rounded-3 shadow"
                            :disabled="isScanning" @click="openScanPicker">
                            <i class="bi bi-upload fs-2"></i>
                            <span class="fs-4 fw-bold">{{ isScanning ? 'PROCESSING...' : 'UPLOAD ANSWER SHEET' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <input ref="singleInputRef" type="file" class="d-none" accept="image/*" @change="onSingleSelected" />
        <input ref="folderInputRef" type="file" class="d-none" accept="image/*" multiple webkitdirectory directory
            @change="onFolderSelected" />

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

const currentTime = ref('');
const isScanning = ref(false);
const singleInputRef = ref(null);
const folderInputRef = ref(null);
const router = useRouter();
const activities = ref([]);
let statsRefreshTimer = null;

const stats = ref([
    { key: 'exams_created', label: 'Exams Created', value: '0', icon: 'bi-file-earmark-plus-fill', colorClass: 'bg-emerald-light text-emerald', route: '' },
    { key: 'total_examinees', label: 'Total Examinees', value: '0', icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald', route: '/college-dean/students' },
    { key: 'subjects', label: 'Subjects', value: '0', icon: 'bi-book-fill', colorClass: 'bg-info-subtle text-info', route: '/college-dean/subjects' },
    { key: 'passing_rate', label: 'Passing Rate', value: '0%', icon: 'bi-graph-up-arrow', colorClass: 'bg-warning-subtle text-warning', route: '/college-dean/normal/reports' }
]);

const prettyRole = (role) => {
    if (!role) return 'N/A';
    return String(role).replaceAll('_', ' ').toUpperCase();
};

const actionIcon = (action) => {
    const map = {
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
    router.push('/college-dean/reports');
};

const goToStat = (stat) => {
    if (!stat?.route) return;
    router.push(stat.route);
};

const loadStats = async () => {
    try {
        const { data } = await axios.get('/api/college_dean/dashboard/stats');
        stats.value.forEach((stat) => {
            if (stat.key === 'exams_created') {
                stat.value = Number(data.exams_created || 0).toLocaleString();
                return;
            }
            if (stat.key === 'total_examinees') {
                stat.value = Number(data.total_examinees || 0).toLocaleString();
                return;
            }
            if (stat.key === 'subjects') {
                stat.value = Number(data.subjects || 0).toLocaleString();
                return;
            }
            if (stat.key === 'passing_rate') {
                stat.value = `${Number(data.passing_rate || 0).toFixed(2)}%`;
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

const openScanPicker = async () => {
    if (isScanning.value) return;

    const result = await Swal.fire({
        title: 'Select Upload Type',
        text: 'Choose how you want to check answer sheets.',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Single Image',
        denyButtonText: 'Folder of Images',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#10b981',
        denyButtonColor: '#0ea5e9',
    });

    if (result.isConfirmed) {
        singleInputRef.value?.click();
    } else if (result.isDenied) {
        folderInputRef.value?.click();
    }
};

const onSingleSelected = async (event) => {
    const file = event?.target?.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('image', file);
    await submitOmr(formData);
    event.target.value = '';
};

const onFolderSelected = async (event) => {
    const files = Array.from(event?.target?.files || []);
    if (!files.length) return;

    const formData = new FormData();
    files.forEach((file) => formData.append('images[]', file));
    await submitOmr(formData);
    event.target.value = '';
};

const submitOmr = async (formData) => {
    isScanning.value = true;
    try {
        Swal.fire({
            title: 'Processing...',
            text: 'Checking uploaded image(s). Please wait.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        const { data } = await axios.post('/api/entrance/omr/check', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const processed = Array.isArray(data?.processed) ? data.processed : [];
        const successCount = processed.filter((item) => item?.success).length;
        const failed = processed.filter((item) => !item?.success);
        const failureText = failed
            .slice(0, 3)
            .map((item) => `${item?.file || 'file'}: ${item?.message || 'Failed to process.'}`)
            .join('\n');
        const summaryText = data?.message || `Processed ${successCount} file(s).`;
        const detailText = failureText ? `${summaryText}\n\n${failureText}` : summaryText;

        await Swal.fire({
            icon: successCount > 0 ? 'success' : 'warning',
            title: 'Scanning Completed',
            text: detailText,
            showCancelButton: true,
            confirmButtonText: 'Open Reports',
            cancelButtonText: 'Close',
            confirmButtonColor: '#10b981',
        }).then((res) => {
            if (res.isConfirmed) {
                router.push('/college-dean/entrance/reports');
            }
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Scanning failed',
            text: error?.response?.data?.message || 'Could not process uploaded file(s).',
            confirmButtonColor: '#ef4444',
        });
    } finally {
        isScanning.value = false;
    }
};

onMounted(() => {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentTime.value = new Date().toLocaleDateString(undefined, options);
    loadStats();
    statsRefreshTimer = setInterval(loadStats, 30000);
});

onBeforeUnmount(() => {
    if (statsRefreshTimer) {
        clearInterval(statsRefreshTimer);
        statsRefreshTimer = null;
    }
});
</script>

<style scoped>
/* Emerald UI Colors */
.text-emerald {
    color: #10b981;
}

.bg-emerald-light {
    background-color: #ecfdf5;
}

/* Dashboard Card Animations */
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

/* Custom Warning/Info states for PH dashboard */
.bg-warning-subtle {
    background-color: #fffbeb;
}

.text-warning {
    color: #f59e0b;
}

.bg-info-subtle {
    background-color: #eff6ff;
}

.text-info {
    color: #3b82f6;
}
</style>
