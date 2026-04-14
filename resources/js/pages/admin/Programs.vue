<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">Program Management</h4>
                        <p class="text-muted small mb-0">Manage academic degree programs and their affiliations</p>
                    </div>
                    <div class="col-auto">
                        <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-mortarboard me-2"></i> ADD PROGRAM
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
                            placeholder="Search programs...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">No.</th>
                                <th class="py-3 text-secondary small fw-bold">PROGRAM NAME</th>
                                <th class="py-3 text-secondary small fw-bold">COLLEGE</th>
                                <th class="py-3 text-secondary small fw-bold">CREATED AT</th>
                                <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="5" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                    <div class="mt-2 text-muted small">Loading programs...</div>
                                </td>
                            </tr>

                            <template v-else>
                                <tr v-for="(program, index) in filteredPrograms" :key="program.id">
                                    <td class="ps-4 text-muted">#{{ index + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ program.Program_Name }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-emerald border border-emerald-subtle">
                                            <i class="bi bi-diagram-3 me-1"></i>
                                            {{ program.college?.College_Name || 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">{{ program.created_at }}</td>
                                    <td class="pe-4 text-end">
                                        <button @click="openModal(program)" class="btn btn-icon btn-light-success me-2"
                                            :disabled="deletingId === program.id">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteProgram(program.id)" class="btn btn-icon btn-light-danger"
                                            :disabled="deletingId === program.id">
                                            <span v-if="deletingId === program.id"
                                                class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredPrograms.length === 0">
                                    <td colspan="5" class="text-center py-5 text-muted">No programs found.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="programModal" tabindex="-1" aria-hidden="true" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Edit Program' : 'New Program' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveProgram">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Program Name</label>
                                <input v-model="form.Program_Name" type="text" class="form-control"
                                    placeholder="e.g. BSIT" required :disabled="isSaving">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Assigned College</label>
                                <select v-model="form.college_id" class="form-select" required :disabled="isSaving">
                                    <option value="" disabled>Select a college</option>
                                    <option v-for="dept in colleges" :key="dept.id" :value="dept.id">
                                        {{ dept.College_Name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal"
                                :disabled="isSaving">CANCEL</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold shadow-sm" :disabled="isSaving">
                                <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"></span>
                                {{ isSaving ? 'SAVING...' : 'SAVE PROGRAM' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';

const programs = ref([]);
const colleges = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const modalRef = ref(null);
let modalInstance = null;

const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);

const form = reactive({
    Program_Name: '',
    college_id: ''
});

const filteredPrograms = computed(() => {
    return programs.value.filter(p =>
        p.Program_Name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

onMounted(() => {
    fetchPrograms();
    fetchcolleges();
    modalInstance = new Modal(modalRef.value);
});

const fetchPrograms = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/admin/programs');
        programs.value = response.data.data;
    } catch (error) {
        console.error("Fetch Error:", error);
    } finally {
        isLoading.value = false;
    }
};

const fetchcolleges = async () => {
    try {
        const response = await axios.get('/api/admin/colleges');
        colleges.value = response.data.data;
    } catch (error) {
        console.error("Dept Fetch Error:", error);
    }
};

const openModal = (program = null) => {
    if (program) {
        editMode.value = true;
        currentId.value = program.id;
        form.Program_Name = program.Program_Name;
        form.college_id = program.college_id;
    } else {
        editMode.value = false;
        currentId.value = null;
        form.Program_Name = '';
        form.college_id = '';
    }
    modalInstance.show();
};

const saveProgram = async () => {
    isSaving.value = true;
    try {
        if (editMode.value) {
            await axios.put(`/api/admin/programs/${currentId.value}`, form);
        } else {
            await axios.post('/api/admin/programs', form);
        }

        modalInstance.hide();
        await fetchPrograms();

        // Success Toast
        window.Toast.fire({
            icon: 'success',
            title: editMode.value ? 'Program updated' : 'Program created'
        });

    } catch (error) {
        window.Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: error.response?.data?.message || 'Something went wrong!',
            confirmButtonColor: '#10b981'
        });
    } finally {
        isSaving.value = false;
    }
};

const deleteProgram = async (id) => {
    // Warning Confirmation
    const result = await window.Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this program deletion!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        deletingId.value = id;
        try {
            await axios.delete(`/api/admin/programs/${id}`);
            await fetchPrograms();

            window.Toast.fire({
                icon: 'success',
                title: 'Program deleted successfully'
            });
        } catch (error) {
            window.Swal.fire({
                icon: 'error',
                title: 'Delete Failed',
                text: 'This program might be linked to existing students.',
                confirmButtonColor: '#10b981'
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
