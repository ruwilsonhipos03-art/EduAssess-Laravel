<template>
  <div class="page-container">
    <h3 class="fw-bold mb-4">Entrance Examiner Reports</h3>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold">Filter by Exam</label>
          <select v-model="filters.examTitle" class="form-select">
            <option value="">All Exams</option>
            <option v-for="title in examTitles" :key="title" :value="title">{{ title }}</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-semibold">Search Applicant</label>
          <input v-model.trim="filters.name" type="text" class="form-control" placeholder="Lastname, Firstname..." />
        </div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Result</label>
          <select v-model="filters.result" class="form-select">
            <option value="">All</option>
            <option value="Passed">Passed</option>
            <option value="Failed">Failed</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Arrange By</label>
          <select v-model="filters.sort" class="form-select">
            <option value="student_asc">Applicant A-Z</option>
            <option value="student_desc">Applicant Z-A</option>
            <option value="total_desc">Total High-Low</option>
            <option value="total_asc">Total Low-High</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Download</label>
          <input class="form-control" value="PDF" disabled />
        </div>
      </div>

      <div class="row g-2 mt-2">
        <div class="col-md-2">
          <button class="btn btn-outline-success w-100" :disabled="loading" @click="loadReports">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
          </button>
        </div>
        <div class="col-md-3">
          <button class="btn btn-success w-100" :disabled="loading || !filteredRows.length" @click="downloadPrintablePdf">
            <i class="bi bi-download me-1"></i>Download PDF
          </button>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 72px">No.</th>
              <th>Applicant</th>
              <th>Exam</th>
              <th class="text-end">Score</th>
              <th class="text-end">Items</th>
              <th>Result</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="text-center py-4 text-muted">Loading reports...</td>
            </tr>
            <tr v-else-if="filteredRows.length === 0">
              <td colspan="6" class="text-center py-4 text-muted">No records found.</td>
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
              <td class="text-end fw-bold">{{ row.score ?? row.total }}</td>
              <td class="text-end">{{ row.items ?? 100 }}</td>
              <td class="position-relative">
                <span class="badge" :class="row.total >= 75 ? 'text-bg-success' : 'text-bg-danger'">
                  {{ row.total >= 75 ? 'Passed' : 'Failed' }}
                </span>
                <span v-if="isRowNew('reports', row.checked_at)" class="row-dot" aria-hidden="true"></span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="isDetailOpen" class="popup-overlay" @click.self="closeStudentAnswers">
      <div class="popup-card">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 class="fw-bold mb-1">Applicant Answer Check (1-100)</h5>
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
  result: '',
  sort: 'student_asc',
});

const examTitles = computed(() => {
  return [...new Set(rows.value.map((row) => row.exam_name).filter(Boolean))].sort((a, b) => a.localeCompare(b));
});

const filteredRows = computed(() => {
  let result = [...rows.value];

  if (filters.value.examTitle) {
    result = result.filter((row) => row.exam_name === filters.value.examTitle);
  }

  if (filters.value.name) {
    const search = filters.value.name.toLowerCase();
    result = result.filter((row) => String(row.student_full_name || '').toLowerCase().includes(search));
  }

  if (filters.value.result) {
    result = result.filter((row) => (row.total >= 75 ? 'Passed' : 'Failed') === filters.value.result);
  }

  if (filters.value.sort === 'student_asc') {
    result.sort((a, b) => String(a.student_full_name || '').localeCompare(String(b.student_full_name || '')));
  } else if (filters.value.sort === 'student_desc') {
    result.sort((a, b) => String(b.student_full_name || '').localeCompare(String(a.student_full_name || '')));
  } else if (filters.value.sort === 'total_desc') {
    result.sort((a, b) => Number(b.total || 0) - Number(a.total || 0));
  } else if (filters.value.sort === 'total_asc') {
    result.sort((a, b) => Number(a.total || 0) - Number(b.total || 0));
  }

  return result;
});

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
    detailError.value = error?.response?.data?.message || 'Failed to load applicant answers.';
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

const downloadPrintablePdf = () => {
  if (!filteredRows.value.length) return;

  const reportTitle = 'Entrance Examiner Reports';

  const tableRows = filteredRows.value
    .map((row, index) => {
      const result = row.total >= 75 ? 'Passed' : 'Failed';
      return `<tr>
        <td>${index + 1}</td>
        <td>${escapeHtml(row.student_full_name || '')}</td>
        <td>${escapeHtml(row.exam_name || '')}</td>
        <td>${Number(row.score ?? row.total ?? 0)}</td>
        <td>${Number(row.items ?? 100)}</td>
        <td>${escapeHtml(result)}</td>
      </tr>`;
    })
    .join('');

  const html = `
    <html>
      <head>
        <meta charset="UTF-8" />
        <title>${escapeHtml(reportTitle)}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 28px 36px; }
          h2 { text-align: center; margin: 8px 0 22px; font-size: 40px; font-weight: 700; }
          table { border-collapse: collapse; width: 100%; font-size: 18px; }
          th, td { border: 1px solid #aeb9c7; padding: 8px 10px; text-align: center; }
          th { background: #dde3eb; font-weight: 700; }
          td:nth-child(2) { text-align: left; }
        </style>
      </head>
      <body>
        <h2>${escapeHtml(reportTitle)}</h2>
        <table>
          <thead>
            <tr>
              <th>No.</th>
              <th>Applicant</th>
              <th>Exam</th>
              <th>Score</th>
              <th>Items</th>
              <th>Result</th>
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
</style>
