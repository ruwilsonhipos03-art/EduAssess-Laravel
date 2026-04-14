<template>
  <div class="page-container">
    <h3 class="fw-bold mb-4">{{ title }}</h3>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label fw-semibold">Exam</label>
          <select v-model="selectedExam" class="form-select" :disabled="loadingReports || !examOptions.length">
            <option value="">Select Exam</option>
            <option v-for="exam in examOptions" :key="exam" :value="exam">{{ exam }}</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-semibold">Respondents</label>
          <input class="form-control" :value="String(respondentCount)" disabled />
        </div>

        <div class="col-md-4">
          <button class="btn btn-outline-success w-100" :disabled="loadingReports || loadingItems" @click="reloadAll">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh Analysis
          </button>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Question #</th>
              <th class="text-end">Difficulty (p)</th>
              <th class="text-end">Discrimination (D)</th>
              <th>Status</th>
              <th>Action Needed</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingReports || loadingItems">
              <td colspan="5" class="text-center py-4 text-muted">Computing item analysis...</td>
            </tr>
            <tr v-else-if="itemRows.length === 0">
              <td colspan="5" class="text-center py-4 text-muted">No analysis data available for this exam.</td>
            </tr>
            <tr v-else v-for="row in itemRows" :key="row.question">
              <td class="fw-semibold">Q{{ row.question }}</td>
              <td class="text-end">{{ row.difficulty.toFixed(2) }}</td>
              <td class="text-end">{{ row.discrimination.toFixed(2) }}</td>
              <td>
                <span :class="['fw-semibold', row.statusClass]">{{ row.statusLabel }}</span>
              </td>
              <td>{{ row.actionNeeded }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  title: {
    type: String,
    default: 'Exam Item Analysis',
  },
  examTypeScope: {
    type: String,
    default: 'all', // all | screening | term
  },
});

const SCREENING_ALIASES = ['entrance', 'entrance exam', 'screening', 'screening exam'];
const TERM_ALIASES = ['departmental', 'normal', 'normal exam', 'term', 'term exam'];

const loadingReports = ref(false);
const loadingItems = ref(false);
const reportRows = ref([]);
const itemRows = ref([]);
const selectedExam = ref('');
const respondentCount = ref(0);

const normalizeType = (value) => String(value || '').trim().toLowerCase();

const isAllowedByScope = (row) => {
  if (props.examTypeScope === 'all') {
    return true;
  }

  const examType = normalizeType(row?.exam_type);
  if (props.examTypeScope === 'screening') {
    return SCREENING_ALIASES.includes(examType);
  }
  if (props.examTypeScope === 'term') {
    return TERM_ALIASES.includes(examType);
  }

  return true;
};

const scopedRows = computed(() => {
  return reportRows.value.filter((row) => isAllowedByScope(row));
});

const examOptions = computed(() => {
  return [...new Set(scopedRows.value.map((row) => String(row?.exam_name || '').trim()).filter(Boolean))]
    .sort((a, b) => a.localeCompare(b));
});

const classifyItem = (difficulty, discrimination) => {
  if (discrimination < 0) {
    return {
      statusLabel: '🔴 Defective',
      statusClass: 'text-danger',
      actionNeeded: 'Check for errors / Ambiguity',
    };
  }

  if (difficulty >= 0.85) {
    return {
      statusLabel: '🟡 Too Easy',
      statusClass: 'text-warning',
      actionNeeded: 'Make it more challenging',
    };
  }

  if (difficulty <= 0.20) {
    return {
      statusLabel: '🟠 Too Difficult',
      statusClass: 'text-warning',
      actionNeeded: 'Review wording or content coverage',
    };
  }

  if (discrimination >= 0.30 && difficulty >= 0.30 && difficulty <= 0.80) {
    return {
      statusLabel: '✅ Excellent',
      statusClass: 'text-success',
      actionNeeded: 'Keep for Item Bank',
    };
  }

  return {
    statusLabel: '🔵 Needs Review',
    statusClass: 'text-primary',
    actionNeeded: 'Revise and re-test item quality',
  };
};

