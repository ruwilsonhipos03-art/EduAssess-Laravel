<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">Office Management</h4>
                        <p class="text-muted small mb-0">Create, edit, and manage corporate offices</p>
                    </div>
                    <div class="col-auto">
                        <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-plus-lg me-2"></i> ADD OFFICE
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
                            placeholder="Search offices...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">No</th>
                                <th class="py-3 text-secondary small fw-bold">OFFICE NAME</th>
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
                                    <div class="mt-2 text-muted small">Loading offices...</div>
                                </td>
                            </tr>

                            <template v-else>
                                <tr v-for="(office, index) in filteredOffices" :key="office.id">
                                    <td class="ps-4 text-muted">#{{ index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="office-icon me-3">
                                                <i class="bi bi-building text-emerald"></i>
                                            </div>
                                            <span class="fw-semibold text-dark">{{ office.Office_Name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-muted">{{ office.created_at }}</td>
                                    <td class="pe-4 text-end">
                                        <button @click="openModal(office)" class="btn btn-icon btn-light-success me-2"
                                            title="Edit" :disabled="deletingId === office.id">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteOffice(office.id)" class="btn btn-icon btn-light-danger"
                                            title="Delete" :disabled="deletingId === office.id">
                                            <span v-if="deletingId === office.id"
                                                class="spinner-border spinner-border-sm" role="status"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="filteredOffices.length === 0">
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        No offices found.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="officeModal" tabindex="-1" aria-hidden="true" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Edit Office' : 'Add New Office' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveOffice">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Office
                                    Name</label>
                                <input v-model="form.Office_Name" type="text"
                                    class="form-control form-control-lg border-2" placeholder="e.g. Guidance Office"
                                    required :disabled="isSaving">
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

// State Management
const offices = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const modalRef = ref(null);
let modalInstance = null;

// Loading States
const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);

const form = reactive({
    Office_Name: ''
});

// Search functionality
const filteredOffices = computed(() => {
    return offices.value.filter(o =>
        o.Office_Name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

onMounted(() => {
    fetchOffices();
    modalInstance = new Modal(modalRef.value);
});

const fetchOffices = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/admin/offices');
        offices.value = response.data.data || response.data;
    } catch (error) {
        console.error("Failed to fetch offices:", error);
    } finally {
        isLoading.value = false;
    }
};

const openModal = (office = null) => {
    if (office) {
        editMode.value = true;
        currentId.value = office.id;
        form.Office_Name = office.Office_Name;
    } else {
        editMode.value = false;
        currentId.value = null;
        form.Office_Name = '';
    }
    modalInstance.show();
};

const saveOffice = async () => {
    isSaving.value = true;
    try {
        if (editMode.value) {
            await axios.put(`/api/admin/offices/${currentId.value}`, form);
        } else {
            await axios.post('/api/admin/offices', form);
        }

        modalInstance.hide();
        await fetchOffices();

        // Success Toast
        window.Toast.fire({
            icon: 'success',
            title: editMode.value ? 'Office updated successfully' : 'Office created successfully'
        });

    } catch (error) {
        window.Swal.fire({
            icon: 'error',
            title: 'Save Failed',
            text: error.response?.data?.message || 'Error saving office',
            confirmButtonColor: '#10b981'
        });
    } finally {
        isSaving.value = false;
    }
};

const deleteOffice = async (id) => {
    const result = await window.Swal.fire({
        title: 'Delete Office?',
        text: "This action cannot be undone. All linked data may be affected.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        deletingId.value = id;
        try {
            await axios.delete(`/api/admin/offices/${id}`);
            await fetchOffices();

            window.Toast.fire({
                icon: 'success',
                title: 'Office has been deleted'
            });
        } catch (error) {
            window.Swal.fire({
                icon: 'error',
                title: 'Delete Error',
                text: 'This office could not be deleted. It may be in use by other records.',
                confirmButtonColor: '#10b981'
            });
        } finally {
            deletingId.value = null;
        }
    }
};
</script>

<style scoped>
/* Emerald Palette */
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

/* Action Buttons */
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

/* Icons and UI Elements */
.office-icon {
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

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
}
</style>
