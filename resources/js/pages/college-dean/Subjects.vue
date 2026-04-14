<template>
  <div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="fw-bold mb-1">Student Subject Assignment</h3>
        <p class="text-muted mb-0 small">Assign subjects to students under your department</p>
      </div>
      <button class="btn btn-outline-success btn-sm" @click="loadAll" :disabled="loadingAny">
        <span v-if="loadingAny" class="spinner-border spinner-border-sm me-2"></span>
        Refresh
      </button>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <h6 class="fw-bold">Add Assignment</h6>
        <div class="row g-2">
          <div class="col-md-5">
            <label class="form-label small text-muted">Students</label>
            <input
              v-model="studentPickerSearch"
              type="text"
              class="form-control form-control-sm mb-2"
              placeholder="Search student name..."
              :disabled="saving"
            >
            <select class="form-select" v-model="form.student_user_ids" :disabled="saving" multiple size="7">
              <option v-for="student in filteredStudentOptions" :key="student.id" :value="student.id">
                {{ student.full_name }} - {{ student.program_name || '-' }}
              </option>
            </select>
            <div class="small text-muted mt-1">Selected: {{ selectedStudentCount }}</div>
          </div>
          <div class="col-md-5">
            <label class="form-label small text-muted">Subject</label>
            <select class="form-select" v-model="form.subject_id" :disabled="saving">
              <option value="" disabled>Select subject</option>
              <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                {{ subject.Subject_Name }}
              </option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-success w-100" @click="saveAssignment" :disabled="saving || !canSave">
              <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
              {{ assignButtonLabel }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-0">
        <div class="p-3 border-bottom">
          <input v-model="search" class="form-control form-control-sm" type="text"
            placeholder="Search by student or subject">
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Student</th>
                <th>Subject</th>
                <th class="text-end pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loadingAssignments">
                <td colspan="3" class="text-center py-4 text-muted">Loading assignments...</td>
              </tr>
              <tr v-else-if="!filteredAssignments.length">
                <td colspan="3" class="text-center py-4 text-muted">No assignments yet.</td>
              </tr>
              <tr v-for="item in filteredAssignments" :key="item.id">
                <td class="ps-3">{{ item.student_name || '-' }}</td>
                <td>{{ item.subject_name || '-' }}</td>
                <td class="text-end pe-3">
                  <button class="btn btn-sm btn-outline-danger" @click="removeAssignment(item.id)"
                    :disabled="deletingId === item.id">
                    <span v-if="deletingId === item.id" class="spinner-border spinner-border-sm me-2"></span>
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const students = ref([]);
const subjects = ref([]);
const assignments = ref([]);

const loadingStudents = ref(false);
const loadingSubjects = ref(false);
const loadingAssignments = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const search = ref('');
const studentPickerSearch = ref('');

const form = reactive({
  student_user_ids: [],
  subject_id: '',
});

const loadingAny = computed(() =>
  loadingStudents.value || loadingSubjects.value || loadingAssignments.value
);

const selectedStudentCount = computed(() => Array.isArray(form.student_user_ids) ? form.student_user_ids.length : 0);
const canSave = computed(() => Boolean(selectedStudentCount.value > 0 && form.subject_id));
const assignButtonLabel = computed(() => `Assign to ${selectedStudentCount.value || 0} Student${selectedStudentCount.value === 1 ? '' : 's'}`);

const filteredStudentOptions = computed(() => {
  const q = studentPickerSearch.value.trim().toLowerCase();
  if (!q) return students.value;

  return students.value.filter((student) =>
    String(student.full_name || '').toLowerCase().includes(q)
  );
});

const filteredAssignments = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return assignments.value;

  return assignments.value.filter((row) => {
    const combined = `${row.student_name || ''} ${row.subject_name || ''}`.toLowerCase();
    return combined.includes(q);
  });
});

const loadStudents = async () => {
  loadingStudents.value = true;
  try {
    const { data } = await axios.get('/api/college_dean/students');
    students.value = Array.isArray(data?.data) ? data.data : [];
  } finally {
    loadingStudents.value = false;
  }
};

const loadSubjects = async () => {
  loadingSubjects.value = true;
  try {
    const { data } = await axios.get('/api/college_dean/subjects');
    subjects.value = Array.isArray(data?.data) ? data.data : [];
  } finally {
    loadingSubjects.value = false;
  }
};

const loadAssignments = async () => {
  loadingAssignments.value = true;
  try {
    const { data } = await axios.get('/api/college_dean/subject-assignments/students');
    assignments.value = Array.isArray(data?.data) ? data.data : [];
  } catch (error) {
    assignments.value = [];
    window.Swal?.fire({
      icon: 'error',
      title: 'Failed to load assignments',
      text: error?.response?.data?.message || 'Please try again.',
      confirmButtonColor: '#10b981',
    });
  } finally {
    loadingAssignments.value = false;
  }
};

const loadAll = async () => {
  await Promise.all([loadStudents(), loadSubjects(), loadAssignments()]);
};

const saveAssignment = async () => {
  if (!canSave.value || saving.value) return;

  saving.value = true;
  try {
    const payload = {
      subject_id: Number(form.subject_id),
      student_user_ids: form.student_user_ids.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0),
    };

    const { data } = await axios.post('/api/college_dean/subject-assignments/students', payload);
    window.Toast?.fire({ icon: 'success', title: data?.message || 'Assignment saved' });
    form.student_user_ids = [];
    form.subject_id = '';
    await loadAssignments();
  } catch (error) {
    window.Swal?.fire({
      icon: 'error',
      title: 'Save failed',
      text: error?.response?.data?.message || 'Unable to save assignment.',
      confirmButtonColor: '#10b981',
    });
  } finally {
    saving.value = false;
  }
};

const removeAssignment = async (id) => {
  const result = await window.Swal?.fire({
    title: 'Remove assignment?',
    text: 'This will unassign the subject from the student.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Remove',
  });

  if (!result?.isConfirmed) return;

  deletingId.value = id;
  try {
    await axios.delete(`/api/college_dean/subject-assignments/students/${id}`);
    window.Toast?.fire({ icon: 'success', title: 'Assignment removed' });
    await loadAssignments();
  } catch (error) {
    window.Swal?.fire({
      icon: 'error',
      title: 'Delete failed',
      text: error?.response?.data?.message || 'Unable to remove assignment.',
      confirmButtonColor: '#10b981',
    });
  } finally {
    deletingId.value = null;
  }
};

onMounted(loadAll);
</script>
