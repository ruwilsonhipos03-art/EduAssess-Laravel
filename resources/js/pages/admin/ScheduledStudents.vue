<template>
    <div class="container-fluid py-4 scheduled-page">
        <div class="card border-0 shadow-sm mb-4 hero-card">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 align-items-lg-end">
                    <div>
                        <div class="eyebrow mb-2">Admissions Scheduling</div>
                        <h3 class="fw-bold mb-2">Scheduled Students</h3>
                        <p class="text-muted mb-0 hero-copy">
                            View students assigned to entrance or screening schedules, then download a printable list
                            using the selected schedule.
                        </p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-emerald px-4 fw-semibold" @click="loadRows" :disabled="loading">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
                        <button class="btn btn-emerald px-4 fw-semibold" @click="downloadList"
                            :disabled="downloading">
                            <span v-if="downloading" class="spinner-border spinner-border-sm me-2"></span>
                            <i v-else class="bi bi-download me-2"></i>Download List
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-semibold mb-1">Schedule Type</label>
                        <select v-model="filters.schedule_type" class="form-select" @change="onScheduleTypeChanged">
                            <option value="entrance">Entrance</option>
                            <option value="screening">Screening</option>
                        </select>
                    </div>

                    <div class="col-lg-4 col-md-8">
                        <label class="form-label small fw-semibold mb-1">Schedule</label>
                        <select v-model="filters.exam_schedule_id" class="form-select" @change="loadRows">
                            <option value="">All {{ scheduleTypeLabel.toLowerCase() }} schedules</option>
                            <option v-for="schedule in schedules" :key="schedule.id" :value="String(schedule.id)">
                                {{ formatScheduleOption(schedule) }}
                            </option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Month (Current Year)</label>
                        <select v-model="filters.month" class="form-select" @change="loadRows">
                            <option value="">All Months</option>
                            <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                                {{ month.label }}
                            </option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Selected Summary</label>
                        <div class="summary-pill">
                            <span class="summary-count">{{ rows.length }}</span>
                            <span class="summary-label">student{{ rows.length === 1 ? '' : 's' }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="selectedSchedule" class="selected-schedule-card mt-4">
                    <div class="schedule-grid">
                        <div>
                            <div class="schedule-kicker">Printable Schedule</div>
                            <div class="schedule-title">{{ selectedSchedule.label }}</div>
                            <div class="schedule-exam">{{ selectedSchedule.exam_titles || 'Exam title will appear in the download.' }}</div>
                        </div>
                        <div class="schedule-meta">
                            <span class="meta-chip">{{ selectedSchedule.assigned_students }}/{{
                                selectedSchedule.capacity }}</span>
                            <span class="meta-chip meta-chip-soft">{{ scheduleTypeLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                    <div>
                        <h5 class="fw-bold mb-1">Scheduled Student List</h5>
                        <p class="text-muted small mb-0">
                            {{ selectedSchedule ? 'Showing students from the selected schedule.' : 'Showing all scheduled students for the selected type.' }}
                        </p>
                    </div>
                    <div class="text-muted small">
                        {{ selectedSchedule ? 'Download will export the selected schedule.' : 'Download will export all filtered schedules in one PDF file.' }}
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">No.</th>
                                <th>Student</th>
                                <th>Exam</th>
                                <th>Schedule</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="5" class="text-center py-5">
                                    <div class="spinner-border text-emerald"></div>
                                    <div class="small text-muted mt-2">Loading scheduled students...</div>
                                </td>
                            </tr>
                            <tr v-else-if="rows.length === 0">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    No scheduled students found for the current filters.
                                </td>
                            </tr>
                            <tr v-else v-for="(row, index) in rows" :key="row.id">
                                <td class="ps-4 text-muted fw-semibold">{{ index + 1 }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ row.full_name || '-' }}</div>
                                    <div class="small text-muted">{{ row.student_number || '-' }}</div>
                                    <div class="small text-muted">{{ row.email || '-' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ row.exam_title || '-' }}</div>
                                    <div class="small text-muted">{{ row.exam_type || '-' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ row.schedule_label || '-' }}</div>
                                </td>
                                <td>
                                    <span class="status-pill" :class="statusClass(row.schedule_status)">
                                        {{ prettyStatus(row.schedule_status) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const loading = ref(false);
const downloading = ref(false);
const rows = ref([]);
const schedules = ref([]);

const filters = reactive({
    schedule_type: 'entrance',
    exam_schedule_id: '',
    month: '',
});

const scheduleTypeLabel = computed(() => filters.schedule_type === 'screening' ? 'Screening' : 'Entrance');
const monthOptions = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const selectedSchedule = computed(() => (
    schedules.value.find((schedule) => Number(schedule.id) === Number(filters.exam_schedule_id)) || null
));

const formatScheduleOption = (schedule) => {
    const batch = String(schedule?.schedule_name || '').trim();
    const label = String(schedule?.label || '').trim();
    const current = Number(schedule?.assigned_students || 0);
    const capacity = Number(schedule?.capacity || 0);

    if (batch) {
        return `${batch} | ${label} | Current: ${current}/${capacity}`;
    }

    return `${label} | Current: ${current}/${capacity}`;
};

const prettyStatus = (value) => {
    const text = String(value || '').trim();
    if (!text) return 'Unknown';
    return text.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
};

const statusClass = (value) => {
    const text = String(value || '').toLowerCase();
    if (text === 'attended') return 'status-attended';
    if (text === 'missed') return 'status-missed';
    return 'status-scheduled';
};

const loadRows = async () => {
    loading.value = true;
    try {
        const params = {
            schedule_type: filters.schedule_type,
        };

        if (filters.exam_schedule_id) {
            params.exam_schedule_id = Number(filters.exam_schedule_id);
        }
        if (filters.month) {
            params.month = Number(filters.month);
        }

        const { data } = await axios.get('/api/admin/scheduled-students', { params });
        schedules.value = Array.isArray(data?.schedule_options) ? data.schedule_options : [];
        rows.value = Array.isArray(data?.data) ? data.data : [];

        if (filters.exam_schedule_id) {
            const stillExists = schedules.value.some((schedule) => Number(schedule.id) === Number(filters.exam_schedule_id));
            if (!stillExists) {
                filters.exam_schedule_id = '';
            }
        }
    } catch (error) {
        rows.value = [];
        schedules.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load scheduled students',
            text: error?.response?.data?.message || 'Please refresh and try again.',
        });
    } finally {
        loading.value = false;
    }
};

const onScheduleTypeChanged = async () => {
    filters.exam_schedule_id = '';
    await loadRows();
};

const downloadList = async () => {
    if (downloading.value) {
        return;
    }

    const promptResult = await window.Swal?.fire({
        title: 'Enter Semester / Academic Year',
        input: 'text',
        inputLabel: 'This line will appear below the PDF title.',
        inputPlaceholder: 'FIRST SEMESTER, AY 2026-2027',
        inputValue: '',
        showCancelButton: true,
        confirmButtonText: 'Download PDF',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6c757d',
        inputValidator: (value) => {
            if (!String(value || '').trim()) {
                return 'Semester / academic year is required.';
            }

            return undefined;
        },
    });

    if (!promptResult?.isConfirmed) {
        return;
    }

    const semesterLabel = String(promptResult.value || '').trim();

    downloading.value = true;
    try {
        const params = {
            schedule_type: filters.schedule_type,
            semester_label: semesterLabel,
        };

        if (filters.exam_schedule_id) {
            params.exam_schedule_id = Number(filters.exam_schedule_id);
        }
        if (filters.month) {
            params.month = Number(filters.month);
        }

        const response = await axios.get('/api/admin/scheduled-students/download', {
            params,
            responseType: 'blob',
        });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;

        const match = response.headers['content-disposition']?.match(/filename="?([^"]+)"?/i);
        link.download = match ? match[1] : 'scheduled_students.pdf';

        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);

        window.Toast?.fire({
            icon: 'success',
            title: selectedSchedule.value
                ? 'Scheduled student list downloaded.'
                : 'Combined scheduled student PDF downloaded.',
        });
    } catch (error) {
        window.Swal?.fire({
            icon: 'error',
            title: 'Download failed',
            text: error?.response?.data?.message || 'Please check your filters and try again.',
        });
    } finally {
        downloading.value = false;
    }
};

