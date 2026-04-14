<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg">
                        <h4 class="fw-bold mb-1 text-dark">Screening Exam Schedule</h4>
                        <p class="text-muted small mb-0">
                            Schedule only students who already passed the entrance exam.
                        </p>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label small fw-bold text-secondary">SCREENING EXAM</label>
                        <select v-model="selectedExamId" class="form-select" :disabled="isLoadingExams || exams.length === 0">
                            <option value="">Select screening exam...</option>
                            <option v-for="exam in exams" :key="exam.id" :value="String(exam.id)">
                                {{ exam.Exam_Title }}{{ exam.program?.Program_Name ? ` - ${exam.program.Program_Name}` : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-auto">
                        <button class="btn btn-light-success fw-bold px-4 me-2" @click="openScheduleModal()" :disabled="isBusy">
                            <i class="bi bi-calendar-plus me-2"></i>Add Screening Schedule
                        </button>
                        <button class="btn btn-emerald fw-bold px-4" @click="refreshData" :disabled="!selectedExamId || isBusy">
                            <span v-if="isBusy" class="spinner-border spinner-border-sm me-2"></span>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!selectedExamId" class="card shadow-sm border-0">
            <div class="card-body p-5 text-center text-muted">
                Select a screening exam to manage its schedules and eligible students.
            </div>
        </div>

        <template v-else>
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-lg">
                                    <h6 class="fw-bold mb-1">Eligible Students</h6>
                                    <p class="text-muted small mb-0">
                                        Only students who passed the entrance exam and selected this program are listed here.
                                    </p>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label small fw-bold text-secondary">SEARCH</label>
                                    <input v-model.trim="searchQuery" type="text" class="form-control" placeholder="Search by student name or number">
                                </div>
                                <div class="col-12 col-lg-auto">
                                    <button
                                        class="btn btn-emerald fw-bold px-4"
                                        @click="assignSelectedStudents"
                                        :disabled="!selectedScheduleId || selectedStudentIds.length === 0 || isAssigning"
                                    >
                                        <span v-if="isAssigning" class="spinner-border spinner-border-sm me-2"></span>
                                        Assign Selected
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="isLoadingStudents" class="text-center py-5 text-muted">
                                <div class="spinner-border text-emerald"></div>
                                <div class="small mt-2">Loading eligible students...</div>
                            </div>
                            <div v-else-if="filteredStudents.length === 0" class="text-center py-5 text-muted">
                                No eligible students found for this screening exam.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    :checked="filteredStudents.length > 0 && selectedStudentIds.length === filteredStudents.length"
                                                    @change="toggleSelectAll"
                                                >
                                            </th>
                                            <th>Student #</th>
                                            <th>Name</th>
                                            <th>Entrance Score</th>
                                            <th>Choice Rank</th>
                                            <th>Current Schedule</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="student in filteredStudents" :key="student.user_id">
                                            <td class="ps-3">
                                                <input class="form-check-input" type="checkbox" :value="student.user_id" v-model="selectedStudentIds">
                                            </td>
                                            <td>{{ student.student_number || '-' }}</td>
                                            <td>{{ student.student_name || '-' }}</td>
                                            <td>{{ student.entrance_score ?? '-' }}</td>
                                            <td>{{ formatRank(student.program_rank) }}</td>
                                            <td>
                                                <span v-if="student.exam_schedule_id" class="badge bg-light text-dark border">
                                                    {{ scheduleLabelById(student.exam_schedule_id) }}
                                                </span>
                                                <span v-else class="text-muted small">Not scheduled</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-1">Available Schedules</h6>
                            <p class="text-muted small mb-0">Choose a schedule slot for the selected screening exam.</p>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <div v-if="isLoadingSchedules" class="text-center py-5 text-muted">
                                <div class="spinner-border text-emerald"></div>
                                <div class="small mt-2">Loading schedules...</div>
                            </div>
                            <div v-else-if="schedules.length === 0" class="text-muted small">
                                No schedules found. Ask the admin to create exam schedules first.
                            </div>
                            <div v-else class="schedule-list">
                                <button
                                    v-for="schedule in schedules"
                                    :key="schedule.id"
                                    type="button"
                                    class="schedule-card text-start"
                                    :class="{ active: Number(selectedScheduleId) === Number(schedule.id) }"
                                    @click="selectedScheduleId = String(schedule.id)"
                                >
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div v-if="schedule.schedule_name" class="small fw-semibold text-emerald mb-1">{{ schedule.schedule_name }}</div>
                                            <div class="fw-bold text-dark">{{ schedule.formatted_date || schedule.date }}</div>
                                            <div class="small text-muted">{{ schedule.time }} | {{ schedule.location }}</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge rounded-pill bg-light text-dark border">
                                                {{ schedule.assigned_students }}/{{ schedule.capacity }}
                                            </span>
                                            <button class="btn btn-icon btn-light-success" @click.stop="openScheduleModal(schedule)" :disabled="isDeletingScheduleId === schedule.id">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button class="btn btn-icon btn-light-danger" @click.stop="deleteSchedule(schedule)" :disabled="isDeletingScheduleId === schedule.id">
                                                <span v-if="isDeletingScheduleId === schedule.id" class="spinner-border spinner-border-sm"></span>
                                                <i v-else class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-1">Assigned Students</h6>
                                <p class="text-muted small mb-0">
                                    {{ selectedScheduleLabel || 'Select a schedule to view assigned students.' }}
                                </p>
                            </div>
                            <span v-if="selectedSchedule" class="badge bg-light text-dark border">
                                {{ selectedSchedule.assigned_students }} assigned
                            </span>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <div v-if="!selectedSchedule" class="text-muted small">Choose a schedule from the left panel first.</div>
                            <div v-else-if="selectedAssignedStudents.length === 0" class="text-muted small">No students assigned to this schedule yet.</div>
                            <div v-else class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student #</th>
                                            <th>Name</th>
                                            <th>Entrance Score</th>
                                            <th>Choice Rank</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="student in selectedAssignedStudents" :key="student.user_id">
                                            <td>{{ student.student_number || '-' }}</td>
                                            <td>{{ student.student_name || '-' }}</td>
                                            <td>{{ student.entrance_score ?? '-' }}</td>
                                            <td>{{ formatRank(student.program_rank) }}</td>
                                            <td class="text-end">
                                                <button
                                                    class="btn btn-light-danger btn-sm"
                                                    @click="unassignStudent(student)"
                                                    :disabled="isUnassigningUserId === student.user_id"
                                                >
                                                    <span v-if="isUnassigningUserId === student.user_id" class="spinner-border spinner-border-sm"></span>
                                                    <span v-else>Remove</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="modal fade" id="scheduleModal" tabindex="-1" ref="scheduleModalRef">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ scheduleEditId ? 'Edit Screening Schedule' : 'New Screening Schedule' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveSchedule">
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Date</label>
                                    <input v-model="scheduleForm.date" type="date" class="form-control" required :disabled="isSavingSchedule">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Time</label>
                                    <input v-model="scheduleForm.time" type="time" class="form-control" required :disabled="isSavingSchedule">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-uppercase">Schedule Name</label>
                                    <input v-model="scheduleForm.schedule_name" type="text" class="form-control" :disabled="isSavingSchedule">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-uppercase">Location</label>
                                    <input v-model="scheduleForm.location" type="text" class="form-control" required :disabled="isSavingSchedule">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-uppercase">Capacity</label>
                                    <input v-model.number="scheduleForm.capacity" type="number" min="1" class="form-control" required :disabled="isSavingSchedule">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal" :disabled="isSavingSchedule">Cancel</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold" :disabled="isSavingSchedule">
                                <span v-if="isSavingSchedule" class="spinner-border spinner-border-sm me-2"></span>
                                {{ isSavingSchedule ? 'Saving...' : 'Save Schedule' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';

const exams = ref([]);
const schedules = ref([]);
const students = ref([]);
const selectedExamId = ref('');
const selectedScheduleId = ref('');
const selectedStudentIds = ref([]);
const searchQuery = ref('');

const isLoadingExams = ref(false);
const isLoadingSchedules = ref(false);
const isLoadingStudents = ref(false);
const isAssigning = ref(false);
const isUnassigningUserId = ref(null);
const isSavingSchedule = ref(false);
const isDeletingScheduleId = ref(null);
const scheduleEditId = ref(null);
const scheduleModalRef = ref(null);
let scheduleModalInstance = null;

const scheduleForm = reactive({
    date: '',
    time: '',
    schedule_name: '',
    location: '',
    capacity: 1,
});

const isBusy = computed(() =>
    isLoadingExams.value || isLoadingSchedules.value || isLoadingStudents.value || isAssigning.value || isUnassigningUserId.value !== null || isSavingSchedule.value || isDeletingScheduleId.value !== null
);

const selectedSchedule = computed(() =>
    schedules.value.find((schedule) => Number(schedule.id) === Number(selectedScheduleId.value)) || null
);

const selectedScheduleLabel = computed(() => {
    if (!selectedSchedule.value) return '';
    return `${selectedSchedule.value.formatted_date || selectedSchedule.value.date} | ${selectedSchedule.value.time} | ${selectedSchedule.value.location}`;
});

const selectedAssignedStudents = computed(() => selectedSchedule.value?.students || []);

const filteredStudents = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return students.value;

    return students.value.filter((student) => {
        const haystack = [
            student.student_number,
            student.student_name,
            student.first_name,
            student.middle_initial,
            student.last_name,
        ].map((value) => String(value || '').toLowerCase()).join(' ');

        return haystack.includes(query);
    });
});

const extractArray = (payload) => {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    return [];
};

const formatRank = (rank) => {
    const value = Number(rank || 0);
    if (value === 1) return '1st';
    if (value === 2) return '2nd';
    if (value === 3) return '3rd';
    return value > 0 ? `${value}th` : '-';
};

const scheduleLabelById = (scheduleId) => {
    const schedule = schedules.value.find((item) => Number(item.id) === Number(scheduleId));
    if (!schedule) return 'Assigned';
    return `${schedule.formatted_date || schedule.date} | ${schedule.time}`;
};

const resetScheduleForm = () => {
    scheduleEditId.value = null;
    scheduleForm.date = '';
    scheduleForm.time = '';
    scheduleForm.schedule_name = '';
    scheduleForm.location = '';
    scheduleForm.capacity = 1;
};

const openScheduleModal = (schedule = null) => {
    resetScheduleForm();
    if (schedule) {
        scheduleEditId.value = Number(schedule.id);
        scheduleForm.date = String(schedule.date || '');
        scheduleForm.time = String(schedule.time || '');
        scheduleForm.schedule_name = String(schedule.schedule_name || '');
        scheduleForm.location = String(schedule.location || '');
        scheduleForm.capacity = Number(schedule.capacity || 1);
    }
    scheduleModalInstance?.show();
};

const fetchExams = async () => {
    isLoadingExams.value = true;
    try {
        const response = await axios.get('/api/exams', { params: { scope: 'screening' } });
        exams.value = extractArray(response.data);
        if (!selectedExamId.value && exams.value[0]) {
            selectedExamId.value = String(exams.value[0].id);
        }
    } catch (error) {
        exams.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load screening exams',
            text: error?.response?.data?.message || 'Please try again.',
        });
    } finally {
        isLoadingExams.value = false;
    }
};

