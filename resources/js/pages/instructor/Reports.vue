<template>
    <div class="page-container">
        <h3 class="fw-bold mb-4">Entrance Examiner Reports</h3>

        <div class="card border-0 shadow-sm p-4 rounded-4 mb-3 no-print">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter by Exam Title</label>
                    <select v-model="filters.examTitle" class="form-select">
                        <option value="">All Exams</option>
                        <option v-for="title in examTitles" :key="title" :value="title">{{ title }}</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Search Student Name</label>
                    <input
                        v-model.trim="filters.name"
                        type="text"
                        class="form-control"
                        placeholder="Lastname, Firstname..."
                    />
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Sort By</label>
                    <select v-model="filters.sortBy" class="form-select">
                        <option value="student_full_name">Name</option>
                        <option value="exam_name">Exam</option>
                        <option value="math">Math</option>
                        <option value="english">English</option>
                        <option value="science">Science</option>
                        <option value="social_science">Social Science</option>
                        <option value="total">Total</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Order</label>
                    <select v-model="filters.sortOrder" class="form-select">
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-success w-100" :disabled="loading || filteredRows.length === 0" @click="downloadPrintablePdf">
                        <i class="bi bi-download me-2"></i>Download PDF
                    </button>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="print-only print-sheet">
                <div class="print-title">{{ printExamTitle }}</div>
                <div class="print-sub">Type of Exam: {{ printExamType }}</div>
                <div class="print-sub">College: {{ printCollegeName }}</div>
            </div>
            <div class="table-responsive no-print">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 72px;">No.</th>
                            <th>Student Fullname</th>
                            <th>Exam Name</th>
                            <th class="text-end">Math</th>
                            <th class="text-end">English</th>
                            <th class="text-end">Science</th>
                            <th class="text-end">Social Science</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="8" class="text-center py-4 text-muted">Loading reports...</td>
                        </tr>
                        <tr v-else-if="filteredRows.length === 0">
                            <td colspan="8" class="text-center py-4 text-muted">No records found.</td>
                        </tr>
                        <tr
                            v-else
                            v-for="(row, index) in filteredRows"
                            :key="row.answer_sheet_id"
                            class="clickable-row"
                            :class="{ 'row-new': isRowNew('reports', row.checked_at) }"
                            @click="openStudentAnswers(row)"
                        >
                            <td>{{ index + 1 }}</td>
                            <td class="fw-semibold">{{ row.student_full_name }}</td>
                            <td>{{ row.exam_name }}</td>
                            <td class="text-end">{{ row.math }}</td>
                            <td class="text-end">{{ row.english }}</td>
                            <td class="text-end">{{ row.science }}</td>
                            <td class="text-end">{{ row.social_science }}</td>
                            <td class="text-end fw-bold position-relative">
                                {{ row.total }}
                                <span v-if="isRowNew('reports', row.checked_at)" class="row-dot" aria-hidden="true"></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive print-only">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 72px;">No.</th>
                            <th>Name</th>
                            <th class="text-end">Score</th>
                            <th class="text-end">Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="printableRows.length === 0">
                            <td colspan="4" class="text-center py-4 text-muted">No records found.</td>
                        </tr>
                        <tr v-else v-for="(row, index) in printableRows" :key="row.answer_sheet_id">
                            <td>{{ index + 1 }}</td>
                            <td>{{ row.student_full_name }}</td>
                            <td class="text-end">{{ row.score }}</td>
                            <td class="text-end">{{ row.items }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="isDetailOpen" class="popup-overlay" @click.self="closeStudentAnswers">
            <div class="popup-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">Student Answer Check (1-100)</h5>
                        <div class="text-muted small">
                            {{ selectedStudent?.student_full_name || '-' }} | {{ selectedStudent?.exam_name || '-' }}
                        </div>
                    </div>
                    <button type="button" class="btn-close" @click="closeStudentAnswers"></button>
                </div>

                <div v-if="detailLoading" class="text-center text-muted py-4">Loading answer details...</div>
                <div v-else-if="detailError" class="alert alert-danger py-2 mb-0">{{ detailError }}</div>
                <div v-else class="answers-grid">
                    <div v-for="item in detailItems" :key="item.question" class="answer-item">
                        <div class="fw-semibold">{{ item.question }}</div>
                        <div :class="item.is_correct ? 'text-success' : 'text-danger'">
                            {{ item.is_correct ? 'Correct' : 'Incorrect' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { useNotifications } from '../../composables/useNotifications';

const loading = ref(false);
const rows = ref([]);
const isDetailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const selectedStudent = ref(null);
const detailItems = ref([]);
const latestCheckedAt = ref(null);
const { isRowNew, markSeen } = useNotifications({ poll: false });

const filters = ref({
    examTitle: '',
    name: '',
    sortBy: 'student_full_name',
    sortOrder: 'asc',
});

const examTitles = computed(() => {
    return [...new Set(rows.value.map((row) => row.exam_name))].sort((a, b) => a.localeCompare(b));
});

const filteredRows = computed(() => {
    let result = [...rows.value];

    if (filters.value.examTitle) {
        result = result.filter((row) => row.exam_name === filters.value.examTitle);
    }

    if (filters.value.name) {
        const search = filters.value.name.toLowerCase();
        result = result.filter((row) => row.student_full_name.toLowerCase().includes(search));
    }

    const key = filters.value.sortBy;
    const factor = filters.value.sortOrder === 'asc' ? 1 : -1;

    result.sort((a, b) => {
        const first = a[key];
        const second = b[key];

        if (typeof first === 'number' && typeof second === 'number') {
            return (first - second) * factor;
        }

        return String(first).localeCompare(String(second)) * factor;
    });

    return result;
});

const readableSortBy = computed(() => {
    const labels = {
        student_full_name: 'Name',
        exam_name: 'Exam',
        math: 'Math',
        english: 'English',
        science: 'Science',
        social_science: 'Social Science',
        total: 'Total',
    };

    return labels[filters.value.sortBy] || 'Name';
});

const generatedAt = computed(() => {
    return new Date().toLocaleString();
});

const printExamTitle = computed(() => {
    if (filters.value.examTitle) {
        return filters.value.examTitle;
    }

    const unique = [...new Set(filteredRows.value.map((row) => row.exam_name).filter(Boolean))];
    return unique.length === 1 ? unique[0] : 'All Exams';
});

const printExamType = computed(() => {
    const unique = [...new Set(filteredRows.value.map((row) => row.exam_type).filter(Boolean))];
    return unique.length === 1 ? unique[0] : 'Mixed';
});

const printCollegeName = computed(() => {
    const unique = [...new Set(filteredRows.value.map((row) => row.college_name).filter(Boolean))];
    return unique.length === 1 ? unique[0] : 'Multiple Colleges';
});

const printableRows = computed(() => {
    return filteredRows.value.map((row) => ({
        ...row,
        score: Number(row.total ?? 0),
        items: Number(row.items ?? 100),
    }));
});

const downloadPrintablePdf = () => {
    if (loading.value || filteredRows.value.length === 0) {
        return;
    }

    const tableRows = printableRows.value
        .map((row, index) => `<tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(row.student_full_name || '')}</td>
            <td>${Number(row.score || 0)}</td>
            <td>${Number(row.items || 100)}</td>
        </tr>`)
        .join('');

    const html = `
        <html>
            <head>
                <meta charset="UTF-8" />
                <title>${escapeHtml(printExamTitle.value)}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 28px 36px; }
                    h2 { text-align: center; margin: 8px 0 8px; font-size: 36px; font-weight: 700; }
                    .sub { text-align: center; font-size: 16px; margin: 2px 0; }
                    table { border-collapse: collapse; width: 100%; margin-top: 18px; font-size: 16px; }
                    th, td { border: 1px solid #aeb9c7; padding: 8px 10px; text-align: center; }
                    th { background: #dde3eb; font-weight: 700; }
                    td:nth-child(2) { text-align: left; }
                </style>
            </head>
            <body>
                <h2>${escapeHtml(printExamTitle.value)}</h2>
                <div class="sub">Type of Exam: ${escapeHtml(printExamType.value)}</div>
                <div class="sub">College: ${escapeHtml(printCollegeName.value)}</div>
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Name</th>
                            <th>Score</th>
                            <th>Items</th>
                        </tr>
                    </thead>
                    <tbody>${tableRows}</tbody>
                </table>
            </body>
        </html>
    `;

    const printWindow = window.open('', '_blank', 'width=1024,height=768');
    if (!printWindow) return;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
};

const escapeHtml = (value) => {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
};

const loadReports = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/entrance/reports/examinee-results');
        rows.value = Array.isArray(data?.data) ? data.data : [];
        latestCheckedAt.value = rows.value
            .map((row) => row.checked_at)
            .filter(Boolean)
            .sort()
            .slice(-1)[0] || null;
    } catch (error) {
        rows.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load reports',
            text: 'Please refresh and try again.',
        });
    } finally {
        loading.value = false;
    }
};

