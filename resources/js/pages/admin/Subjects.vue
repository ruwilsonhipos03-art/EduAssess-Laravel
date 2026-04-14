<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">Subject Management</h4>
                        <p class="text-muted small mb-0">Create, edit, and manage academic subjects</p>
                    </div>
                    <div class="col-auto">
                        <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-plus-lg me-2"></i> ADD SUBJECT
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
                        <input type="text" v-model="searchQuery" class="form-control border-start-0 ps-0 shadow-none"
                            placeholder="Search subjects...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">No.</th>
                                <th class="py-3 text-secondary small fw-bold">SUBJECT NAME</th>
                                <th class="py-3 text-secondary small fw-bold">CREATED AT</th>
                                <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="4" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                    <div class="mt-2 text-muted small">Loading subjects...</div>
                                </td>
                            </tr>

                            <template v-else>
                                <tr v-for="(subject, index) in filteredSubjects" :key="subject.id">
                                    <td class="ps-4 text-muted">#{{ index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="subject-icon me-3">
                                                <i class="bi bi-book text-emerald"></i>
                                            </div>
                                            <span class="fw-semibold text-dark">{{ subject.Subject_Name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-muted">{{ subject.created_at }}</td>
                                    <td class="pe-4 text-end">
                                        <button @click="openModal(subject)" class="btn btn-icon btn-light-success me-2"
                                            title="Edit" :disabled="deletingId === subject.id">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteSubject(subject.id)" class="btn btn-icon btn-light-danger"
                                            title="Delete" :disabled="deletingId === subject.id">
                                            <span v-if="deletingId === subject.id"
                                                class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="filteredSubjects.length === 0">
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        No subjects found.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="subjectModal" tabindex="-1" aria-hidden="true" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Edit Subject' : 'Add New Subject' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveSubject">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Subject
                                    Name</label>
                                <input v-model="form.Subject_Name" type="text"
                                    class="form-control form-control-lg border-2 shadow-none" placeholder="e.g. Math"
                                    required :disabled="isSaving">
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold text-secondary" data-bs-dismiss="modal"
                                :disabled="isSaving">CANCEL</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold shadow-sm" :disabled="isSaving">
                                <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"></span>
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

// --- State Management ---
// Initialize with empty array to prevent .filter() errors on undefined
const subjects = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const modalRef = ref(null);
let modalInstance = null;

const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);

const form = reactive({
    Subject_Name: ''
});

// --- Computed ---
const filteredSubjects = computed(() => {
    // Ensure subjects.value is treated as an array even before API finishes
    const list = Array.isArray(subjects.value) ? subjects.value : [];
    return list.filter(s =>
        s.Subject_Name?.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// --- Lifecycle ---
onMounted(() => {
    fetchSubjects();
    if (modalRef.value) {
        modalInstance = new Modal(modalRef.value);
    }
});

// --- Methods ---
const fetchSubjects = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/admin/subjects');
        // Because you use SubjectResource::collection, data is in response.data.data
        subjects.value = response.data.data || [];
    } catch (error) {
        console.error("Failed to fetch:", error);
        subjects.value = []; // Reset to empty array on error
    } finally {
        isLoading.value = false;
    }
};

const openModal = (subject = null) => {
    if (subject) {
        editMode.value = true;
        currentId.value = subject.id;
        form.Subject_Name = subject.Subject_Name;
    } else {
        editMode.value = false;
        currentId.value = null;
        form.Subject_Name = '';
    }
    modalInstance.show();
};

const saveSubject = async () => {
    isSaving.value = true;
    try {
        if (editMode.value) {
            await axios.put(`/api/admin/subjects/${currentId.value}`, form);
        } else {
            await axios.post('/api/admin/subjects', form);
        }

        modalInstance.hide();
        await fetchSubjects();

        if (window.Toast) {
            window.Toast.fire({
                icon: 'success',
                title: editMode.value ? 'Subject updated successfully' : 'Subject added successfully'
            });
        }
    } catch (error) {
        if (window.Swal) {
            window.Swal.fire({
                icon: 'error',
                title: 'Save Failed',
                text: error.response?.data?.message || 'Check your network or inputs.',
                confirmButtonColor: '#10b981'
            });
        }
    } finally {
        isSaving.value = false;
    }
};

const deleteSubject = async (id) => {
    if (!window.Swal) return;

    const result = await window.Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this subject!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        deletingId.value = id;
        try {
            await axios.delete(`/api/subjects/${id}`);
            await fetchSubjects();

            if (window.Toast) {
                window.Toast.fire({
                    icon: 'success',
                    title: 'Deleted successfully'
                });
            }
        } catch (error) {
            window.Swal.fire({
                icon: 'error',
                title: 'Delete Failed',
                text: 'The subject could not be deleted.'
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

.subject-icon {
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