<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">College Dean Workspace</h4>
                        <p class="text-muted small mb-0">Create exams, set answer keys, and generate sheets in one place
                        </p>
                    </div>
                    <div class="col-auto">
                        <button @click="openExamModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-plus-lg me-2"></i> NEW EXAM SET
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="px-4 py-3 border-bottom bg-light">
                    <div class="input-group input-group-sm w-50 w-md-25">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input v-model="searchQuery" type="text" class="form-control border-start-0 ps-0"
                            placeholder="Search by title or type...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">NO.</th>
                                <th class="py-3 text-secondary small fw-bold">EXAM TITLE</th>
                                <th class="py-3 text-secondary small fw-bold">CATEGORY</th>
                                <th class="py-3 text-secondary small fw-bold">PROGRAM</th>
                                <th class="py-3 text-secondary small fw-bold">STATUS</th>
                                <th class="py-3 text-secondary small fw-bold">EXAMINER</th>
                                <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="7" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                </td>
                            </tr>
                            <template v-else>
                                <tr v-for="(exam, index) in filteredExams" :key="exam.id" class="exam-row"
                                    @click="openWorkspace(exam)">
                                    <td class="ps-4 text-muted">#{{ index + 1 }}</td>
                                    <td>
                                        <button
                                            class="btn btn-link p-0 text-decoration-none text-start exam-title-button">
                                            <div class="d-flex align-items-center">
                                                <div class="exam-icon me-3">
                                                    <i class="bi bi-journal-text text-emerald"></i>
                                                </div>
                                                <span class="fw-semibold text-dark">{{ exam.Exam_Title }}</span>
                                            </div>
                                        </button>
                                    </td>
                                    <td>
                                        <span
                                            class="badge rounded-pill bg-light text-emerald border border-emerald px-3">
                                            {{ exam.Exam_Type }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">{{ exam.program?.Program_Name || exam.program_name || 'N/A' }}</td>
                                    <td>
                                        <span class="badge rounded-pill px-3 py-2 small fw-semibold"
                                            :class="statusBadgeClass(exam)">
                                            {{ examStatusLabel(exam) }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">{{ examinerName(exam) }}</td>
                                    <td class="pe-4 text-end" @click.stop>
                                        <button @click="openExamModal(exam)" class="btn btn-icon btn-light-success me-2"
                                            title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteExam(exam.id)" class="btn btn-icon btn-light-danger"
                                            :disabled="deletingId === exam.id">
                                            <span v-if="deletingId === exam.id"
                                                class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredExams.length === 0">
                                    <td colspan="7" class="text-center py-5 text-muted">No exams recorded yet.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="examModal" tabindex="-1" ref="examModalRef">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Modify Exam Set' : 'Create New Exam Set' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveExam">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">EXAM TITLE</label>
                                <input v-model="form.Exam_Title" type="text" class="form-control border-2" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">PROGRAM</label>
                                <select v-model="form.program_id" class="form-select border-2" required>
                                    <option value="" disabled>Select program</option>
                                    <option v-for="program in programs" :key="program.id" :value="String(program.id)">
                                        {{ program.Program_Name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">CANCEL</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold" :disabled="isSaving">
                                {{ isSaving ? 'SAVING...' : 'SAVE EXAM' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="workspaceModal" tabindex="-1" ref="workspaceModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-dark text-white border-0">
                        <div>
                            <h5 class="modal-title fw-bold mb-1">{{ selectedExam?.Exam_Title || 'Exam Workspace' }}</h5>
                            <p class="mb-0 small text-white-50">{{ selectedExam?.program?.Program_Name || selectedExam?.program_name || 'No program assigned' }}</p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light p-4">
                        <div v-if="selectedExam" class="row g-4">
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                            <div>
                                                <div class="small text-muted fw-semibold mb-2">EXAM STATUS</div>
                                                <span class="badge rounded-pill px-3 py-2 small fw-semibold"
                                                    :class="statusBadgeClass(selectedExam)">
                                                    {{ examStatusLabel(selectedExam) }}
                                                </span>
                                            </div>
                                            <div class="text-muted small">
                                                Sheet generation is enabled only after a complete answer key is saved.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="selection-switcher">
                                    <button type="button" class="selection-pill"
                                        :class="{ active: workspaceSection === 'answers' }"
                                        @click="workspaceSection = 'answers'">
                                        Answer Key
                                    </button>
                                    <button v-if="isAnswerKeyReady(selectedExam)" type="button" class="selection-pill"
                                        :class="{ active: workspaceSection === 'sheets' }"
                                        @click="workspaceSection = 'sheets'">
                                        Generate Sheets
                                    </button>
                                </div>
                            </div>

                            <div v-if="workspaceSection === 'answers'" class="col-12">
                                <div class="card border-0 shadow-sm h-100">
                                    <div
                                        class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-1">Answer Key</h6>
                                            <p class="text-muted small mb-0">Complete all 100 items before saving.</p>
                                        </div>
                                        <button v-if="selectedAnswerKey" @click="deleteAnswerKey"
                                            class="btn btn-light-danger btn-sm" :disabled="isDeletingAnswerKey">
                                            <span v-if="isDeletingAnswerKey"
                                                class="spinner-border spinner-border-sm me-2"></span>
                                            Delete Key
                                        </button>
                                    </div>
                                    <div class="card-body px-4 pb-4">
                                        <div class="answer-grid">
                                            <div v-for="n in 100" :key="n" class="answer-grid-item">
                                                <div class="answer-box compact-box" :class="getAnswerBoxClass(n)">
                                                    <div
                                                        class="d-flex justify-content-between px-2 pt-1 compact-label-wrap">
                                                        <span class="fw-bold x-small text-muted compact-label">#{{ n
                                                            }}</span>
                                                    </div>
                                                    <div class="p-1 compact-select-wrap">
                                                        <select v-model="answerKeyForm.answers[n]"
                                                            class="form-select form-select-sm border-0 shadow-sm text-center fw-bold compact-select"
                                                            :class="getAnswerSelectClass(n)" @focus="focusedItem = n"
                                                            @change="markAnswerTouched(n)">
                                                            <option value="">-</option>
                                                            <option v-for="opt in ['A', 'B', 'C', 'D', 'E']" :key="opt"
                                                                :value="opt">{{ opt }}</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="workspaceSection === 'answers'" class="col-12">
                                <div class="d-flex justify-content-end">
                                    <button @click="saveExamConfiguration" class="btn btn-emerald px-4 fw-bold"
                                        :disabled="isSavingConfiguration || !selectedExam || !canSaveExamConfiguration">
                                        <span v-if="isSavingConfiguration"
                                            class="spinner-border spinner-border-sm me-2"></span>
                                        {{ isSavingConfiguration ? 'SAVING...' : 'SAVE EXAM' }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="workspaceSection === 'sheets'" class="col-12">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-white border-0 pt-4 px-4">
                                        <h6 class="fw-bold mb-1">Generate Sheets</h6>
                                        <p class="text-muted small mb-0">Generate printable sheets for this exam only.
                                        </p>
                                    </div>
                                    <div class="card-body px-4 pb-4">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-secondary">SELECT
                                                SCHEDULE</label>
                                            <select v-model="scheduleForm.exam_schedule_id" class="form-select border-2">
                                                <option value="">Select screening schedule...</option>
                                                <option v-for="schedule in entranceSchedules" :key="schedule.id" :value="String(schedule.id)">
                                                    {{ schedule.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <div v-if="selectedSchedule" class="small text-muted mb-3">
                                            Assigned students: {{ selectedSchedule.assigned_students }} | Capacity:
                                            {{ selectedSchedule.capacity }}
                                        </div>
                                        <div v-else class="small text-muted mb-3">
                                            Use the Screening Exam Schedule page to assign passed entrance-exam students first.
                                        </div>
                                        <button @click="generateSheets" class="btn btn-emerald w-100 fw-bold"
                                            :disabled="isGeneratingSheets || !isAnswerKeyReady(selectedExam) || !scheduleForm.exam_schedule_id">
                                            <span v-if="isGeneratingSheets"
                                                class="spinner-border spinner-border-sm me-2"></span>
                                            {{ isGeneratingSheets ? 'GENERATING...' : 'GENERATE / DOWNLOAD SHEETS' }}
                                        </button>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white border-0 pt-4 px-4">
                                        <h6 class="fw-bold mb-1">Generated Sheets</h6>
                                        <p class="text-muted small mb-0">Manage sheets already created for this exam.
                                        </p>
                                    </div>
                                    <div class="card-body px-4 pb-4">
                                        <div v-if="selectedExamSheets.length === 0" class="text-muted small">
                                            No answer sheets generated yet.
                                        </div>
                                        <div v-else class="sheet-list">
                                            <div v-for="sheet in selectedExamSheets" :key="sheet.id" class="sheet-item">
                                                <div>
                                                    <div class="fw-semibold small text-dark">{{ sheet.qr_payload }}
                                                    </div>
                                                    <div class="text-muted x-small text-uppercase">{{ sheet.status }}
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button @click="downloadSheet(sheet.id)"
                                                        class="btn btn-light-success btn-sm"
                                                        :disabled="printingId === sheet.id || deletingSheetId === sheet.id">
                                                        <span v-if="printingId === sheet.id"
                                                            class="spinner-border spinner-border-sm"></span>
                                                        <i v-else class="bi bi-download"></i>
                                                    </button>
                                                    <button @click="deleteSheet(sheet.id)"
                                                        class="btn btn-light-danger btn-sm"
                                                        :disabled="deletingSheetId === sheet.id || printingId === sheet.id">
                                                        <span v-if="deletingSheetId === sheet.id"
                                                            class="spinner-border spinner-border-sm"></span>
                                                        <i v-else class="bi bi-trash3"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';

const exams = ref([]);
const answerKeys = ref([]);
const sheets = ref([]);
const programs = ref([]);
const entranceSchedules = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const examModalRef = ref(null);
const workspaceModalRef = ref(null);
const selectedExam = ref(null);
const focusedItem = ref(null);
const touchedAnswers = ref({});
const hasAttemptedAnswerSave = ref(false);
const workspaceSection = ref('answers');
const isSavingConfiguration = ref(false);

let examModalInstance = null;
let workspaceModalInstance = null;

const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);
const isDeletingAnswerKey = ref(false);
const isGeneratingSheets = ref(false);
const printingId = ref(null);
const deletingSheetId = ref(null);

const EXAM_TYPE_ALIASES = ['entrance', 'screening', 'screening exam'];

const form = reactive({
    Exam_Title: '',
    Exam_Type: 'Screening',
    program_id: '',
});
const answerKeyForm = reactive({ answers: {} });
const scheduleForm = reactive({ exam_schedule_id: '' });

const resetForm = () => {
    form.Exam_Title = '';
    form.Exam_Type = 'Screening';
    form.program_id = '';
};

const resetAnswerKeyForm = () => {
    focusedItem.value = null;
    hasAttemptedAnswerSave.value = false;
    touchedAnswers.value = {};
    for (let i = 1; i <= 100; i++) answerKeyForm.answers[i] = '';
};

const filteredExams = computed(() => {
    const list = (exams.value || []).filter((exam) =>
        EXAM_TYPE_ALIASES.includes(String(exam?.Exam_Type || '').trim().toLowerCase())
    );
    return list.filter((exam) =>
        String(exam?.Exam_Title || '').toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const selectedAnswerKey = computed(() => {
    const examId = Number(selectedExam.value?.id || 0);
    return answerKeys.value.find((key) => Number(key.exam_id) === examId) || null;
});

const selectedExamSheets = computed(() => {
    const examId = Number(selectedExam.value?.id || 0);
    return sheets.value.filter((sheet) => Number(sheet.exam_id) === examId);
});

const selectedSchedule = computed(() =>
    entranceSchedules.value.find((schedule) => Number(schedule.id) === Number(scheduleForm.exam_schedule_id)) || null
);

const examinerName = (exam) => {
    const first = String(exam?.examiner_first_name || exam?.creator?.user?.first_name || exam?.creator?.first_name || '').trim();
    return first || 'N/A';
};

const normalizedAnswerCount = (answers) => Object.values(answers || {}).filter((value) => String(value || '').trim() !== '').length;
const extractArray = (payload) => {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    return [];
};

const answerKeyForExam = (exam) => {
    const examId = Number(exam?.id || 0);
    return answerKeys.value.find((key) => Number(key.exam_id) === examId) || null;
};

const isAnswerKeyReady = (exam) => normalizedAnswerCount(answerKeyForExam(exam)?.answers) >= 100;

const examStatusLabel = (exam) => {
    const key = answerKeyForExam(exam);
    if (!key) return 'No Answer Key Set';
    if (normalizedAnswerCount(key.answers) < 100) return 'Answer Key Incomplete';
    return 'Ready for Sheet Generation';
};

const statusBadgeClass = (exam) => {
    const label = examStatusLabel(exam);
    if (label === 'Ready for Sheet Generation') return 'bg-success-subtle text-success border border-success-subtle';
    if (label === 'Answer Key Incomplete') return 'bg-warning-subtle text-warning border border-warning-subtle';
    return 'bg-danger-subtle text-danger border border-danger-subtle';
};

const isAnswerFilled = (n) => String(answerKeyForm.answers[n] || '').trim() !== '';
const markAnswerTouched = (n) => { touchedAnswers.value[n] = true; };
const shouldShowValidation = (n) => hasAttemptedAnswerSave.value || touchedAnswers.value[n];

const getAnswerBoxClass = (n) => ({
    'default-block': !shouldShowValidation(n) && focusedItem.value !== n,
    'focused-block': focusedItem.value === n,
    'valid-block': shouldShowValidation(n) && isAnswerFilled(n) && focusedItem.value !== n,
    'invalid-block': shouldShowValidation(n) && !isAnswerFilled(n) && focusedItem.value !== n,
});

const getAnswerSelectClass = (n) => ({
    'valid-select': shouldShowValidation(n) && isAnswerFilled(n),
    'invalid-select': shouldShowValidation(n) && !isAnswerFilled(n),
    'focused-select': focusedItem.value === n,
});

const hasCompleteAnswers = () => {
    for (let i = 1; i <= 100; i++) {
        if (!isAnswerFilled(i)) return false;
    }
    return true;
};

const answerKeyValidationError = computed(() => hasCompleteAnswers() ? '' : 'Please complete all 100 answer items before saving.');

const canSaveExamConfiguration = computed(() => !answerKeyValidationError.value);

const fetchExams = async () => {
    try {
        const response = await axios.get('/api/exams', { params: { scope: 'screening' } });
        exams.value = extractArray(response.data);
    } catch (_) {
        exams.value = [];
    }
};

const fetchAnswerKeys = async () => {
    try {
        const response = await axios.get('/api/answer-keys');
        answerKeys.value = extractArray(response.data).filter((key) =>
            EXAM_TYPE_ALIASES.includes(String(key?.exam?.Exam_Type || '').trim().toLowerCase())
        );
    } catch (_) {
        answerKeys.value = [];
    }
};

const fetchSheets = async () => {
    try {
        const response = await axios.get('/api/answer-sheets');
        sheets.value = extractArray(response.data);
    } catch (_) {
        sheets.value = [];
    }
};

const fetchPrograms = async () => {
    try {
        const response = await axios.get('/api/programs');
        programs.value = extractArray(response.data);
    } catch (_) {
        try {
            const fallback = await axios.get('/api/admin/programs');
            programs.value = extractArray(fallback.data);
        } catch (_) {
            programs.value = [];
        }
    }
};

const fetchEntranceSchedules = async () => {
    if (!selectedExam.value?.id) {
        entranceSchedules.value = [];
        scheduleForm.exam_schedule_id = '';
        return;
    }

    try {
        const response = await axios.get('/api/college_dean/screening-schedules', {
            params: { exam_id: Number(selectedExam.value.id) },
        });
        entranceSchedules.value = extractArray(response.data);

        const hasCurrent = entranceSchedules.value.some((schedule) => Number(schedule.id) === Number(scheduleForm.exam_schedule_id));
        if (!hasCurrent) {
            scheduleForm.exam_schedule_id = entranceSchedules.value[0] ? String(entranceSchedules.value[0].id) : '';
        }
    } catch (_) {
        entranceSchedules.value = [];
        scheduleForm.exam_schedule_id = '';
    }
};

const syncAnswerKeyForm = () => {
    resetAnswerKeyForm();
    if (selectedAnswerKey.value?.answers) {
        Object.assign(answerKeyForm.answers, selectedAnswerKey.value.answers);
    }
};

const refreshWorkspaceData = async () => {
    isLoading.value = true;
    try {
        await Promise.allSettled([fetchExams(), fetchAnswerKeys(), fetchSheets(), fetchPrograms()]);
        if (selectedExam.value?.id) {
            selectedExam.value = exams.value.find((exam) => Number(exam.id) === Number(selectedExam.value.id)) || null;
            syncAnswerKeyForm();
            await fetchEntranceSchedules();
            if (!isAnswerKeyReady(selectedExam.value)) workspaceSection.value = 'answers';
        }
    } finally {
        isLoading.value = false;
    }
};

const openExamModal = (exam = null) => {
    resetForm();
    editMode.value = !!exam;
    currentId.value = exam?.id || null;
    form.Exam_Title = exam?.Exam_Title || '';
    form.Exam_Type = exam?.Exam_Type || 'Screening';
    form.program_id = exam?.program_id ? String(exam.program_id) : '';
    examModalInstance.show();
};

const openWorkspace = (exam) => {
    selectedExam.value = exam;
    scheduleForm.exam_schedule_id = '';
    workspaceSection.value = 'answers';
    syncAnswerKeyForm();
    fetchEntranceSchedules();
    workspaceModalInstance.show();
};

const saveExam = async () => {
    isSaving.value = true;
    const url = editMode.value ? `/api/exams/${currentId.value}` : '/api/exams';
    const method = editMode.value ? 'put' : 'post';
    try {
        await axios[method](url, {
            Exam_Title: form.Exam_Title,
            Exam_Type: form.Exam_Type,
            program_id: form.program_id ? Number(form.program_id) : null,
        });
        examModalInstance.hide();
        await refreshWorkspaceData();
        window.Toast.fire({ icon: 'success', title: 'Exam saved successfully.' });
    } catch (e) {
        window.Swal.fire({ icon: 'error', title: 'Process Error', text: e?.response?.data?.message || 'Check your inputs.' });
    } finally {
        isSaving.value = false;
    }
};

const saveExamConfiguration = async () => {
    hasAttemptedAnswerSave.value = true;
    if (!selectedExam.value) return;
    if (answerKeyValidationError.value) {
        window.Swal.fire({ icon: 'warning', title: 'Incomplete Answer Key', text: answerKeyValidationError.value });
        return;
    }

    isSavingConfiguration.value = true;
    try {
        const payload = { exam_id: Number(selectedExam.value.id), answers: { ...answerKeyForm.answers } };
        if (selectedAnswerKey.value) {
            await axios.put(`/api/answer-keys/${selectedAnswerKey.value.id}`, payload);
        } else {
            await axios.post('/api/answer-keys', payload);
        }
        await refreshWorkspaceData();
        workspaceSection.value = 'sheets';
        window.Toast.fire({ icon: 'success', title: 'Exam saved successfully.' });
    } catch (e) {
        window.Swal.fire({ icon: 'error', title: 'Save Failed', text: e?.response?.data?.message || 'Failed to save answer key.' });
    } finally {
        isSavingConfiguration.value = false;
    }
};

const deleteAnswerKey = async () => {
    if (!selectedAnswerKey.value) return;
    const result = await window.Swal.fire({
        title: 'Delete answer key?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
    });
    if (!result.isConfirmed) return;

    isDeletingAnswerKey.value = true;
    try {
        await axios.delete(`/api/answer-keys/${selectedAnswerKey.value.id}`);
        await refreshWorkspaceData();
        window.Toast.fire({ icon: 'success', title: 'Answer key deleted.' });
    } catch (e) {
        window.Swal.fire({ icon: 'error', title: 'Delete Failed', text: e?.response?.data?.message || 'Failed to delete answer key.' });
    } finally {
        isDeletingAnswerKey.value = false;
    }
};

const generateSheets = async () => {
    if (!selectedExam.value) return;
    if (!scheduleForm.exam_schedule_id) {
        window.Swal.fire({
            icon: 'warning',
            title: 'No Schedule Selected',
            text: 'Please select a screening schedule with assigned students first.',
        });
        return;
    }
    isGeneratingSheets.value = true;
    try {
        const res = await axios.post(
            '/api/answer-sheets/generate',
            {
                exam_id: Number(selectedExam.value.id),
                exam_schedule_id: Number(scheduleForm.exam_schedule_id),
            },
            { responseType: 'blob' }
        );
        const blob = new Blob([res.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const headerName = res.headers['content-disposition'];
        const match = headerName?.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
        const fileName = match ? match[1].replace(/['"]/g, '') : 'answer_sheets_generated.pdf';
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        await refreshWorkspaceData();
        window.Toast.fire({ icon: 'success', title: 'Answer sheets generated and downloaded.' });
    } catch (e) {
        let message = 'Could not generate sheets.';
        const maybeBlob = e?.response?.data;
        if (maybeBlob instanceof Blob) {
            try {
                message = JSON.parse(await maybeBlob.text())?.message || message;
            } catch (_) {
                // ignore parse issues
            }
        } else {
            message = e?.response?.data?.message || message;
        }
        window.Swal.fire({ icon: 'error', title: 'Generation Failed', text: message });
    } finally {
        isGeneratingSheets.value = false;
    }
};

const downloadSheet = async (id) => {
    try {
        printingId.value = id;
        const res = await axios.get(`/api/answer-sheets/${id}/print`, { responseType: 'blob' });
        const blob = new Blob([res.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const headerName = res.headers['content-disposition'];
        const match = headerName?.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
        const fileName = match ? match[1].replace(/['"]/g, '') : `answer_sheet_${id}.pdf`;
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (_) {
        window.Toast.fire({ icon: 'error', title: 'Failed to download sheet.' });
    } finally {
        printingId.value = null;
    }
};

const deleteSheet = async (id) => {
    const result = await window.Swal.fire({
        title: 'Delete Sheet?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
    });
    if (!result.isConfirmed) return;

    deletingSheetId.value = id;
    try {
        await axios.delete(`/api/answer-sheets/${id}`);
        await refreshWorkspaceData();
        window.Toast.fire({ icon: 'success', title: 'Sheet deleted.' });
    } catch (_) {
        window.Swal.fire({ icon: 'error', title: 'Delete Failed', text: 'Could not delete the sheet.' });
    } finally {
        deletingSheetId.value = null;
    }
};

const deleteExam = async (id) => {
    const result = await window.Swal.fire({
        title: 'Confirm Deletion?',
        text: 'This exam set will be permanently removed from your records.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
    });
    if (!result.isConfirmed) return;

    deletingId.value = id;
    try {
        await axios.delete(`/api/exams/${id}`);
        if (Number(selectedExam.value?.id) === Number(id)) {
            workspaceModalInstance.hide();
            selectedExam.value = null;
        }
        await refreshWorkspaceData();
        window.Toast.fire({ icon: 'success', title: 'Exam deleted successfully.' });
    } finally {
        deletingId.value = null;
    }
};

onMounted(async () => {
    examModalInstance = new Modal(examModalRef.value);
    workspaceModalInstance = new Modal(workspaceModalRef.value);
    await refreshWorkspaceData();
});
</script>

<style scoped>
.btn-emerald {
    background-color: #10b981;
    color: white;
}

.btn-emerald:hover {
    background-color: #059669;
}

.text-emerald {
    color: #10b981;
}

.bg-emerald {
    background-color: #10b981;
}

.border-emerald {
    border-color: #10b981 !important;
}

.btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
}

.btn-light-success {
    color: #10b981;
    background-color: #ecfdf5;
}

.btn-light-danger {
    color: #ef4444;
    background-color: #fef2f2;
}

.exam-icon {
    width: 40px;
    height: 40px;
    background-color: #f0fdf4;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.exam-row {
    cursor: pointer;
}

.exam-row:hover {
    background: #f8fafc;
}

.exam-title-button {
    border: 0;
    background: transparent;
}

.selection-switcher {
    display: inline-flex;
    gap: 0.75rem;
    padding: 0.4rem;
    border-radius: 999px;
    background: #e5e7eb;
}

.selection-pill {
    border: 0;
    background: transparent;
    color: #475569;
    font-weight: 700;
    padding: 0.7rem 1.2rem;
    border-radius: 999px;
    transition: 0.2s ease;
}

.selection-pill.active {
    background: #10b981;
    color: #fff;
    box-shadow: 0 10px 25px rgba(16, 185, 129, 0.2);
}

.answer-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.6rem;
}

.answer-grid-item {
    min-width: 0;
}

.answer-box {
    border-radius: 12px;
    border: 1px solid #edf2f7;
    background: #fff;
}

.compact-box {
    border-radius: 10px;
}

.compact-label-wrap {
    min-height: 20px;
}

.compact-label {
    font-size: 0.7rem;
}

.compact-select-wrap {
    padding-top: 0;
}

.compact-select {
    min-height: 34px;
    font-size: 0.85rem;
}

.default-block {
    border: 1px solid #e2e8f0;
}

.focused-block {
    border: 2px solid #2563eb;
    box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
}

.valid-block {
    border: 2px solid #16a34a;
    box-shadow: 0 0 0 0.15rem rgba(22, 163, 74, 0.12);
}

.invalid-block {
    border: 2px solid #dc2626;
    box-shadow: 0 0 0 0.15rem rgba(220, 38, 38, 0.12);
}

.valid-select {
    background-color: #f0fdf4;
}

.invalid-select {
    background-color: #fef2f2;
}

.focused-select {
    outline: 2px solid #2563eb;
    outline-offset: 1px;
}

.x-small {
    font-size: 0.75rem;
}

.sheet-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-height: 420px;
    overflow: auto;
}

.sheet-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 0.9rem;
    border-radius: 0.85rem;
    background: #f8fafc;
}

@media (max-width: 991.98px) {
    .answer-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .answer-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    .answer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
