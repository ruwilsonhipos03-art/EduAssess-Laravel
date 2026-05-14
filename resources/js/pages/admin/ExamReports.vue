<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold mb-1 text-dark">Exam Reports</h4>
                        <p class="text-muted small mb-0">Review all exams created across the system.</p>
                    </div>
                    <button class="btn btn-emerald fw-bold px-4" :disabled="loading" @click="loadExamReports">
                        <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-light border-bottom p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input
                            v-model.trim="filters.search"
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="Title, type, program, examiner..."
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Exam Type</label>
                        <select v-model="filters.examType" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option v-for="type in examTypeOptions" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Program</label>
                        <select v-model="filters.programName" class="form-select form-select-sm">
                            <option value="">All Programs</option>
                            <option v-for="program in programOptions" :key="program" :value="program">{{ program }}</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Order</label>
                        <select v-model="filters.sortBy" class="form-select form-select-sm">
                            <option value="newest">Recent to Oldest</option>
                            <option value="oldest">Oldest to Recent</option>
                            <option value="title-asc">Title A-Z</option>
                            <option value="title-desc">Title Z-A</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">No.</th>
                                <th>Exam Title</th>
                                <th>Type</th>
                                <th>Program</th>
                                <th>Examiner</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="6" class="text-center py-4 text-muted">Loading exam reports...</td>
                            </tr>
                            <tr v-else-if="filteredRows.length === 0">
                                <td colspan="6" class="text-center py-4 text-muted">No exam reports found.</td>
                            </tr>
                            <tr v-else v-for="(row, index) in filteredRows" :key="row.id" class="clickable-row" @click="openExamDetail(row)">
                                <td class="ps-3">{{ index + 1 }}</td>
                                <td class="fw-semibold">{{ row.exam_title || '-' }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ row.exam_type || 'N/A' }}</span>
                                </td>
                                <td>{{ row.program_name || 'N/A' }}</td>
                                <td>{{ row.examiner_name || 'N/A' }}</td>
                                <td>{{ formatDate(row.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="isDetailOpen" class="popup-overlay" @click.self="closeExamDetail">
            <div class="popup-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">Exam Takers</h5>
                        <div class="text-muted small">
                            {{ selectedExam?.exam_title || '-' }} | {{ selectedExam?.exam_type || '-' }} | {{ selectedExam?.program_name || 'N/A' }}
                        </div>
                    </div>
                    <button type="button" class="btn-close" @click="closeExamDetail"></button>
                </div>

                <div v-if="detailLoading" class="text-center text-muted py-4">Loading exam takers...</div>
                <div v-else-if="detailError" class="alert alert-danger py-2 mb-0">{{ detailError }}</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No.</th>
                                <th>Student</th>
                                <th>Status</th>
                                <th class="text-end">Score</th>
                                <th class="text-end">Analysis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="detailRows.length === 0">
                                <td colspan="5" class="text-center py-4 text-muted">No students found for this exam.</td>
                            </tr>
                            <tr v-for="(student, index) in detailRows" :key="student.answer_sheet_id">
                                <td>{{ index + 1 }}</td>
                                <td class="fw-semibold">{{ student.student_full_name }}</td>
                                <td>
                                    <span class="badge" :class="student.status === 'checked' ? 'text-bg-success' : 'text-bg-warning'">
                                        {{ student.status || 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold">{{ student.score ?? 'Pending' }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" @click="toggleAnalysis(student.answer_sheet_id)">
                                        {{ activeAnalysisId === student.answer_sheet_id ? 'Hide' : 'Show' }} Analysis
                                    </button>
                                </td>
                            </tr>
                            <tr v-for="student in detailRows" :key="`analysis-${student.answer_sheet_id}`" v-show="activeAnalysisId === student.answer_sheet_id">
                                <td colspan="5" class="analysis-cell">
                                    <div class="analysis-shell">
                                        <div class="analysis-topbar">
                                            <div>
                                                <div class="analysis-eyebrow">Student Analysis</div>
                                                <div class="analysis-title">{{ student.student_full_name }}</div>
                                            </div>
                                            <div class="analysis-score-badge">
                                                <span class="analysis-score-label">Overall Score</span>
                                                <span class="analysis-score-value">{{ student.score ?? 'Pending' }}</span>
                                            </div>
                                        </div>

                                        <div class="analysis-summary-grid">
                                            <div class="summary-card summary-card-emerald">
                                                <div class="summary-label">Correct Answers</div>
                                                <div class="summary-value">{{ (student.correct_questions || []).length }}</div>
                                            </div>
                                            <div class="summary-card summary-card-red">
                                                <div class="summary-label">Wrong Answers</div>
                                                <div class="summary-value">{{ (student.incorrect_questions || []).length }}</div>
                                            </div>
                                            <div class="summary-card summary-card-slate">
                                                <div class="summary-label">Subjects</div>
                                                <div class="summary-value">{{ (student.subject_scores || []).length }}</div>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-lg-4">
                                                <div class="analysis-panel">
                                                    <div class="panel-heading">Per Subject Scores</div>
                                                    <div class="subject-score-stack">
                                                        <div
                                                            v-for="item in student.subject_scores || []"
                                                            :key="`${student.answer_sheet_id}-${item.subject}`"
                                                            class="subject-score-card"
                                                        >
                                                            <span class="subject-name">{{ item.subject }}</span>
                                                            <span class="subject-score">{{ item.score }}</span>
                                                        </div>
                                                        <div v-if="!(student.subject_scores || []).length" class="empty-analysis">
                                                            No subject scores available.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="analysis-panel">
                                                    <div class="panel-heading panel-heading-success">Correct Items</div>
                                                    <div class="item-chip-wrap">
                                                        <span
                                                            v-for="q in student.correct_questions || []"
                                                            :key="`c-${student.answer_sheet_id}-${q}`"
                                                            class="item-chip item-chip-success"
                                                        >
                                                            {{ q }}
                                                        </span>
                                                        <div v-if="!(student.correct_questions || []).length" class="empty-analysis">
                                                            No correct items recorded.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="analysis-panel">
                                                    <div class="panel-heading panel-heading-danger">Wrong Items</div>
                                                    <div class="item-chip-wrap">
                                                        <span
                                                            v-for="q in student.incorrect_questions || []"
                                                            :key="`w-${student.answer_sheet_id}-${q}`"
                                                            class="item-chip item-chip-danger"
                                                        >
                                                            {{ q }}
                                                        </span>
                                                        <div v-if="!(student.incorrect_questions || []).length" class="empty-analysis">
                                                            No wrong items recorded.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const loading = ref(false);
const rows = ref([]);
const isDetailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const selectedExam = ref(null);
const detailRows = ref([]);
const activeAnalysisId = ref(null);

const filters = ref({
    search: '',
    examType: '',
    programName: '',
    sortBy: 'newest',
});

const examTypeOptions = computed(() => {
    return [...new Set(rows.value.map((row) => row.exam_type).filter(Boolean))]
        .sort((a, b) => String(a).localeCompare(String(b)));
});

const programOptions = computed(() => {
    return [...new Set(rows.value.map((row) => row.program_name).filter(Boolean))]
        .sort((a, b) => String(a).localeCompare(String(b)));
});

const filteredRows = computed(() => {
    let result = [...rows.value];

    if (filters.value.search) {
        const q = filters.value.search.toLowerCase();
        result = result.filter((row) => {
            const text = [
                row.exam_title,
                row.exam_type,
                row.program_name,
                row.examiner_name,
            ].map((item) => String(item || '').toLowerCase()).join(' ');

            return text.includes(q);
        });
    }

    if (filters.value.examType) {
        result = result.filter((row) => row.exam_type === filters.value.examType);
    }

    if (filters.value.programName) {
        result = result.filter((row) => row.program_name === filters.value.programName);
    }

    result.sort((a, b) => {
        if (filters.value.sortBy === 'oldest') {
            return new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime();
        }

        if (filters.value.sortBy === 'title-asc') {
            return String(a.exam_title || '').localeCompare(String(b.exam_title || ''));
        }

        if (filters.value.sortBy === 'title-desc') {
            return String(b.exam_title || '').localeCompare(String(a.exam_title || ''));
        }

        return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
    });

    return result;
});

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const loadExamReports = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/exam-reports');
        rows.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        rows.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load exam reports',
            text: error?.response?.data?.message || 'Please refresh and try again.',
        });
    } finally {
        loading.value = false;
    }
};

const openExamDetail = async (row) => {
    isDetailOpen.value = true;
    detailLoading.value = true;
    detailError.value = '';
    selectedExam.value = row;
    detailRows.value = [];

    try {
        const { data } = await axios.get(`/api/admin/exam-reports/${row.id}`);
        selectedExam.value = data?.data?.exam || row;
        detailRows.value = Array.isArray(data?.data?.students) ? data.data.students : [];
    } catch (error) {
        detailError.value = error?.response?.data?.message || 'Failed to load exam takers.';
    } finally {
        detailLoading.value = false;
    }
};

const closeExamDetail = () => {
    isDetailOpen.value = false;
    detailLoading.value = false;
    detailError.value = '';
    selectedExam.value = null;
    detailRows.value = [];
    activeAnalysisId.value = null;
};

const toggleAnalysis = (answerSheetId) => {
    activeAnalysisId.value = activeAnalysisId.value === answerSheetId ? null : answerSheetId;
};

onMounted(loadExamReports);
</script>

<style scoped>
.btn-emerald {
    background-color: #10b981;
    color: white;
}

.btn-emerald:hover {
    background-color: #059669;
}

.clickable-row {
    cursor: pointer;
}

.popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 16px;
}