const refreshData = async () => {
    if (!selectedExamId.value) {
        schedules.value = [];
        students.value = [];
        selectedScheduleId.value = '';
        selectedStudentIds.value = [];
        return;
    }

    isLoadingSchedules.value = true;
    isLoadingStudents.value = true;

    try {
        const [scheduleResponse, studentResponse] = await Promise.all([
            axios.get('/api/college_dean/screening-schedules', {
                params: { exam_id: Number(selectedExamId.value) },
            }),
            axios.get('/api/college_dean/screening-schedules/eligible-students', {
                params: { exam_id: Number(selectedExamId.value) },
            }),
        ]);

        schedules.value = extractArray(scheduleResponse.data);
        students.value = extractArray(studentResponse.data);

        const hasSelectedSchedule = schedules.value.some((schedule) => Number(schedule.id) === Number(selectedScheduleId.value));
        if (!hasSelectedSchedule) {
            selectedScheduleId.value = schedules.value[0] ? String(schedules.value[0].id) : '';
        }

        const visibleIds = new Set(filteredStudents.value.map((student) => Number(student.user_id)));
        selectedStudentIds.value = selectedStudentIds.value.filter((id) => visibleIds.has(Number(id)));
    } catch (error) {
        schedules.value = [];
        students.value = [];
        selectedScheduleId.value = '';
        selectedStudentIds.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load schedule data',
            text: error?.response?.data?.message || 'Please refresh and try again.',
        });
    } finally {
        isLoadingSchedules.value = false;
        isLoadingStudents.value = false;
    }
};