const loadReports = async () => {
  loadingReports.value = true;
  try {
    const { data } = await axios.get('/api/entrance/reports/examinee-results');
    reportRows.value = Array.isArray(data?.data) ? data.data : [];

    if (!examOptions.value.includes(selectedExam.value)) {
      selectedExam.value = examOptions.value[0] || '';
    }
  } catch (error) {
    reportRows.value = [];
    selectedExam.value = '';
    window.Swal?.fire({
      icon: 'error',
      title: 'Failed to load reports',
      text: error?.response?.data?.message || 'Please refresh and try again.',
    });
  } finally {
    loadingReports.value = false;
  }
};

const fetchExamDetailRows = async (rows) => {
  const responses = [];
  const chunkSize = 8;

  for (let index = 0; index < rows.length; index += chunkSize) {
    const chunk = rows.slice(index, index + chunkSize);
    const chunkResults = await Promise.all(
      chunk.map(async (row) => {
        const { data } = await axios.get(`/api/entrance/reports/examinee-results/${row.answer_sheet_id}`);
        const items = Array.isArray(data?.data?.items) ? data.data.items : [];
        return {
          score: Number(row?.total ?? row?.score ?? 0),
          items,
        };
      })
    );

    responses.push(...chunkResults);
  }

  return responses;
};

const buildItemAnalysis = async () => {
  if (!selectedExam.value) {
    itemRows.value = [];
    respondentCount.value = 0;
    return;
  }

  const selectedRows = scopedRows.value.filter((row) => String(row?.exam_name || '').trim() === selectedExam.value);
  respondentCount.value = selectedRows.length;

  if (selectedRows.length === 0) {
    itemRows.value = [];
    return;
  }

  loadingItems.value = true;
  try {
    const respondents = await fetchExamDetailRows(selectedRows);
    const totalRespondents = respondents.length;

    if (totalRespondents === 0) {
      itemRows.value = [];
      return;
    }

    const sortedRespondents = [...respondents].sort((a, b) => b.score - a.score);
    const groupSize = Math.max(1, Math.floor(totalRespondents * 0.27));
    const upperGroup = sortedRespondents.slice(0, groupSize);
    const lowerGroup = sortedRespondents.slice(-groupSize);

    itemRows.value = Array.from({ length: 100 }, (_, idx) => {
      const question = idx + 1;

      const totalCorrect = respondents.reduce((acc, respondent) => {
        const row = respondent.items.find((item) => Number(item?.question) === question);
        return acc + (row?.is_correct ? 1 : 0);
      }, 0);

      const upperCorrect = upperGroup.reduce((acc, respondent) => {
        const row = respondent.items.find((item) => Number(item?.question) === question);
        return acc + (row?.is_correct ? 1 : 0);
      }, 0);

      const lowerCorrect = lowerGroup.reduce((acc, respondent) => {
        const row = respondent.items.find((item) => Number(item?.question) === question);
        return acc + (row?.is_correct ? 1 : 0);
      }, 0);

      const difficulty = totalRespondents > 0 ? totalCorrect / totalRespondents : 0;
      const discrimination = groupSize > 0 ? (upperCorrect / groupSize) - (lowerCorrect / groupSize) : 0;
      const status = classifyItem(difficulty, discrimination);

      return {
        question,
        difficulty,
        discrimination,
        ...status,
      };
    });
  } catch (error) {
    itemRows.value = [];
    window.Swal?.fire({
      icon: 'error',
      title: 'Failed to compute item analysis',
      text: error?.response?.data?.message || 'Please try another exam or refresh.',
    });
  } finally {
    loadingItems.value = false;
  }
};

const reloadAll = async () => {
  await loadReports();
  await buildItemAnalysis();
};

watch(selectedExam, async () => {
  await buildItemAnalysis();
});

onMounted(async () => {
  await loadReports();
  await buildItemAnalysis();
});
</script>
