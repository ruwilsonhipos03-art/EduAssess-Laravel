<template>
    <div class="container-fluid py-2 py-md-4">
        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body p-3 p-md-4">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-md">
                        <h4 class="fw-bold mb-1 text-dark">Generate Answer Sheets</h4>
                        <p class="text-muted small mb-0 d-none d-sm-block">Create and download OMR bubble sheets</p>
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button @click="downloadSelectedSheets" class="btn btn-light-success fw-bold px-4"
                            :disabled="selectedSheetIds.length === 0 || isPrintingSelected || isDeletingSelected">
                            <span v-if="isPrintingSelected" class="spinner-border spinner-border-sm me-2"></span>
                            <i v-else class="bi bi-download me-2"></i>
                            {{ isPrintingSelected ? 'DOWNLOADING...' : 'DOWNLOAD SELECTED' }}
                        </button>
                        <button @click="deleteSelectedSheets" class="btn btn-light-danger fw-bold px-4"
                            :disabled="selectedSheetIds.length === 0 || isDeletingSelected || isPrintingSelected">
                            <span v-if="isDeletingSelected" class="spinner-border spinner-border-sm me-2"></span>
                            <i v-else class="bi bi-trash3 me-2"></i>
                            {{ isDeletingSelected ? 'DELETING...' : 'DELETE SELECTED' }}
                        </button>
                        <button @click="openModal" class="btn btn-emerald fw-bold px-4">
                            <i class="bi bi-plus-circle me-2"></i> GENERATE
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
                            <th class="py-3 ps-3 text-secondary small fw-bold">
                                <input class="form-check-input" type="checkbox"
                                    :checked="sheets.length > 0 && selectedSheetIds.length === sheets.length"
                                    @change="toggleSelectAll">
                            </th>
                            <th class="py-3 text-secondary small fw-bold">SHEET CODE</th>
                            <th class="py-3 text-secondary small fw-bold">EXAM</th>
                            <th class="py-3 text-secondary small fw-bold">STATUS</th>
                            <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="5" class="text-center py-5">
                                <div class="spinner-border text-emerald"></div>
                                <p class="text-muted mt-2 mb-0">Loading data...</p>
                            </td>
                        </tr>

                        <tr v-else-if="sheets.length === 0">
                            <td colspan="5" class="text-center py-5">
                                <i class="bi bi-file-earmark-text text-muted mb-2 d-block" style="font-size: 2rem;"></i>
                                <span class="text-muted">No answer sheets found.</span>
                            </td>
                        </tr>

                        <tr v-else v-for="sheet in sheets" :key="sheet.id">
                            <td class="ps-3">
                                <input class="form-check-input" type="checkbox" :value="sheet.id"
                                    v-model="selectedSheetIds">
                            </td>
                            <td class="fw-bold text-emerald">{{ sheet.qr_payload }}</td>
                            <td>{{ sheet.exam?.Exam_Title || 'N/A' }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ sheet.status }}</span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="btn-group shadow-sm rounded-3">
                                    <button @click="downloadSheet(sheet.id)" class="btn btn-light-success border-0 px-3"
                                        title="Download" :disabled="printingId === sheet.id || deletingId === sheet.id">
                                        <span v-if="printingId === sheet.id" class="spinner-border spinner-border-sm"></span>
                                        <i v-else class="bi bi-download"></i>
                                    </button>
                                    <button @click="deleteSheet(sheet.id)" class="btn btn-light-danger border-0 px-3"
                                        title="Delete" :disabled="deletingId === sheet.id || printingId === sheet.id">
                                        <span v-if="deletingId === sheet.id" class="spinner-border spinner-border-sm"></span>
                                        <i v-else class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal fade" id="generateModal" tabindex="-1" ref="modalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0">
                    <div class="modal-header bg-dark text-white border-0">
                        <h5 class="modal-title fw-bold">Generate Answer Sheets</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">SELECT EXAM</label>
                            <select v-model="form.exam_id"
                                class="form-select border-2 fw-bold text-emerald focus-ring-emerald">
                                <option value="">Select Exam...</option>
                                <option v-for="ex in availableExams" :key="ex.id" :value="ex.id">
                                    {{ ex.Exam_Title }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">NUMBER OF SHEETS</label>
                            <input v-model.number="form.count" type="number" min="1" max="200"
                                class="form-control border-2" placeholder="e.g. 5">
                        </div>
                        <p class="small text-muted mb-0">This will generate answer sheets and download the PDF.</p>
                    </div>
                    <div class="modal-footer bg-light border-top">
                        <button type="button" class="btn btn-light px-4 fw-bold" data-bs-dismiss="modal">CANCEL</button>
                        <button @click="generateSheets" class="btn btn-emerald px-4 fw-bold"
                            :disabled="isGenerating || !form.exam_id || !form.count">
                            <span v-if="isGenerating" class="spinner-border spinner-border-sm me-2"></span>
                            {{ isGenerating ? 'GENERATING / DOWNLOADING...' : 'GENERATE / DOWNLOAD' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';

// State
const sheets = ref([]);
const availableExams = ref([]);
const isLoading = ref(false);
const isGenerating = ref(false);
const selectedSheetIds = ref([]);
const printingId = ref(null);
const deletingId = ref(null);
const isPrintingSelected = ref(false);
const isDeletingSelected = ref(false);
const TERM_TYPE_ALIASES = ['term', 'term exam', 'departmental', 'normal', 'normal exam'];

const form = reactive({
    exam_id: '',
    count: 1
});

const modalRef = ref(null);
let modalInstance = null;

// SweetAlert Mixin for notifications
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});

const fetchSheets = async () => {
    isLoading.value = true;
    try {
        const [sheetsRes, examsRes] = await Promise.all([
            axios.get('/api/answer-sheets'),
            axios.get('/api/exams', { params: { scope: 'term' } }),
        ]);

        // Always show highest sheet id first.
        sheets.value = Array.isArray(sheetsRes.data)
            ? [...sheetsRes.data].sort((a, b) => Number(b?.id || 0) - Number(a?.id || 0))
            : [];
        const validIds = new Set(sheets.value.map((sheet) => sheet.id));
        selectedSheetIds.value = selectedSheetIds.value.filter((id) => validIds.has(id));
        const rawExams = Array.isArray(examsRes.data) ? examsRes.data : [];
        availableExams.value = rawExams.filter((exam) =>
            TERM_TYPE_ALIASES.includes(String(exam?.Exam_Type || '').trim().toLowerCase())
        );
    } catch (e) {
        console.error('Failed to load data', e);
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Failed to fetch data from the server.',
            confirmButtonColor: '#10b981'
        });
    } finally {
        isLoading.value = false;
    }
};