.popup-card {
    width: min(1080px, 100%);
    max-height: 88vh;
    overflow: auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.2);
    padding: 24px;
}

.analysis-cell {
    background: linear-gradient(180deg, #f8fafc 0%, #eef6f2 100%);
}

.analysis-shell {
    border: 1px solid #dbe7df;
    border-radius: 18px;
    background: #ffffff;
    padding: 18px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
}

.analysis-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}

.analysis-eyebrow {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
}

.analysis-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.analysis-score-badge {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    padding: 10px 14px;
    border-radius: 14px;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid #a7f3d0;
    min-width: 140px;
}

.analysis-score-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #047857;
}

.analysis-score-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #065f46;
}

.analysis-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.summary-card {
    border-radius: 16px;
    padding: 14px 16px;
    border: 1px solid transparent;
}

.summary-card-emerald {
    background: #ecfdf5;
    border-color: #bbf7d0;
}

.summary-card-red {
    background: #fef2f2;
    border-color: #fecaca;
}

.summary-card-slate {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.summary-label {
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
}

.summary-value {
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
}

.analysis-panel {
    height: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 16px;
    background: #fcfdfd;
}

.panel-heading {
    font-size: 0.88rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 12px;
}

.panel-heading-success {
    color: #15803d;
}

.panel-heading-danger {
    color: #b91c1c;
}

.subject-score-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.subject-score-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.subject-name {
    font-size: 0.92rem;
    font-weight: 600;
    color: #334155;
}

.subject-score {
    min-width: 44px;
    text-align: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: #0f172a;
    color: #fff;
    font-weight: 700;
    font-size: 0.88rem;
}

.item-chip-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    max-height: 220px;
    overflow: auto;
    align-content: flex-start;
}

.item-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 42px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.84rem;
    font-weight: 700;
    border: 1px solid transparent;
}

.item-chip-success {
    background: #ecfdf5;
    color: #15803d;
    border-color: #bbf7d0;
}

.item-chip-danger {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #fecaca;
}

.empty-analysis {
    font-size: 0.86rem;
    color: #64748b;
    padding: 6px 0;
}

@media (max-width: 767.98px) {
    .analysis-topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .analysis-score-badge {
        width: 100%;
        align-items: flex-start;
    }

    .analysis-summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>