const openStudentAnswers = async (row) => {
    isDetailOpen.value = true;
    detailLoading.value = true;
    detailError.value = '';
    detailItems.value = [];
    selectedStudent.value = row;

    try {
        const { data } = await axios.get(`/api/entrance/reports/examinee-results/${row.answer_sheet_id}`);
        const apiItems = Array.isArray(data?.data?.items) ? data.data.items : [];

        detailItems.value = Array.from({ length: 100 }, (_, i) => {
            const question = i + 1;
            const item = apiItems.find((it) => Number(it?.question) === question);

            return {
                question,
                is_correct: Boolean(item?.is_correct),
            };
        });
    } catch (error) {
        detailError.value = error?.response?.data?.message || 'Failed to load student answers.';
    } finally {
        detailLoading.value = false;
    }
};

const closeStudentAnswers = () => {
    isDetailOpen.value = false;
    detailLoading.value = false;
    detailError.value = '';
    selectedStudent.value = null;
    detailItems.value = [];
};

onMounted(loadReports);

onUnmounted(() => {
    if (latestCheckedAt.value) {
        markSeen('reports', latestCheckedAt.value);
    }
});
</script>

<style scoped>
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
    width: min(960px, 100%);
    max-height: 88vh;
    overflow: auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.2);
    padding: 20px;
}

.answers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
}

.answer-item {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px;
    text-align: center;
}

.row-new {
    background: #fff1f2;
}

.row-dot {
    position: absolute;
    top: 6px;
    right: 10px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ef4444;
    box-shadow: 0 0 0 2px #fff1f2;
}

.print-only {
    display: none;
}

@media print {
    .no-print {
        display: none !important;
    }

    .print-only {
        display: block !important;
    }

    .page-container {
        padding: 0 !important;
    }

    .card {
        border: 1px solid #d1d5db !important;
        box-shadow: none !important;
    }

    .table-responsive {
        overflow: visible !important;
    }

    .table {
        font-size: 12px;
    }

    .table th,
    .table td {
        white-space: nowrap;
    }

    .print-sheet {
        padding: 12px 16px;
        border-bottom: 1px solid #e5e7eb;
        text-align: center;
    }

    .print-title {
        font-size: 22px;
        font-weight: 700;
        line-height: 1.25;
    }

    .print-sub {
        font-size: 13px;
        line-height: 1.4;
    }
}
</style>
