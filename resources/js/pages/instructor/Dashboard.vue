<template>
    <div class="dashboard-container">
        <div class="content-header mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold text-dark">Welcome back, Instructor! 👋</h1>
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
                        <button
                            class="btn btn-scan d-flex align-items-center gap-3 px-5 py-3 rounded-3 shadow"
                            :disabled="isScanning"
                            @click="openScanPicker"
                        >
                            <i class="bi bi-upload fs-2"></i>
                            <span class="fs-4 fw-bold">{{ isScanning ? 'PROCESSING...' : 'UPLOAD ANSWER SHEET' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <input
            ref="singleInputRef"
            type="file"
            class="d-none"
            accept="image/*"
            @change="onSingleSelected"
        />
        <input
            ref="folderInputRef"
            type="file"
            class="d-none"
            accept="image/*"
            multiple
            webkitdirectory
            directory
            @change="onFolderSelected"
        />

        <div class="row g-4 mb-4">
            <div class="col-md-4" v-for="(stat, index) in stats" :key="index">
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
                <a href="#" class="text-emerald text-decoration-none small fw-bold">View All</a>
            </div>
            <div class="card-body p-0">
                <div v-if="!activities.length" class="p-4 text-center text-muted">
                    No recent activity.
                </div>
                <div v-else v-for="a in activities" :key="a.id" class="p-4 border-bottom d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3 bg-emerald-light text-emerald">
                        <i :class="a.icon"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark">{{ a.title }}</div>
                        <div class="small text-muted">{{ a.time }}</div>
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
let statsRefreshTimer = null;

const stats = ref([
    { key: 'total_students', label: 'Total Students', value: '0', icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald', route: '/instructor/students' },
    { key: 'subjects', label: 'Subjects', value: '0', icon: 'bi-book-fill', colorClass: 'bg-info-subtle text-info', route: '/instructor/subjects' },
    { key: 'passing_rate', label: 'Passing Rate', value: '0%', icon: 'bi-graph-up-arrow', colorClass: 'bg-emerald-light text-emerald', route: '/instructor/reports' }
]);

const activities = ref([]);

const formatActivityTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString();
};

const loadStats = async () => {
    try {
        const { data } = await axios.get('/api/instructor/dashboard/stats');
        stats.value.forEach((stat) => {
            if (stat.key === 'total_students') {
                stat.value = Number(data.total_students || 0).toLocaleString();
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
        const recent = Array.isArray(data?.recent_activities) ? data.recent_activities : [];
        const extractSubjectName = (value) => {
            if (!value) return '';
            const match = String(value).match(/subject \"([^\"]+)\"/i);
            return match?.[1] || '';
        };

        activities.value = recent.map((item) => {
            const meta = item?.meta || {};
            const subjectName = meta.subject_name
                || extractSubjectName(item?.description)
                || extractSubjectName(item?.title)
                || 'Subject';
            const studentName = meta.student_name || 'Student';
            const actionType = item?.action_type;

            let title = item?.title || item?.description || 'Activity';
            if (actionType === 'instructor_subject_assigned') {
                title = `You got assigned to a new subject "${subjectName}"`;
            } else if (actionType === 'student_subject_assigned') {
                title = `You got a new student "${studentName}" in "${subjectName}"`;
            }

            return {
                id: item?.id ?? `${actionType || 'activity'}-${item?.created_at || Math.random()}`,
                title,
                time: formatActivityTime(item?.created_at),
                icon: actionType === 'instructor_subject_assigned' ? 'bi-journal-plus' : 'bi-person-plus',
            };
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Failed to load dashboard stats',
            text: 'Please try refreshing the page.',
            confirmButtonColor: '#ef4444'
        });
    }
};

const goToStat = (stat) => {
    if (!stat?.route) return;
    router.push(stat.route);
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

        const endpoint = '/api/instructor/omr/check-term';

        const { data } = await axios.post(endpoint, formData, {
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
                router.push('/instructor/reports');
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
