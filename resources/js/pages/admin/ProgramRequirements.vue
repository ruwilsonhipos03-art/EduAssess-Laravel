<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">Program Requirements</h4>
                        <p class="text-muted small mb-0">Set subject importance per program (must total exactly 10)</p>
                    </div>
                    <div class="col-auto">
                        <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-list-check me-2"></i> ADD REQUIREMENT
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="px-4 py-3 border-bottom bg-light">
                    <div class="input-group input-group-sm w-25">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" v-model="searchQuery" class="form-control border-start-0 ps-0"
                            placeholder="Search program...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">No.</th>
                                <th class="py-3 text-secondary small fw-bold">PROGRAM</th>
                                <th class="py-3 text-secondary small fw-bold">TOTAL SCORE</th>
                                <th class="py-3 text-secondary small fw-bold text-end">MATH</th>
                                <th class="py-3 text-secondary small fw-bold text-end">ENGLISH</th>
                                <th class="py-3 text-secondary small fw-bold text-end">SCIENCE</th>
                                <th class="py-3 text-secondary small fw-bold text-end">SOCIAL SCIENCE</th>
                                <th class="py-3 text-secondary small fw-bold">CREATED AT</th>
                                <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="9" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                    <div class="mt-2 text-muted small">Loading program requirements...</div>
                                </td>
                            </tr>

                            <template v-else>
                                <tr v-for="(item, index) in filteredRequirements" :key="item.id">
                                    <td class="ps-4 text-muted">#{{ index + 1 }}</td>
                                    <td class="fw-semibold text-dark">{{ item.program?.Program_Name || 'N/A' }}</td>
                                    <td><span class="badge bg-light text-emerald border border-emerald-subtle">{{ item.total_score }}/100</span></td>
                                    <td class="text-end">{{ formatImportance(item.math_scale) }}</td>
                                    <td class="text-end">{{ formatImportance(item.english_scale) }}</td>
                                    <td class="text-end">{{ formatImportance(item.science_scale) }}</td>
                                    <td class="text-end">{{ formatImportance(item.social_science_scale) }}</td>
                                    <td class="text-muted small">{{ item.created_at }}</td>
                                    <td class="pe-4 text-end">
                                        <button @click="openModal(item)" class="btn btn-icon btn-light-success me-2"
                                            :disabled="deletingId === item.id">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteRequirement(item.id)" class="btn btn-icon btn-light-danger"
                                            :disabled="deletingId === item.id">
                                            <span v-if="deletingId === item.id"
                                                class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredRequirements.length === 0">
                                    <td colspan="9" class="text-center py-5 text-muted">No program requirements found.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="programRequirementModal" tabindex="-1" aria-hidden="true" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Edit Program Requirement' : 'New Program Requirement' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveRequirement">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Program</label>
                                <select v-model="form.program_id" class="form-select" required :disabled="isSaving">
                                    <option value="" disabled>Select a program</option>
                                    <option v-for="program in programs" :key="program.id" :value="program.id">
                                        {{ program.Program_Name }}
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Total Score (0-100)</label>
                                <input v-model.number="form.total_score" type="number" class="form-control" min="0" max="100"
                                    required :disabled="isSaving">
                            </div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-uppercase">Math Importance</label>
                                    <input v-model.number="form.math_scale" type="number" class="form-control" min="1" max="10" step="0.01"
                                        required :disabled="isSaving">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-uppercase">English Importance</label>
                                    <input v-model.number="form.english_scale" type="number" class="form-control" min="1" max="10" step="0.01"
                                        required :disabled="isSaving">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-uppercase">Science Importance</label>
                                    <input v-model.number="form.science_scale" type="number" class="form-control" min="1" max="10" step="0.01"
                                        required :disabled="isSaving">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-uppercase">Social Science Importance</label>
                                    <input v-model.number="form.social_science_scale" type="number" class="form-control" min="1" max="10" step="0.01"
                                        required :disabled="isSaving">
                                </div>
                            </div>
                            <div class="mt-3 small fw-semibold" :class="isImportanceValid ? 'text-success' : 'text-danger'">
                                Total Importance: {{ totalImportance.toFixed(2) }}/10
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal"
                                :disabled="isSaving">CANCEL</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold shadow-sm" :disabled="isSaving || !isImportanceValid">
                                <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"></span>
                                {{ isSaving ? 'SAVING...' : 'SAVE REQUIREMENT' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';

const requirements = ref([]);
const programs = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const modalRef = ref(null);
let modalInstance = null;

const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);

const form = reactive({
    program_id: '',
    total_score: 0,
    math_scale: 1,
    english_scale: 1,
    science_scale: 1,
    social_science_scale: 1,
});

const filteredRequirements = computed(() => {
    const q = searchQuery.value.toLowerCase();
    return requirements.value.filter((item) =>
        (item.program?.Program_Name || '').toLowerCase().includes(q)
    );
});

const totalImportance = computed(() => {
    return Number(form.math_scale || 0)
        + Number(form.english_scale || 0)
        + Number(form.science_scale || 0)
        + Number(form.social_science_scale || 0);
});

const isImportanceValid = computed(() => Math.abs(totalImportance.value - 10) < 0.0001);

const formatImportance = (value) => {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return value;
    return numeric.toFixed(2).replace(/\.?0+$/, '');
};

onMounted(() => {
    fetchRequirements();
    fetchPrograms();
    modalInstance = new Modal(modalRef.value);
});

const fetchRequirements = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/admin/program-requirements');
        requirements.value = response.data.data;
    } catch (error) {
        console.error('Fetch requirement error:', error);
    } finally {
        isLoading.value = false;
    }
};

