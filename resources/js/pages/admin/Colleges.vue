<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">College Management</h4>
                        <p class="text-muted small mb-0">Organize and manage institutional colleges</p>
                    </div>
                    <div class="col-auto">
                        <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-plus-lg me-2"></i> ADD COLLEGE
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
                            placeholder="Search colleges...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">No.</th>
                                <th class="py-3 text-secondary small fw-bold">COLLEGE NAME</th>
                                <th class="py-3 text-secondary small fw-bold">CREATED AT</th>
                                <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="4" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div class="mt-2 text-muted small">Loading colleges...</div>
                                </td>
                            </tr>

                            <template v-else>
                                <tr v-for="(dept, index) in filteredcolleges" :key="dept.id">
                                    <td class="ps-4 text-muted">#{{ index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="dept-icon me-3">
                                                <i class="bi bi-diagram-3 text-emerald"></i>
                                            </div>
                                            <span class="fw-semibold text-dark">{{ dept.College_Name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-muted">{{ dept.created_at }}</td>
                                    <td class="pe-4 text-end">
                                        <button @click="openModal(dept)" class="btn btn-icon btn-light-success me-2"
                                            title="Edit" :disabled="deletingId === dept.id">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteDepartment(dept)" class="btn btn-icon btn-light-danger"
                                            title="Delete" :disabled="deletingId === dept.id">
                                            <span v-if="deletingId === dept.id" class="spinner-border spinner-border-sm"
                                                role="status"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="filteredcolleges.length === 0">
                                    <td colspan="4" class="text-center py-5 text-muted">No colleges found.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deptModal" tabindex="-1" aria-hidden="true" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Edit College' : 'Add New College' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveDepartment">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase">College
                                    Name</label>
                                <input v-model="form.College_Name" type="text"
                                    class="form-control form-control-lg border-2"
                                    placeholder="e.g. College of Information Technology" required
                                    :disabled="isSaving">
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold text-secondary" data-bs-dismiss="modal"
                                :disabled="isSaving">CANCEL</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold shadow-sm" :disabled="isSaving">
                                <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"
                                    role="status"></span>
                                {{ isSaving ? 'SAVING...' : 'SAVE CHANGES' }}
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
import Swal from 'sweetalert2';

// State
const colleges = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const modalRef = ref(null);
let modalInstance = null;

const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);

const form = reactive({ College_Name: '' });

// Configure SweetAlert2 Toast
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

const filteredcolleges = computed(() => {
    return colleges.value.filter(d =>
        d.College_Name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

onMounted(() => {
    fetchcolleges();
    modalInstance = new Modal(modalRef.value);
});

const fetchcolleges = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/admin/colleges');
        colleges.value = response.data.data || response.data;
    } catch (error) {
        console.error("Fetch error:", error);
    } finally {
        isLoading.value = false;
    }
};

const openModal = (dept = null) => {
    if (dept) {
        editMode.value = true;
        currentId.value = dept.id;
        form.College_Name = dept.College_Name;
    } else {
        editMode.value = false;
        currentId.value = null;
        form.College_Name = '';
    }
    modalInstance.show();
};

const saveDepartment = async () => {
    isSaving.value = true;
    try {
        if (editMode.value) {
            await axios.put(`/api/admin/colleges/${currentId.value}`, form);
            Toast.fire({ icon: 'success', title: 'College updated successfully' });
        } else {
            await axios.post('/api/admin/colleges', form);
            Toast.fire({ icon: 'success', title: 'New college added' });
        }
        modalInstance.hide();
        await fetchcolleges();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: error.response?.data?.message || 'Something went wrong!',
            confirmButtonColor: '#10b981'
        });
    } finally {
        isSaving.value = false;
    }
};

const deleteDepartment = async (dept) => {
    const result = await Swal.fire({
        title: 'Delete College?',
        text: `Are you sure you want to delete "${dept.College_Name}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        deletingId.value = dept.id;
        try {
            await axios.delete(`/api/admin/colleges/${dept.id}`);
            await fetchcolleges();
            Toast.fire({ icon: 'success', title: 'College has been deleted' });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Delete Failed',
                text: error.response?.data?.message || 'Could not delete the college.',
                confirmButtonColor: '#10b981'
            });
        } finally {
            deletingId.value = null;
        }
    }
};
</script>

<style scoped>
/* Emerald Theme Styles */
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
}

.btn-light-success {
    color: #10b981;
    background-color: #ecfdf5;
    border: none;
}

.btn-light-success:hover {
    background-color: #10b981;
    color: white;
}

.btn-light-danger {
    color: #ef4444;
    background-color: #fef2f2;
    border: none;
}

.btn-light-danger:hover {
    background-color: #ef4444;
    color: white;
}

.dept-icon {
    width: 35px;
    height: 35px;
    background-color: #f0fdf4;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.form-control:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.15);
}
</style>
