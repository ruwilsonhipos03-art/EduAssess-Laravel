<template>
    <div class="container-fluid py-2 py-md-4">
        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body p-3 p-md-4">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-md">
                        <h4 class="fw-bold mb-1 text-dark">Exam Configuration Hub</h4>
                        <p class="text-muted small mb-0 d-none d-sm-block">Manage exam answer keys in one view</p>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button @click="openModal()" class="btn btn-emerald fw-bold w-100 px-4">
                            <i class="bi bi-plus-lg me-2"></i> CONFIGURE NEW EXAM
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-secondary small fw-bold">EXAM TITLE</th>
                            <th class="py-3 text-secondary small fw-bold text-center">ITEMS</th>
                            <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="3" class="text-center py-5">
                                <div class="spinner-border text-emerald"></div>
                            </td>
                        </tr>
                        <tr v-else v-for="key in answerKeys" :key="key.id">
                            <td class="ps-4">
                                <div class="fw-bold text-dark">{{ key.exam?.Exam_Title || 'N/A' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-dark px-3">{{ Object.keys(key.answers || {}).length }}</span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="btn-group shadow-sm rounded-3">
                                    <button @click="openModal(key)" class="btn btn-light-success border-0 px-3"><i
                                            class="bi bi-pencil-square"></i></button>
                                    <button @click="deleteKey(key.id)" class="btn btn-light-danger border-0 px-3"><i
                                            class="bi bi-trash3"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal fade" id="configModal" tabindex="-1" ref="modalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable config-modal-dialog">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-dark text-white border-0 py-3 px-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-emerald p-2 rounded-3 me-3 d-none d-sm-block">
                                <i class="bi bi-gear-fill text-white"></i>
                            </div>
                            <h5 class="modal-title fw-bold">{{ editMode ? 'Edit' : 'New' }} Answer Key</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-0 d-flex flex-column overflow-hidden bg-light">
                        <div class="bg-white p-3 p-md-4 overflow-auto custom-scrollbar config-modal-body">
                            <div class="sticky-top bg-white pb-3 mb-2 shadow-bottom-fade">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center">
                                            <span class="step-badge">1</span>
                                            <h6 class="fw-bold mb-0 ms-2">Answer Key (1-100)</h6>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="d-flex flex-column gap-2">
                                            <select v-model="form.exam_id"
                                                class="form-select border-2 fw-bold text-emerald focus-ring-emerald"
                                                :disabled="editMode">
                                                <option value="">Select Target Exam...</option>
                                                <option v-for="ex in availableExams" :key="ex.id" :value="ex.id">{{
                                                    ex.Exam_Title }}</option>
                                            </select>
                                            <small class="text-muted fw-semibold text-end">
                                                {{ focusedItem ? `Currently editing: #${focusedItem}` : 'Currently editing: none' }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="answer-grid mt-3">
                                <div v-for="n in 100" :key="n" class="answer-grid-item">
                                    <div class="answer-box transition-all compact-box" :class="getAnswerBoxClass(n)">
                                        <div class="d-flex justify-content-between px-2 pt-1 compact-label-wrap">
                                            <span class="fw-bold x-small text-muted compact-label">#{{ n }}</span>
                                        </div>
                                        <div class="p-1 compact-select-wrap">
                                            <select v-model="form.answers[n]"
                                                class="form-select form-select-sm border-0 shadow-sm text-center fw-bold compact-select"
                                                :class="getAnswerSelectClass(n)"
                                                @focus="focusedItem = n"
                                                @change="markAnswerTouched(n)"
                                                :ref="'ans' + n">
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

                    <div class="modal-footer bg-light border-top p-3 px-4 shadow-lg sticky-bottom">
                        <button type="button" class="btn btn-light px-4 fw-bold" data-bs-dismiss="modal">CANCEL</button>
                        <button @click="saveFullConfiguration" class="btn btn-emerald px-5 fw-bold shadow-sm"
                            :disabled="isSaving || !form.exam_id">
                            <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"></span>
                            {{ isSaving ? 'PROCESSING...' : 'SAVE ALL DATA' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';

const answerKeys = ref([]);
const availableExams = ref([]);
const EXAM_TYPE_ALIASES = ['entrance', 'screening', 'screening exam'];

const isLoading = ref(false);
const isSaving = ref(false);
const editMode = ref(false);
const currentKeyId = ref(null);
const focusedItem = ref(null);
const hasAttemptedSave = ref(false);
const touchedAnswers = ref({});

const modalRef = ref(null);
let modalInstance = null;

const form = reactive({
    exam_id: '',
    answers: {}
});

const resetForm = () => {
    form.exam_id = '';
    focusedItem.value = null;
    hasAttemptedSave.value = false;
    touchedAnswers.value = {};
    for (let i = 1; i <= 100; i++) form.answers[i] = '';
};

const isAnswerFilled = (n) => String(form.answers[n] || '').trim() !== '';

const markAnswerTouched = (n) => {
    touchedAnswers.value[n] = true;
};

const shouldShowValidation = (n) => hasAttemptedSave.value || touchedAnswers.value[n];

const getAnswerBoxClass = (n) => ({
    'default-block': !shouldShowValidation(n) && focusedItem.value !== n,
    'focused-block': focusedItem.value === n,
    'valid-block': shouldShowValidation(n) && isAnswerFilled(n) && focusedItem.value !== n,
    'invalid-block': shouldShowValidation(n) && !isAnswerFilled(n) && focusedItem.value !== n
});

const getAnswerSelectClass = (n) => ({
    'valid-select': shouldShowValidation(n) && isAnswerFilled(n),
    'invalid-select': shouldShowValidation(n) && !isAnswerFilled(n),
    'focused-select': focusedItem.value === n
});

const hasCompleteAnswers = () => {
    for (let i = 1; i <= 100; i++) {
        if (!isAnswerFilled(i)) return false;
    }
    return true;
};

const fetchData = async () => {
    isLoading.value = true;
    try {
        const [keys, exams] = await Promise.all([
            axios.get('/api/answer-keys'),
            axios.get('/api/exams')
        ]);
        const allExams = Array.isArray(exams.data) ? exams.data : [];
        const filteredExams = allExams.filter(exam =>
            EXAM_TYPE_ALIASES.includes(String(exam?.Exam_Type || '').trim().toLowerCase())
        );
        const allowedExamIds = new Set(filteredExams.map(exam => exam.id));

        availableExams.value = filteredExams;
        answerKeys.value = (Array.isArray(keys.data) ? keys.data : []).filter(key =>
            allowedExamIds.has(key.exam_id)
        );
    } catch (e) {
        console.error('Data fetch failed', e);
    } finally {
        isLoading.value = false;
    }
};

const openModal = (key = null) => {
    resetForm();
    if (key) {
        editMode.value = true;
        currentKeyId.value = key.id;
        form.exam_id = key.exam_id;
        Object.assign(form.answers, key.answers);
    } else {
        editMode.value = false;
        currentKeyId.value = null;
    }
    modalInstance.show();
};

const saveFullConfiguration = async () => {
    hasAttemptedSave.value = true;
    if (!hasCompleteAnswers()) {
        await Swal.fire({
            icon: 'warning',
            title: 'Incomplete Answer Key',
            text: 'Please complete all 100 answer items before saving.',
            confirmButtonColor: '#10b981'
        });
        return;
    }

    isSaving.value = true;
    try {
        const keyData = { exam_id: form.exam_id, answers: form.answers };
        if (editMode.value) {
            await axios.put(`/api/answer-keys/${currentKeyId.value}`, keyData);
        } else {
            await axios.post('/api/answer-keys', keyData);
        }

        modalInstance.hide();
        await fetchData();
        await Swal.fire({
            icon: 'success',
            title: 'Saved',
            text: 'Answer key saved successfully.',
            confirmButtonColor: '#10b981'
        });
    } catch (e) {
        await Swal.fire({
            icon: 'error',
            title: 'Save Failed',
            text: 'Check your inputs.',
            confirmButtonColor: '#ef4444'
        });
    } finally {
        isSaving.value = false;
    }
};

const deleteKey = async (id) => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Delete answer key?',
        text: 'This action cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ef4444'
    });

    if (result.isConfirmed) {
        await axios.delete(`/api/answer-keys/${id}`);
        await fetchData();
        await Swal.fire({
            icon: 'success',
            title: 'Deleted',
            text: 'Answer key deleted successfully.',
            confirmButtonColor: '#10b981'
        });
    }
};

onMounted(() => {
    fetchData();
    modalInstance = new Modal(modalRef.value);
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

.text-emerald {
    color: #10b981;
}

.focus-ring-emerald:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
}

.step-badge {
    width: 28px;
    height: 28px;
    background: #10b981;
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.8rem;
}

.config-modal-dialog {
    max-width: 1080px;
}

.config-modal-body {
    max-height: min(72vh, 840px);
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

.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.btn-light-success {
    color: #10b981;
    background: #ecfdf5;
}

.btn-light-danger {
    color: #ef4444;
    background: #fef2f2;
}

@media (max-width: 991.98px) {
    .answer-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .config-modal-dialog {
        margin: 0.75rem;
    }

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
