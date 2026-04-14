<template>
    <div>
        <h2 class="fw-bold mb-1">Entrance Examiner Applicants Management</h2>
        <p class="text-muted">Registered applicants who have not taken the screening exam yet.</p>

        <div class="row g-4 mt-2 mb-4">
            <div class="col-md-3" v-for="(card, idx) in counterCards" :key="idx">
                <div class="card border-0 shadow-sm p-4 rounded-4 h-100">
                    <h6 class="text-muted mb-2">{{ card.label }}</h6>
                    <h3 class="fw-bold mb-0">{{ card.value }}</h3>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body border-bottom">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Search Name</label>
                        <input v-model.trim="filters.search" type="text" class="form-control" placeholder="Lastname, Firstname..." />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Exam</label>
                        <select v-model="filters.exam" class="form-select">
                            <option value="">All Exams</option>
                            <option v-for="exam in examOptions" :key="exam" :value="exam">{{ exam }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select v-model="filters.result" class="form-select">
                            <option value="">All</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="missed">Missed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Sort</label>
                        <select v-model="filters.sortBy" class="form-select">
                            <option value="student_full_name">Name</option>
                            <option value="exam_name">Exam</option>
                            <option value="exam_status">Status</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">No.</th>
                            <th>Applicant Fullname</th>
                            <th>Exam</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="4" class="text-center py-4 text-muted">Loading applicants...</td>
                        </tr>
                        <tr v-else-if="filteredStudents.length === 0">
                            <td colspan="4" class="text-center py-4 text-muted">No applicants found.</td>
                        </tr>
                        <tr v-else v-for="(student, index) in filteredStudents" :key="student.id">
                            <td>{{ index + 1 }}</td>
                            <td class="fw-semibold">{{ student.student_full_name }}</td>
                            <td>{{ student.exam_name }}</td>
                            <td class="text-capitalize">{{ student.exam_status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const loading = ref(false);
const students = ref([]);
const counters = ref({
    scheduled_students: 0,
    pending_results: 0,
    passed_students: 0,
    total_students: 0,
});

const filters = ref({
    search: '',
    exam: '',
    result: '',
    sortBy: 'student_full_name',
});

const counterCards = computed(() => {
    return [
        { label: 'Scheduled Applicants', value: Number(counters.value.scheduled_students || 0).toLocaleString() },
        { label: 'Pending Results', value: Number(counters.value.pending_results || 0).toLocaleString() },
        { label: 'Passed Applicants', value: Number(counters.value.passed_students || 0).toLocaleString() },
        { label: 'Total Applicants', value: Number(counters.value.total_students || 0).toLocaleString() },
    ];
});

const examOptions = computed(() => {
    return [...new Set(students.value.map((s) => s.exam_name))].sort((a, b) => a.localeCompare(b));
});

const filteredStudents = computed(() => {
    let result = [...students.value];

    if (filters.value.search) {
        const searchText = filters.value.search.toLowerCase();
        result = result.filter((row) => row.student_full_name.toLowerCase().includes(searchText));
    }

    if (filters.value.exam) {
        result = result.filter((row) => row.exam_name === filters.value.exam);
    }

    if (filters.value.result) {
        result = result.filter((row) => row.exam_status === filters.value.result);
    }

    const key = filters.value.sortBy;
    result.sort((a, b) => {
        const first = a[key];
        const second = b[key];

        if (typeof first === 'number' && typeof second === 'number') {
            return second - first;
        }

        return String(first).localeCompare(String(second));
    });

    return result;
});

const loadCounters = async () => {
    const { data } = await axios.get('/api/entrance/dashboard/stats');
    counters.value = {
        scheduled_students: Number(data?.scheduled_students || 0),
        pending_results: Number(data?.pending_results || 0),
        passed_students: Number(data?.passed_students || 0),
        total_students: Number(data?.total_students || 0),
    };
};

const loadStudentsWhoTookExams = async () => {
    const { data } = await axios.get('/api/entrance/students/took-exams');
    students.value = Array.isArray(data?.data) ? data.data : [];
};

const loadPageData = async () => {
    loading.value = true;
    try {
        await Promise.all([loadCounters(), loadStudentsWhoTookExams()]);
    } catch (error) {
        students.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load applicants data',
            text: 'Please refresh and try again.',
        });
    } finally {
        loading.value = false;
    }
};

onMounted(loadPageData);
</script>