const toggleSelectAll = (event) => {
    if (event.target.checked) {
        selectedSheetIds.value = sheets.value.map((sheet) => sheet.id);
        return;
    }
    selectedSheetIds.value = [];
};

const openModal = () => {
    form.exam_id = '';
    form.count = 1;
    modalInstance.show();
};

const generateSheets = async () => {
    isGenerating.value = true;
    try {
        const res = await axios.post(
            '/api/answer-sheets/generate',
            { exam_id: form.exam_id, count: form.count },
            { responseType: 'blob' }
        );

        const blob = new Blob([res.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const headerName = res.headers['content-disposition'];
        const match = headerName?.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
        const fileName = match ? match[1].replace(/['"]/g, '') : `answer_sheets_generated.pdf`;

        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        modalInstance.hide();
        await fetchSheets();

        Toast.fire({
            icon: 'success',
            title: 'Answer sheets generated and downloaded'
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Generation Failed',
            text: 'Could not generate/download sheets. Check your network or inputs.',
            confirmButtonColor: '#ef4444'
        });
    } finally {
        isGenerating.value = false;
    }
};

const downloadSelectedSheets = async () => {
    if (selectedSheetIds.value.length === 0) return;

    try {
        isPrintingSelected.value = true;
        const res = await axios.post(
            '/api/answer-sheets/print-selected',
            { sheet_ids: selectedSheetIds.value },
            { responseType: 'blob' }
        );

        const blob = new Blob([res.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const headerName = res.headers['content-disposition'];
        const match = headerName?.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
        const fileName = match ? match[1].replace(/['"]/g, '') : `answer_sheets_selected.pdf`;

        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        Toast.fire({
            icon: 'success',
            title: `${selectedSheetIds.value.length} sheet${selectedSheetIds.value.length > 1 ? 's' : ''} downloaded`
        });
    } catch (e) {
        let message = 'Could not download selected sheets.';
        const maybeBlob = e?.response?.data;
        if (maybeBlob instanceof Blob) {
            try {
                const text = await maybeBlob.text();
                const parsed = JSON.parse(text);
                message = parsed?.message || message;
            } catch (_) {
                // Keep fallback message
            }
        } else {
            message = e?.response?.data?.message || message;
        }
        Swal.fire('Error', message, 'error');
    } finally {
        isPrintingSelected.value = false;
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
    } catch (e) {
        Toast.fire({
            icon: 'error',
            title: 'Failed to download sheet'
        });
    } finally {
        printingId.value = null;
    }
};

const deleteSheet = async (id) => {
    const result = await Swal.fire({
        title: 'Delete Sheet?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        try {
            deletingId.value = id;
            await axios.delete(`/api/answer-sheets/${id}`);
            await fetchSheets();
            Toast.fire({
                icon: 'success',
                title: 'Sheet deleted'
            });
        } catch (e) {
            Swal.fire('Error', 'Could not delete the record.', 'error');
        } finally {
            deletingId.value = null;
        }
    }
};

const deleteSelectedSheets = async () => {
    if (selectedSheetIds.value.length === 0) return;

    const count = selectedSheetIds.value.length;
    const result = await Swal.fire({
        title: `Delete ${count} sheet${count > 1 ? 's' : ''}?`,
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete selected'
    });

    if (!result.isConfirmed) return;

    try {
        isDeletingSelected.value = true;
        await Promise.all(selectedSheetIds.value.map((id) => axios.delete(`/api/answer-sheets/${id}`)));
        selectedSheetIds.value = [];
        await fetchSheets();
        Toast.fire({
            icon: 'success',
            title: `${count} sheet${count > 1 ? 's' : ''} deleted`
        });
    } catch (e) {
        Swal.fire('Error', 'Could not delete selected records.', 'error');
    } finally {
        isDeletingSelected.value = false;
    }
};

onMounted(() => {
    fetchSheets();
    if (modalRef.value) {
        modalInstance = new Modal(modalRef.value);
    }
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

.btn-emerald:disabled {
    background-color: #6ee7b7;
    opacity: 0.7;
}

.text-emerald {
    color: #10b981;
}

.focus-ring-emerald:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
}

.btn-light-success {
    color: #10b981;
    background: #ecfdf5;
}

.btn-light-success:hover {
    background: #d1fae5;
}

.btn-light-danger {
    color: #ef4444;
    background: #fef2f2;
}

.btn-light-danger:hover {
    background: #fee2e2;
}
</style>