const saveSchedule = async () => {
    isSavingSchedule.value = true;
    try {
        const payload = {
            date: scheduleForm.date,
            time: scheduleForm.time,
            schedule_name: scheduleForm.schedule_name,
            location: scheduleForm.location,
            capacity: Number(scheduleForm.capacity || 0),
        };

        if (scheduleEditId.value) {
            await axios.put(`/api/college_dean/screening-schedules/${scheduleEditId.value}`, payload);
        } else {
            await axios.post('/api/college_dean/screening-schedules', payload);
        }

        scheduleModalInstance?.hide();
        await refreshData();
        window.Toast?.fire({
            icon: 'success',
            title: scheduleEditId.value ? 'Screening schedule updated' : 'Screening schedule created',
        });
    } catch (error) {
        window.Swal?.fire({
            icon: 'error',
            title: 'Unable to save schedule',
            text: error?.response?.data?.message || 'Please check the schedule details and try again.',
        });
    } finally {
        isSavingSchedule.value = false;
    }
};

const deleteSchedule = async (schedule) => {
    const result = await window.Swal?.fire({
        title: 'Delete screening schedule?',
        text: 'This will also remove any assigned screening students for that slot.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it',
    });

    if (!result?.isConfirmed) return;

    isDeletingScheduleId.value = Number(schedule.id);
    try {
        await axios.delete(`/api/college_dean/screening-schedules/${Number(schedule.id)}`);
        if (Number(selectedScheduleId.value) === Number(schedule.id)) {
            selectedScheduleId.value = '';
        }
        await refreshData();
        window.Toast?.fire({
            icon: 'success',
            title: 'Screening schedule deleted',
        });
    } catch (error) {
        window.Swal?.fire({
            icon: 'error',
            title: 'Unable to delete schedule',
            text: error?.response?.data?.message || 'Please try again.',
        });
    } finally {
        isDeletingScheduleId.value = null;
    }
};