onMounted(loadRows);
</script>

<style scoped>
.scheduled-page {
    --emerald: #0f9f6e;
    --emerald-deep: #0b6b4c;
    --ink: #17324d;
    --paper: #f5f8fb;
}

.hero-card {
    background:
        radial-gradient(circle at top left, rgba(15, 159, 110, 0.16), transparent 34%),
        linear-gradient(135deg, #ffffff 0%, #f5fbf8 55%, #eef8ff 100%);
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--emerald-deep);
    font-weight: 700;
}

.hero-copy {
    max-width: 660px;
}

.btn-emerald {
    background: var(--emerald);
    border: none;
    color: #fff;
}

.btn-emerald:hover {
    background: var(--emerald-deep);
    color: #fff;
}

.btn-outline-emerald {
    border: 1px solid rgba(15, 159, 110, 0.35);
    color: var(--emerald-deep);
    background: #fff;
}

.btn-outline-emerald:hover {
    background: #ebfff7;
    color: var(--emerald-deep);
}

.summary-pill {
    min-height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #effaf4 0%, #eef6ff 100%);
    border: 1px solid #d8e7dd;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.summary-count {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--ink);
}

.summary-label {
    font-size: 0.92rem;
    color: #4b5563;
}

.selected-schedule-card {
    border: 1px solid #d8e7dd;
    border-radius: 18px;
    background: linear-gradient(135deg, #fff9ec 0%, #ffffff 45%, #f1faf6 100%);
    padding: 18px 20px;
}

.schedule-grid {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
}

.schedule-kicker {
    font-size: 0.78rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #9a3412;
    font-weight: 800;
    margin-bottom: 5px;
}

.schedule-title {
    font-size: 1.08rem;
    font-weight: 800;
    color: var(--ink);
    margin-bottom: 4px;
}

.schedule-exam {
    color: #4b5563;
    font-size: 0.92rem;
}

.schedule-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.meta-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    background: #0f172a;
    color: #fff;
    font-size: 0.85rem;
    font-weight: 700;
}

.meta-chip-soft {
    background: #ebfff7;
    color: var(--emerald-deep);
    border: 1px solid #b8e0ca;
}

table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid #e5e7eb;
    padding-top: 1rem;
    padding-bottom: 1rem;
}

table tbody td {
    padding-top: 1rem;
    padding-bottom: 1rem;
    border-color: #eef2f7;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.4rem 0.78rem;
    font-size: 0.8rem;
    font-weight: 700;
}

.status-scheduled {
    background: #eff6ff;
    color: #1d4ed8;
}

.status-attended {
    background: #ecfdf5;
    color: #047857;
}

.status-missed {
    background: #fef2f2;
    color: #b91c1c;
}

@media (max-width: 767.98px) {
    .schedule-grid {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
