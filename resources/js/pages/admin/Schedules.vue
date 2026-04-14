<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">Exam Schedule Management</h4>
                        <p class="text-muted small mb-0">Set dates, times, and venues for upcoming examinations</p>
                    </div>
                    <div class="col-auto">
                        <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-calendar-plus me-2"></i> ADD SCHEDULE
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
                            placeholder="Search by location...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">No.</th>
                                <th class="py-3 text-secondary small fw-bold">DATE & TIME</th>
                                <th class="py-3 text-secondary small fw-bold">NAME</th>
                                <th class="py-3 text-secondary small fw-bold">LOCATION</th>
                                <th class="py-3 text-secondary small fw-bold">CURRENT EXAMINEES</th>
                                <th class="py-3 text-secondary small fw-bold">CAPACITY</th>
                                <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="5" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                    <div class="mt-2 text-muted small">Loading schedules...</div>
                                </td>
                            </tr>

                            <template v-else>
                                <tr v-for="(exam, index) in filteredExams" :key="exam.id">
                                    <td class="ps-4 text-muted">#{{ index + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ exam.date }}</div>
                                        <div class="small text-muted"><i class="bi bi-clock me-1"></i>{{ exam.time }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ exam.schedule_name || '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-geo-alt me-1"></i>{{ exam.location }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-emerald">{{ exam.current_examinees }}</span>
                                        <span class="small text-muted"> registered</span>
                                    </td>
                                    <td>
                                        <span class=" fw-bold text-emerald">{{ exam.capacity }}</span>
                                        <span class="small text-muted"> seats</span>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button @click="openModal(exam)" class="btn btn-icon btn-light-success me-2"
                                            :disabled="deletingId === exam.id">
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
                                    <td colspan="5" class="text-center py-5 text-muted">No schedules found.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="examModal" tabindex="-1" aria-hidden="true" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Edit Schedule' : 'New Exam Schedule' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveExam">
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Date</label>
                                    <input v-model="form.date" type="date" class="form-control" required
                                        :disabled="isSaving">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Time</label>
                                    <input v-model="form.time" type="time" class="form-control" required
                                        :disabled="isSaving">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-uppercase">Schedule Name</label>
                                    <input v-model="form.schedule_name" type="text" class="form-control"
                                        placeholder="e.g.2E" :disabled="isSaving">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-uppercase">Location</label>
                                    <input v-model="form.location" type="text" class="form-control"
                                        placeholder="e.g. Hall A, Room 302" required :disabled="isSaving">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-uppercase">Capacity</label>
                                    <input v-model="form.capacity" type="number" class="form-control"
                                        placeholder="e.g. 50" required :disabled="isSaving">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal"
                                :disabled="isSaving">CANCEL</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold shadow-sm" :disabled="isSaving">
                                <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"></span>
                                {{ isSaving ? 'SAVING...' : 'SAVE SCHEDULE' }}
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

const exams = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const modalRef = ref(null);
let modalInstance = null;

const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);

const form = reactive({
    date: '',
    time: '',
    schedule_name: '',
    location: '',
    capacity: ''
});

const filteredExams = computed(() => {
    return exams.value.filter(e =>
        e.location.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

onMounted(() => {
    fetchExams();
    modalInstance = new Modal(modalRef.value);
});

const fetchExams = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/admin/exam-schedules');
        exams.value = response.data.data;
    } catch (error) {
        console.error("Fetch Error:", error);
    } finally {
        isLoading.value = false;
    }
};

const openModal = (exam = null) => {
    if (exam) {
        editMode.value = true;
        currentId.value = exam.id;
        Object.assign(form, {
            date: exam.date,
            time: exam.time,
            schedule_name: exam.schedule_name || '',
            location: exam.location,
            capacity: exam.capacity
        });
    } else {
        editMode.value = false;
        currentId.value = null;
        Object.assign(form, { date: '', time: '', schedule_name: '', location: '', capacity: '' });
    }
    modalInstance.show();
};

const saveExam = async () => {
    isSaving.value = true;
    try {
        if (editMode.value) {
            await axios.put(`/api/admin/exam-schedules/${currentId.value}`, form);
        } else {
            await axios.post('/api/admin/exam-schedules', form);
        }

        modalInstance.hide();
        await fetchExams();

        // Global Toast Notification
        window.Toast.fire({
            icon: 'success',
            title: editMode.value ? 'Schedule updated' : 'New schedule created'
        });

    } catch (error) {
        window.Swal.fire({
            icon: 'error',
            title: 'Action Failed',
            text: error.response?.data?.message || 'Check your inputs and try again.',
            confirmButtonColor: '#10b981'
        });
    } finally {
        isSaving.value = false;
    }
};

const deleteExam = async (id) => {
    // Global Confirmation Dialog
    const result = await window.Swal.fire({
        title: 'Delete Schedule?',
        text: "This will remove all student assignments for this slot!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        deletingId.value = id;
        try {
            await axios.delete(`/api/admin/exam-schedules/${id}`);
            await fetchExams();

            window.Toast.fire({
                icon: 'success',
                title: 'Schedule removed successfully'
            });
        } catch (error) {
            window.Swal.fire('Error', 'Could not delete the schedule.', 'error');
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

.form-control:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.15);
}
</style>