const fetchPrograms = async () => {
    try {
        const response = await axios.get('/api/admin/programs');
        programs.value = response.data.data;
    } catch (error) {
        console.error('Fetch programs error:', error);
    }
};

const resetForm = () => {
    form.program_id = '';
    form.total_score = 0;
    form.math_scale = 1;
    form.english_scale = 1;
    form.science_scale = 1;
    form.social_science_scale = 1;
};

const openModal = (item = null) => {
    if (item) {
        editMode.value = true;
        currentId.value = item.id;
        form.program_id = item.program_id;
        form.total_score = item.total_score;
        form.math_scale = item.math_scale;
        form.english_scale = item.english_scale;
        form.science_scale = item.science_scale;
        form.social_science_scale = item.social_science_scale;
    } else {
        editMode.value = false;
        currentId.value = null;
        resetForm();
    }

    modalInstance.show();
};

const saveRequirement = async () => {
    if (!isImportanceValid.value) {
        window.Swal.fire({
            icon: 'error',
            title: 'Invalid Importance Total',
            text: 'Total importance must be exactly 10.',
            confirmButtonColor: '#10b981',
        });
        return;
    }

    isSaving.value = true;
    try {
        if (editMode.value) {
            await axios.put(`/api/admin/program-requirements/${currentId.value}`, form);
        } else {
            await axios.post('/api/admin/program-requirements', form);
        }

        modalInstance.hide();
        await fetchRequirements();

        window.Toast.fire({
            icon: 'success',
            title: editMode.value ? 'Requirement updated' : 'Requirement created',
        });
    } catch (error) {
        window.Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: error.response?.data?.message || 'Something went wrong!',
            confirmButtonColor: '#10b981',
        });
    } finally {
        isSaving.value = false;
    }
};

const deleteRequirement = async (id) => {
    const result = await window.Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this requirement deletion!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
    });

    if (result.isConfirmed) {
        deletingId.value = id;
        try {
            await axios.delete(`/api/admin/program-requirements/${id}`);
            await fetchRequirements();

            window.Toast.fire({
                icon: 'success',
                title: 'Requirement deleted successfully',
            });
        } catch (error) {
            window.Swal.fire({
                icon: 'error',
                title: 'Delete Failed',
                text: error.response?.data?.message || 'Unable to delete this requirement.',
                confirmButtonColor: '#10b981',
            });
        } finally {
            deletingId.value = null;
        }
    }
};
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

.bg-emerald {
    background-color: #10b981;
}

.text-emerald {
    color: #10b981;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
    border: none;
}

.btn-light-success {
    color: #10b981;
    background-color: #ecfdf5;
}

.btn-light-success:hover {
    background-color: #10b981;
    color: white;
}

.btn-light-danger {
    color: #ef4444;
    background-color: #fef2f2;
}

.btn-light-danger:hover {
    background-color: #ef4444;
    color: white;
}

.form-control:focus,
.form-select:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.15);
}
</style>