const toggleSelectAll = (event) => {
    if (event.target.checked) {
        selectedStudentIds.value = filteredStudents.value.map((student) => Number(student.user_id));
        return;
    }

    selectedStudentIds.value = [];
};

const assignSelectedStudents = async () => {
    if (!selectedExamId.value || !selectedScheduleId.value || selectedStudentIds.value.length === 0) {
        return;
    }

    isAssigning.value = true;
    try {
        await axios.post('/api/college_dean/screening-schedules/assign-students', {
            exam_id: Number(selectedExamId.value),
            exam_schedule_id: Number(selectedScheduleId.value),
            user_ids: selectedStudentIds.value.map((id) => Number(id)),
        });

        selectedStudentIds.value = [];
        await refreshData();
        window.Toast?.fire({
            icon: 'success',
            title: 'Students assigned to screening schedule',
        });
    } catch (error) {
        window.Swal?.fire({
            icon: 'error',
            title: 'Assignment failed',
            text: error?.response?.data?.message || 'Could not assign the selected students.',
        });
    } finally {
        isAssigning.value = false;
    }
};

const unassignStudent = async (student) => {
    if (!selectedExamId.value || !selectedScheduleId.value || !student?.user_id) {
        return;
    }

    isUnassigningUserId.value = Number(student.user_id);
    try {
        await axios.delete(`/api/college_dean/screening-schedules/assignments/${Number(selectedExamId.value)}/${Number(selectedScheduleId.value)}/${Number(student.user_id)}`);
        await refreshData();
        window.Toast?.fire({
            icon: 'success',
            title: 'Student removed from schedule',
        });
    } catch (error) {
        window.Swal?.fire({
            icon: 'error',
            title: 'Unable to remove student',
            text: error?.response?.data?.message || 'Could not update the schedule.',
        });
    } finally {
        isUnassigningUserId.value = null;
    }
};

watch(selectedExamId, async () => {
    selectedStudentIds.value = [];
    await refreshData();
});

onMounted(async () => {
    scheduleModalInstance = scheduleModalRef.value ? new Modal(scheduleModalRef.value) : null;
    await fetchExams();
    await refreshData();
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

.bg-emerald {
    background-color: #10b981;
}

.text-emerald {
    color: #10b981;
}

.btn-light-success {
    color: #10b981;
    background: #ecfdf5;
    border: none;
}

.btn-light-success:hover {
    background: #d1fae5;
    color: #059669;
}

.schedule-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.schedule-card {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 1rem;
    background: #fff;
    padding: 1rem;
    transition: 0.2s ease;
}

.schedule-card:hover {
    border-color: #10b981;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
}

.schedule-card.active {
    border-color: #10b981;
    background: #ecfdf5;
    box-shadow: 0 14px 28px rgba(16, 185, 129, 0.12);
}

.btn-icon {
    width: 30px;
    height: 30px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.btn-light-danger {
    color: #ef4444;
    background: #fef2f2;
    border: none;
}

.btn-light-danger:hover {
    background: #fee2e2;
    color: #dc2626;
}
</style>
