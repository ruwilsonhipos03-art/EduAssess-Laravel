<template>
  <div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="fw-bold mb-1">Students</h3>
        <p class="text-muted mb-0 small">Students under your department programs</p>
      </div>
      <button class="btn btn-outline-success btn-sm" @click="loadStudents" :disabled="loading">
        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
        Refresh
      </button>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-0">
        <div class="p-3 border-bottom">
          <div class="row g-2">
            <div class="col-12 col-md-4">
              <input v-model="search" type="text" class="form-control form-control-sm"
                placeholder="Search by student name or number">
            </div>
            <div class="col-12 col-md-3">
              <select v-model="programFilter" class="form-select form-select-sm">
                <option value="">All Programs</option>
                <option v-for="name in programOptions" :key="name" :value="name">{{ name }}</option>
              </select>
            </div>
            <div v-if="hasSubjectOptions" class="col-12 col-md-3">
              <select v-model="subjectFilter" class="form-select form-select-sm">
                <option value="">All Subjects</option>
                <option v-for="name in subjectOptions" :key="name" :value="name">{{ name }}</option>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <select v-model="sortKey" class="form-select form-select-sm">
                <option value="full_name">Name</option>
                <option value="student_number">Student #</option>
              </select>
            </div>
            <div class="col-6 col-md-1">
              <select v-model="sortDirection" class="form-select form-select-sm">
                <option value="asc">Asc</option>
                <option value="desc">Desc</option>
              </select>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Student #</th>
                <th>QR</th>
                <th>Name</th>
                <th>Program</th>
                <th>Subjects</th>
                <th>Username</th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="7" class="text-center py-4 text-muted">Loading students...</td>
              </tr>
              <tr v-else-if="!filteredStudents.length">
                <td colspan="7" class="text-center py-4 text-muted">No students found.</td>
              </tr>
              <tr v-for="student in filteredStudents" :key="student.id">
                <td class="ps-3 fw-semibold">{{ student.student_number || '-' }}</td>
                <td>
                  <img
                    v-if="student.student_qr_svg"
                    :src="`data:image/svg+xml;base64,${student.student_qr_svg}`"
                    alt="Student QR"
                    width="60"
                    height="60"
                  >
                  <span v-else class="text-muted small">-</span>
                </td>
                <td>{{ student.full_name || '-' }}</td>
                <td>{{ student.program_name || '-' }}</td>
                <td>{{ student.subject_names || '-' }}</td>
                <td>{{ student.username || '-' }}</td>
                <td>{{ student.email || '-' }}</td>
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

const students = ref([]);
const loading = ref(false);
const search = ref('');
const programFilter = ref('');
const subjectFilter = ref('');
const sortKey = ref('full_name');
const sortDirection = ref('asc');

const programOptions = computed(() => {
  const values = students.value
    .map((row) => String(row.program_name || '').trim())
    .filter(Boolean);
  return [...new Set(values)].sort((a, b) => a.localeCompare(b));
});

const subjectOptions = computed(() => {
  const values = students.value
    .flatMap((row) => String(row.subject_names || '')
      .split(',')
      .map((name) => name.trim())
      .filter(Boolean));
  return [...new Set(values)].sort((a, b) => a.localeCompare(b));
});

const hasSubjectOptions = computed(() => subjectOptions.value.length > 1);

const filteredStudents = computed(() => {
  const q = search.value.trim().toLowerCase();
  const program = programFilter.value;
  const subject = subjectFilter.value;
  let rows = [...students.value];

  if (program) {
    rows = rows.filter((row) => String(row.program_name || '') === program);
  }

  if (subject) {
    rows = rows.filter((row) =>
      String(row.subject_names || '')
        .split(',')
        .map((name) => name.trim())
        .includes(subject)
    );
  }

  if (q) {
    rows = rows.filter((s) => {
      const haystack = [
        s.student_number,
        s.full_name,
        s.program_name,
        s.username,
        s.email,
        s.subject_names,
      ]
        .map((item) => String(item || '').toLowerCase())
        .join(' ');

      return haystack.includes(q);
    });
  }

  const direction = sortDirection.value === 'desc' ? -1 : 1;
  const key = sortKey.value;
  rows.sort((a, b) => {
    const first = String(a?.[key] || '');
    const second = String(b?.[key] || '');
    return first.localeCompare(second, undefined, { sensitivity: 'base' }) * direction;
  });

  return rows;
});

const loadStudents = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/college_dean/students');
    students.value = Array.isArray(data?.data) ? data.data : [];
  } catch (error) {
    students.value = [];
    window.Swal?.fire({
      icon: 'error',
      title: 'Failed to load students',
      text: error?.response?.data?.message || 'Please try again.',
      confirmButtonColor: '#10b981',
    });
  } finally {
    loading.value = false;
  }
};

onMounted(loadStudents);
</script>
