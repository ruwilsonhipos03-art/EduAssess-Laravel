<template>
  <div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="fw-bold mb-1">Subjects</h3>
        <p class="text-muted mb-0 small">Subjects assigned to you</p>
      </div>
      <button class="btn btn-outline-success btn-sm" @click="loadSubjects" :disabled="loadingSubjects">
        <span v-if="loadingSubjects" class="spinner-border spinner-border-sm me-2"></span>
        Refresh
      </button>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-0">
        <div class="p-3 border-bottom">
          <input v-model="search" type="text" class="form-control form-control-sm" placeholder="Search subject...">
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Subject</th>
                <th>Assigned Students</th>
                <th class="text-end pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loadingSubjects">
                <td colspan="3" class="text-center py-4 text-muted">Loading subjects...</td>
              </tr>
              <tr v-else-if="!filteredSubjects.length">
                <td colspan="3" class="text-center py-4 text-muted">No subjects assigned.</td>
              </tr>
              <tr v-for="subject in filteredSubjects" :key="subject.id">
                <td class="ps-3 fw-semibold">{{ subject.subject_name }}</td>
                <td>{{ subject.students_count }}</td>
                <td class="text-end pe-3">
                  <button
                    class="btn btn-sm btn-outline-success"
                    :disabled="loadingPopup && activeSubjectId === subject.id"
                    @click="openStudentsPopup(subject)"
                  >
                    View Students
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="isPopupOpen" class="popup-overlay" @click.self="closePopup">
      <div class="popup-card">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 class="fw-bold mb-1">{{ popupSubjectName || 'Subject Students' }}</h5>
            <div class="text-muted small">Students assigned under this subject</div>
          </div>
          <button type="button" class="btn-close" @click="closePopup"></button>
        </div>

        <div v-if="loadingPopup" class="text-center text-muted py-4">
          Loading students...
        </div>
        <div v-else-if="popupError" class="alert alert-danger py-2 mb-0">{{ popupError }}</div>
        <div v-else class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Student #</th>
                <th>Name</th>
                <th>Program</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!popupStudents.length">
                <td colspan="3" class="text-center py-3 text-muted">No students assigned yet.</td>
              </tr>
              <tr v-for="student in popupStudents" :key="student.id">
                <td>{{ student.student_number || '-' }}</td>
                <td>{{ student.full_name || '-' }}</td>
                <td>{{ student.program_name || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const subjects = ref([]);
const loadingSubjects = ref(false);
const search = ref('');

const isPopupOpen = ref(false);
const loadingPopup = ref(false);
const popupError = ref('');
const popupSubjectName = ref('');
const popupStudents = ref([]);
const activeSubjectId = ref(null);

const filteredSubjects = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return subjects.value;

  return subjects.value.filter((row) => String(row.subject_name || '').toLowerCase().includes(q));
});

const loadSubjects = async () => {
  loadingSubjects.value = true;
  try {
    const { data } = await axios.get('/api/instructor/subjects');
    subjects.value = Array.isArray(data?.data) ? data.data : [];
  } catch (error) {
    subjects.value = [];
    window.Swal?.fire({
      icon: 'error',
      title: 'Failed to load subjects',
      text: error?.response?.data?.message || 'Please try again.',
      confirmButtonColor: '#10b981',
    });
  } finally {
    loadingSubjects.value = false;
  }
};

const openStudentsPopup = async (subject) => {
  activeSubjectId.value = subject.id;
  isPopupOpen.value = true;
  popupError.value = '';
  popupStudents.value = [];
  popupSubjectName.value = subject.subject_name || '';
  loadingPopup.value = true;

  try {
    const { data } = await axios.get(`/api/instructor/subjects/${subject.id}/students`);
    popupStudents.value = Array.isArray(data?.data) ? data.data : [];
    if (!popupSubjectName.value) {
      popupSubjectName.value = data?.subject_name || '';
    }
  } catch (error) {
    popupError.value = error?.response?.data?.message || 'Failed to load students for this subject.';
  } finally {
    loadingPopup.value = false;
  }
};

const closePopup = () => {
  isPopupOpen.value = false;
  loadingPopup.value = false;
  popupError.value = '';
  popupSubjectName.value = '';
  popupStudents.value = [];
  activeSubjectId.value = null;
};

onMounted(loadSubjects);
</script>

<style scoped>
.popup-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 2000;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 16px;
}

.popup-card {
  width: min(980px, 100%);
  max-height: 88vh;
  overflow: auto;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.2);
  padding: 20px;
}
</style>
