<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4 class="fw-bold mb-1 text-dark">Users</h4>
                        <p class="text-muted small mb-0">All people who use the system, including staff and students.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button @click="printUsers" class="btn btn-outline-secondary fw-bold px-4 shadow-sm"
                            :disabled="loading || filteredRows.length === 0">
                            <i class="bi bi-printer me-2"></i>Print
                        </button>
                        <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-person-plus me-2"></i>Add User
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3" v-for="item in summaryCards" :key="item.label">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-uppercase text-muted fw-semibold mb-2">{{ item.label }}</div>
                        <div class="h3 fw-bold mb-0 text-dark">{{ item.value }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body bg-light border-bottom p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input v-model.trim="filters.search" type="text" class="form-control form-control-sm"
                            placeholder="Name, username, ID no., role, program...">
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Category</label>
                        <select v-model="filters.category" class="form-select form-select-sm">
                            <option v-for="option in categoryOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Program</label>
                        <select v-model="filters.programName" class="form-select form-select-sm">
                            <option value="">All Programs</option>
                            <option v-for="program in programOptions" :key="program" :value="program">{{ program }}
                            </option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-semibold mb-1">College / Office</label>
                        <select v-model="filters.orgUnit" class="form-select form-select-sm">
                            <option value="">All Units</option>
                            <option v-for="unit in orgUnitOptions" :key="unit" :value="unit">{{ unit }}</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Sort</label>
                        <select v-model="filters.sortBy" class="form-select form-select-sm">
                            <option value="name_asc">Name A-Z</option>
                            <option value="name_desc">Name Z-A</option>
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                        </select>
                    </div>

                    <div class="col-lg-1 col-md-6">
                        <button class="btn btn-sm btn-outline-secondary w-100" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">No.</th>
                                <th>User</th>
                                <th>Category</th>
                                <th>Roles</th>
                                <th>ID No.</th>
                                <th>Program / Unit</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="8" class="text-center py-5 text-muted">Loading users...</td>
                            </tr>
                            <tr v-else-if="filteredRows.length === 0">
                                <td colspan="8" class="text-center py-5 text-muted">No users found for the selected
                                    filters.</td>
                            </tr>
                            <tr v-else v-for="(row, index) in filteredRows" :key="row.id">
                                <td class="ps-3">{{ index + 1 }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ row.full_name || '-' }}</div>
                                    <div class="small text-muted">{{ row.username || '-' }}</div>
                                    <div class="small text-muted" v-if="row.email">{{ row.email }}</div>
                                </td>
                                <td>
                                    <span :class="['badge rounded-pill px-3 py-2', categoryBadgeClass(row)]">
                                        {{ categoryLabel(row) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span v-for="role in row.roles" :key="`${row.id}-${role}`"
                                            class="badge rounded-pill bg-light text-dark border px-2 py-1">
                                            {{ prettyRole(role) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="small text-dark">
                                    {{ displayIdNumber(row) }}
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">{{ row.program_name || 'N/A' }}</div>
                                    <div class="small text-muted">{{ row.college_name || 'N/A' }}</div>
                                    <div class="small text-muted">{{ row.office_name || 'N/A' }}</div>
                                </td>
                                <td>
                                    <span v-if="studentStatus(row)"
                                        :class="['badge border', studentStatus(row).className]">
                                        {{ studentStatus(row).label }}
                                    </span>
                                    <span v-else
                                        class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                        Active User
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <template v-if="canManageUser(row)">
                                        <button @click="openModal(row)" class="btn btn-icon btn-light-success me-2"
                                            title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteUser(row)" class="btn btn-icon btn-light-danger"
                                            :disabled="deletingId === deleteKey(row)" title="Delete">
                                            <span v-if="deletingId === deleteKey(row)"
                                                class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </template>
                                    <span v-else class="text-muted small">View only</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ modalTitle }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveUser">
                        <div class="modal-body p-4">
                            <div v-if="!editMode" class="mb-4">
                                <label class="form-label small fw-bold text-uppercase">Add User Type</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="btn fw-bold"
                                        :class="accountType === 'employee' ? 'btn-emerald' : 'btn-outline-secondary'"
                                        @click="setAccountType('employee')"
                                    >
                                        Add Employee
                                    </button>
                                    <button
                                        type="button"
                                        class="btn fw-bold"
                                        :class="accountType === 'student' ? 'btn-emerald' : 'btn-outline-secondary'"
                                        @click="setAccountType('student')"
                                    >
                                        Add Student
                                    </button>
                                </div>
                            </div>

                            <template v-if="accountType === 'employee'">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase label-required">First Name</label>
                                    <input v-model="form.first_name" type="text" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Middle Initial</label>
                                    <input v-model="form.middle_initial" type="text" class="form-control" maxlength="2">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase label-required">Last Name</label>
                                    <input v-model="form.last_name" type="text" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Extension Name</label>
                                    <input v-model="form.extension_name" type="text" class="form-control"
                                        maxlength="10">
                                </div>

                                <div class="col-md-12 py-2">
                                    <label class="form-label small fw-bold text-uppercase label-required">System Role
                                        Assignment</label>
                                    <div class="p-3 border rounded bg-white shadow-sm">
                                        <div class="d-flex flex-wrap align-items-center gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input custom-check" type="radio"
                                                    value="college_dean" id="roleDept" v-model="roleSelectionType"
                                                    @change="handleRoleTypeChange">
                                                <label class="form-check-label fw-bold text-dark" for="roleDept">College
                                                    Dean</label>
                                            </div>
                                            <div class="vr mx-1 d-none d-md-block" style="height: 20px;"></div>
                                            <div class="form-check">
                                                <input class="form-check-input custom-check" type="radio" value="staff"
                                                    id="roleStaff" v-model="roleSelectionType"
                                                    @change="handleRoleTypeChange">
                                                <label class="form-check-label fw-bold text-dark"
                                                    for="roleStaff">Academic /
                                                    Staff</label>
                                            </div>
                                            <div class="d-flex gap-3 ms-3 ms-md-0" v-if="roleSelectionType === 'staff'">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="instructor"
                                                        id="chkInst" v-model="form.roles" @change="syncAssignmentFields">
                                                    <label class="form-check-label small"
                                                        for="chkInst">Instructor</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                        value="entrance_examiner" id="chkExam" v-model="form.roles" @change="syncAssignmentFields">
                                                    <label class="form-check-label small" for="chkExam">Entrance
                                                        Examiner</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="form.roles.length === 0" class="text-danger small mt-1">Please select at
                                        least
                                        one role.</div>
                                </div>

                                <div v-if="showCollegeField" class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">College</label>
                                    <select v-model="form.college_id" class="form-select">
                                        <option value="">None / Unassigned</option>
                                        <option v-for="dept in colleges" :key="dept.id" :value="dept.id">{{
                                            dept.College_Name }}
                                        </option>
                                    </select>
                                </div>

                                <div v-if="showOfficeField" class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Office</label>
                                    <select v-model="form.office_id" class="form-select">
                                        <option value="">None / Unassigned</option>
                                        <option v-for="off in offices" :key="off.id" :value="off.id">{{ off.Office_Name
                                            }}
                                        </option>
                                    </select>
                                </div>

                                <div v-if="showProgramField" class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Program</label>
                                    <select v-model="form.program_id" class="form-select">
                                        <option value="">Select Program</option>
                                        <option v-for="program in employeeProgramOptions" :key="program.id" :value="program.id">
                                            {{ program.Program_Name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-12" v-if="editMode">
                                    <label class="form-label small fw-bold text-uppercase">Employee Number</label>
                                    <input v-model="form.employee_number" type="text" class="form-control" readonly
                                        disabled>
                                </div>

                                <div class="col-md-12">
                                    <label
                                        class="form-label small fw-bold text-uppercase label-required">Username</label>
                                    <input v-model="form.username" type="text" class="form-control" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase"
                                        :class="{ 'label-required': !editMode }">Password</label>
                                    <input v-model="form.password" type="password" class="form-control"
                                        :required="!editMode">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase"
                                        :class="{ 'label-required': !editMode || form.password.length > 0 }">
                                        Confirm Password
                                    </label>
                                    <input v-model="form.password_confirmation" type="password" class="form-control"
                                        :class="{ 'is-invalid': showPassError }" @focus="isConfirmFocused = true">
                                    <div class="invalid-feedback">Passwords do not match.</div>
                                </div>
                            </div>
                            </template>

                            <template v-else>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-uppercase label-required">First Name</label>
                                        <input v-model="studentForm.first_name" type="text" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-uppercase">Middle Initial</label>
                                        <input v-model="studentForm.middle_initial" type="text" class="form-control" maxlength="2">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-uppercase label-required">Last Name</label>
                                        <input v-model="studentForm.last_name" type="text" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-uppercase">Extension Name</label>
                                        <input v-model="studentForm.extension_name" type="text" class="form-control" maxlength="10">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label small fw-bold text-uppercase label-required">Email Address</label>
                                        <input v-model="studentForm.email" type="email" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-uppercase label-required">Program Choice 1</label>
                                        <select v-model="studentForm.program_id" class="form-select" required>
                                            <option value="">Select Program</option>
                                            <option v-for="program in programs" :key="`choice1-${program.id}`" :value="String(program.id)">
                                                {{ program.Program_Name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-uppercase">Program Choice 2</label>
                                        <select v-model="studentForm.program_choice_2" class="form-select">
                                            <option value="">Select Program</option>
                                            <option v-for="program in programs" :key="`choice2-${program.id}`" :value="String(program.id)">
                                                {{ program.Program_Name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-uppercase">Program Choice 3</label>
                                        <select v-model="studentForm.program_choice_3" class="form-select">
                                            <option value="">Select Program</option>
                                            <option v-for="program in programs" :key="`choice3-${program.id}`" :value="String(program.id)">
                                                {{ program.Program_Name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal"
                                :disabled="isSaving">Cancel</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold"
                                :disabled="saveDisabled">
                                <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"></span>
                                {{ isSaving ? 'Saving...' : 'Save Data' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import axios from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const loading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);
const users = ref([]);
const colleges = ref([]);
const offices = ref([]);
const programs = ref([]);
const editMode = ref(false);
const currentEmployeeId = ref(null);
const currentStudentId = ref(null);
const modalRef = ref(null);
const isConfirmFocused = ref(false);
const roleSelectionType = ref('staff');
const accountType = ref('employee');
let modalInstance = null;

const filters = reactive({
    search: '',
    category: 'users',
    programName: '',
    orgUnit: '',
    sortBy: 'name_asc',
});

const form = reactive({
    first_name: '',
    middle_initial: '',
    last_name: '',
    extension_name: '',
    employee_number: '',
    college_id: '',
    office_id: '',
    program_id: '',
    username: '',
    password: '',
    password_confirmation: '',
    roles: [],
});

const studentForm = reactive({
    first_name: '',
    middle_initial: '',
    last_name: '',
    extension_name: '',
    email: '',
    program_id: '',
    program_choice_2: '',
    program_choice_3: '',
});

const passwordsDoNotMatch = computed(() => form.password !== form.password_confirmation);
const showPassError = computed(() => isConfirmFocused.value && passwordsDoNotMatch.value && form.password_confirmation.length > 0);
const modalTitle = computed(() => {
    if (editMode.value) {
        return accountType.value === 'student' ? 'Edit Student' : 'Edit Employee';
    }

    return accountType.value === 'student' ? 'New Student Account' : 'New Employee Account';
});
const saveDisabled = computed(() => {
    if (accountType.value === 'student') {
        return isSaving.value
            || !studentForm.first_name
            || !studentForm.last_name
            || !studentForm.email
            || !studentForm.program_id;
    }

    return isSaving.value || form.roles.length === 0 || form.password !== form.password_confirmation;
});

const summaryCards = computed(() => ([
    { label: 'All Users', value: users.value.length.toLocaleString() },
    { label: 'Staff', value: users.value.filter((row) => row.user_kind === 'employee').length.toLocaleString() },
    { label: 'Students', value: users.value.filter((row) => row.roles.includes('student')).length.toLocaleString() },
    { label: 'Admins', value: users.value.filter((row) => row.roles.includes('admin')).length.toLocaleString() },
]));

const categoryOptions = computed(() => ([
    { value: 'users', label: 'All Users' },
    { value: 'staff', label: 'Staff' },
    { value: 'students', label: 'Students' },
    { value: 'instructors', label: 'Instructors' },
    { value: 'college_deans', label: 'College Deans' },
    { value: 'entrance_examiners', label: 'Entrance Examiners' },
    { value: 'admins', label: 'Admins' },
]));

const programOptions = computed(() => {
    return [...new Set(users.value.map((row) => row.program_name).filter((item) => item && item !== 'N/A'))]
        .sort((a, b) => String(a).localeCompare(String(b)));
});

const orgUnitOptions = computed(() => {
    return [...new Set(
        users.value
            .flatMap((row) => [row.college_name, row.office_name])
            .filter((item) => item && item !== 'N/A')
    )].sort((a, b) => String(a).localeCompare(String(b)));
});

const employeeProgramOptions = computed(() => {
    if (!form.college_id) {
        return [];
    }

    return programs.value
        .filter((program) => String(program.college_id ?? '') === String(form.college_id))
        .sort((a, b) => String(a.Program_Name || '').localeCompare(String(b.Program_Name || '')));
});

const selectedCategoryLabel = computed(() => {
    return categoryOptions.value.find((option) => option.value === filters.category)?.label || 'All Users';
});

const filteredRows = computed(() => {
    let result = [...users.value];

    if (filters.search) {
        const query = filters.search.toLowerCase();
        result = result.filter((row) => {
            const text = [
                row.full_name,
                row.username,
                row.email,
                row.student_number,
                row.employee_number,
                row.program_name,
                row.college_name,
                row.office_name,
                row.role,
            ].map((item) => String(item || '').toLowerCase()).join(' ');

            return text.includes(query);
        });
    }

    if (filters.category) {
        result = result.filter((row) => matchesCategory(row, filters.category));
    }

    if (filters.programName) {
        result = result.filter((row) => row.program_name === filters.programName);
    }

    if (filters.orgUnit) {
        result = result.filter((row) => row.college_name === filters.orgUnit || row.office_name === filters.orgUnit);
    }

    result.sort((a, b) => {
        if (filters.sortBy === 'name_desc') {
            return String(b.full_name || '').localeCompare(String(a.full_name || ''));
        }

        if (filters.sortBy === 'newest') {
            return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
        }

        if (filters.sortBy === 'oldest') {
            return new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime();
        }

        return String(a.full_name || '').localeCompare(String(b.full_name || ''));
    });

    return result;
});

const matchesCategory = (row, category) => {
    if (category === 'users') return true;
    if (category === 'students') return categoryLabel(row) === 'Student';
    if (category === 'instructors') return row.roles.includes('instructor');
    if (category === 'college_deans') return row.roles.includes('college_dean');
    if (category === 'entrance_examiners') return row.roles.includes('entrance_examiner');
    if (category === 'admins') return row.roles.includes('admin');
    if (category === 'staff') return categoryLabel(row) === 'Staff';
    return true;
};

const prettyRole = (role) => String(role || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const categoryLabel = (row) => {
    if (row.roles.includes('admin')) return 'Admin';
    if (row.user_kind === 'employee') return 'Staff';
    if (row.roles.includes('student')) return 'Student';
    return 'User';
};

const categoryBadgeClass = (row) => {
    if (row.roles.includes('admin')) return 'bg-danger-subtle text-danger border border-danger-subtle';
    if (row.user_kind === 'employee') return 'bg-info-subtle text-info border border-info-subtle';
    if (row.roles.includes('student')) return 'bg-success-subtle text-success border border-success-subtle';
    return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
};

const studentStatus = (row) => {
    if (!row?.roles?.includes('student')) {
        return null;
    }

    if (!row.has_verified_email) {
        return {
            label: 'Scheduled for Entrance Exam',
            className: 'bg-info-subtle text-info border-info-subtle',
        };
    }

    if (!row.has_entrance_exam_taken) {
        return {
            label: 'Entrance Exam Not Taken',
            className: 'bg-warning-subtle text-warning border-warning-subtle',
        };
    }

    if (!row.has_screening_attempt) {
        return {
            label: 'No Screening Attempt Yet',
            className: 'bg-warning-subtle text-warning border-warning-subtle',
        };
    }

    if (!row.has_screening_pass) {
        return {
            label: 'Screening Not Passed',
            className: 'bg-warning-subtle text-warning border-warning-subtle',
        };
    }

    return {
        label: 'Screening Passed',
        className: 'bg-success-subtle text-success border-success-subtle',
    };
};

const displayIdNumber = (row) => row.employee_number || row.student_number || `USER-${row.id}`;

const canManageUser = (row) => Boolean(row.employee_id || row.student_profile_id);
const deleteKey = (row) => row.employee_id ? `employee-${row.employee_id}` : row.student_profile_id ? `student-${row.student_profile_id}` : `user-${row.id}`;

const escapeHtml = (value) => {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
};

const printTitle = computed(() => {
    const titles = {
        users: 'Users List',
        staff: 'Staffs List',
        students: 'Students List',
        instructors: 'Staffs List',
        college_deans: 'Staffs List',
        entrance_examiners: 'Staffs List',
        admins: 'Staffs List',
    };

    return titles[filters.category] || `${selectedCategoryLabel.value} List`;
});

const isStudentPrintView = computed(() => filters.category === 'students');

const isStaffPrintView = computed(() => (
    filters.category === 'staff'
    || filters.category === 'instructors'
    || filters.category === 'college_deans'
    || filters.category === 'entrance_examiners'
    || filters.category === 'admins'
));

const showCollegeField = computed(() => (
    roleSelectionType.value === 'college_dean' || form.roles.includes('instructor')
));

const showOfficeField = computed(() => form.roles.includes('entrance_examiner'));

const showProgramField = computed(() => form.roles.includes('instructor') && Boolean(form.college_id));

const printUsers = () => {
    if (loading.value || filteredRows.value.length === 0) {
        return;
    }

    const tableHeaders = isStudentPrintView.value
        ? ['No.', 'Student Number', 'Full Name', 'Program']
        : isStaffPrintView.value
            ? ['No.', 'Employee ID', 'Full Name', 'Role', 'Program']
            : ['No.', 'ID', 'Full Name', 'Program'];

    const tableRows = filteredRows.value.map((row, index) => {
        const program = row.program_name && row.program_name !== 'N/A' ? row.program_name : 'N/A';
        const roles = Array.isArray(row.roles) && row.roles.length
            ? row.roles.map((role) => prettyRole(role)).join(', ')
            : '-';

        if (isStudentPrintView.value) {
            return `<tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(row.student_number || `USER-${row.id}`)}</td>
                <td>${escapeHtml(row.full_name || '-')}</td>
                <td>${escapeHtml(program)}</td>
            </tr>`;
        }

        if (isStaffPrintView.value) {
            return `<tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(row.employee_number || `USER-${row.id}`)}</td>
                <td>${escapeHtml(row.full_name || '-')}</td>
                <td>${escapeHtml(roles)}</td>
                <td>${escapeHtml(program)}</td>
            </tr>`;
        }

        return `<tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(displayIdNumber(row))}</td>
            <td>${escapeHtml(row.full_name || '-')}</td>
            <td>${escapeHtml(program)}</td>
        </tr>`;
    }).join('');

    const html = `
        <html>
            <head>
                <meta charset="UTF-8" />
                <title>${escapeHtml(printTitle.value)}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 28px 36px; color: #1f2937; }
                    h2 { text-align: center; margin: 0 0 10px; font-size: 30px; font-weight: 700; }
                    .sub { text-align: center; font-size: 14px; margin: 2px 0; color: #4b5563; }
                    table { border-collapse: collapse; width: 100%; font-size: 13px; }
                    th, td { border: 1px solid #cbd5e1; padding: 8px 10px; vertical-align: top; text-align: left; }
                    th { background: #e2e8f0; font-weight: 700; }
                </style>
            </head>
            <body>
                <h2>${escapeHtml(printTitle.value)}</h2>
                <table>
                    <thead>
                        <tr>
                            ${tableHeaders.map((header) => `<th>${escapeHtml(header)}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>${tableRows}</tbody>
                </table>
            </body>
        </html>
    `;

    const printWindow = window.open('', '_blank', 'width=1200,height=900');
    if (!printWindow) {
        window.Swal?.fire({
            icon: 'warning',
            title: 'Print window blocked',
            text: 'Please allow pop-ups for this page and try again.',
        });
        return;
    }

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
};

const resetForm = () => {
    Object.assign(form, {
        first_name: '',
        middle_initial: '',
        last_name: '',
        extension_name: '',
        employee_number: '',
        college_id: '',
        office_id: '',
        program_id: '',
        username: '',
        password: '',
        password_confirmation: '',
        roles: [],
    });
};

const resetStudentForm = () => {
    Object.assign(studentForm, {
        first_name: '',
        middle_initial: '',
        last_name: '',
        extension_name: '',
        email: '',
        program_id: '',
        program_choice_2: '',
        program_choice_3: '',
    });
};

const setAccountType = (type) => {
    if (editMode.value) {
        return;
    }

    accountType.value = type;
    isConfirmFocused.value = false;

    if (type === 'employee') {
        resetStudentForm();
        resetForm();
        roleSelectionType.value = 'staff';
        syncAssignmentFields();
        return;
    }

    resetForm();
    resetStudentForm();
};

const handleRoleTypeChange = () => {
    form.roles = roleSelectionType.value === 'college_dean' ? ['college_dean'] : [];
    syncAssignmentFields();
};

const syncAssignmentFields = () => {
    if (!showCollegeField.value) {
        form.college_id = '';
        form.program_id = '';
    }

    if (!showProgramField.value) {
        form.program_id = '';
    }

    if (!showOfficeField.value) {
        form.office_id = '';
    }
};

const resetFilters = () => {
    Object.assign(filters, {
        search: '',
        category: 'users',
        programName: '',
        orgUnit: '',
        sortBy: 'name_asc',
    });
};

const loadUsers = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/users');
        users.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        users.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load users',
            text: error?.response?.data?.message || 'Please refresh and try again.',
        });
    } finally {
        loading.value = false;
    }
};

const fetchOptions = async () => {
    try {
        const [deptRes, offRes, progRes] = await Promise.all([
            axios.get('/api/admin/colleges'),
            axios.get('/api/admin/offices'),
            axios.get('/api/admin/programs'),
        ]);
        colleges.value = deptRes.data.data || deptRes.data || [];
        offices.value = offRes.data.data || offRes.data || [];
        programs.value = progRes.data.data || progRes.data || [];
    } catch (error) {
        colleges.value = [];
        offices.value = [];
        programs.value = [];
    }
};

const openModal = (row = null) => {
    editMode.value = Boolean(row?.employee_id || row?.student_profile_id);
    currentEmployeeId.value = row?.employee_id || null;
    currentStudentId.value = row?.student_profile_id || null;
    isConfirmFocused.value = false;

    if (row?.employee_id) {
        accountType.value = 'employee';
        roleSelectionType.value = row.roles.includes('college_dean') ? 'college_dean' : 'staff';
        Object.assign(form, {
            first_name: row.first_name || '',
            middle_initial: row.middle_initial || '',
            last_name: row.last_name || '',
            extension_name: row.extension_name || '',
            employee_number: row.employee_number || '',
            college_id: row.college_id || '',
            office_id: row.office_id || '',
            program_id: row.program_id || '',
            username: row.username || '',
            password: '',
            password_confirmation: '',
            roles: [...row.roles].filter((role) => ['college_dean', 'instructor', 'entrance_examiner'].includes(role)),
        });
    } else if (row?.student_profile_id) {
        accountType.value = 'student';
        resetForm();
        resetStudentForm();
        Object.assign(studentForm, {
            first_name: row.first_name || '',
            middle_initial: row.middle_initial || '',
            last_name: row.last_name || '',
            extension_name: row.extension_name || '',
            email: row.email || '',
            program_id: row.program_id ? String(row.program_id) : '',
            program_choice_2: row.program_choice_2 ? String(row.program_choice_2) : '',
            program_choice_3: row.program_choice_3 ? String(row.program_choice_3) : '',
        });
    } else {
        accountType.value = 'employee';
        resetForm();
        resetStudentForm();
        roleSelectionType.value = 'staff';
    }

    syncAssignmentFields();
    modalInstance?.show();
};

const saveEmployee = async () => {
    isSaving.value = true;
    try {
        const method = editMode.value ? 'put' : 'post';
        const url = editMode.value ? `/api/admin/employees/${currentEmployeeId.value}` : '/api/admin/employees';

        await axios[method](url, {
            first_name: form.first_name,
            middle_initial: form.middle_initial,
            last_name: form.last_name,
            extension_name: form.extension_name,
            college_id: form.college_id || null,
            office_id: form.office_id || null,
            program_id: form.program_id || null,
            username: form.username,
            password: form.password,
            password_confirmation: form.password_confirmation,
            roles: form.roles,
        });

        modalInstance?.hide();
        await loadUsers();
        window.Toast?.fire({ icon: 'success', title: 'Action successful' });
    } catch (error) {
        window.Swal?.fire({
            icon: 'error',
            title: 'Action failed',
            text: error?.response?.data?.message || 'Check your inputs.',
        });
    } finally {
        isSaving.value = false;
    }
};

const saveStudent = async () => {
    isSaving.value = true;
    try {
        const method = editMode.value ? 'put' : 'post';
        const url = editMode.value ? `/api/admin/student-accounts/${currentStudentId.value}` : '/api/admin/student-accounts';
        const { data } = await axios[method](url, {
            first_name: studentForm.first_name,
            middle_initial: studentForm.middle_initial,
            last_name: studentForm.last_name,
            extension_name: studentForm.extension_name,
            email: studentForm.email,
            program_id: Number(studentForm.program_id),
            program_choice_2: studentForm.program_choice_2 ? Number(studentForm.program_choice_2) : null,
            program_choice_3: studentForm.program_choice_3 ? Number(studentForm.program_choice_3) : null,
        });

        modalInstance?.hide();
        await loadUsers();
        if (editMode.value) {
            window.Toast?.fire({ icon: 'success', title: 'Action successful' });
        } else {
            const schedule = data?.data?.schedule;
            await window.Swal?.fire({
                icon: 'success',
                title: 'Student account created',
                html: `
                    <div class="text-start">
                        <div><strong>Student Number:</strong> ${escapeHtml(data?.data?.student_number || '-')}</div>
                        <hr>
                        <div><strong>Exam:</strong> ${escapeHtml(schedule?.exam_title || '-')}</div>
                        <div><strong>Date:</strong> ${escapeHtml(schedule?.date || '-')}</div>
                        <div><strong>Time:</strong> ${escapeHtml(schedule?.time || '-')}</div>
                        <div><strong>Location:</strong> ${escapeHtml(schedule?.location || '-')}</div>
                    </div>
                `,
            });
        }
    } catch (error) {
        window.Swal?.fire({
            icon: 'error',
            title: 'Action failed',
            text: error?.response?.data?.message || 'Check your inputs.',
        });
    } finally {
        isSaving.value = false;
    }
};

const saveUser = async () => {
    if (accountType.value === 'student') {
        await saveStudent();
        return;
    }

    await saveEmployee();
};

const deleteUser = async (row) => {
    if (!canManageUser(row)) return;

    const result = await window.Swal.fire({
        title: 'Are you sure?',
        text: `Deleting ${row.full_name} will remove system access.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete',
    });

    if (!result.isConfirmed) return;

    deletingId.value = deleteKey(row);
    try {
        if (row.employee_id) {
            await axios.delete(`/api/admin/employees/${row.employee_id}`);
        } else {
            await axios.delete(`/api/admin/student-accounts/${row.student_profile_id}`);
        }
        await loadUsers();
        window.Toast?.fire({ icon: 'success', title: 'Deleted' });
    } catch (error) {
        window.Swal?.fire('Error', 'Delete failed.', 'error');
    } finally {
        deletingId.value = null;
    }
};

watch(() => form.college_id, () => {
    if (!employeeProgramOptions.value.some((program) => String(program.id) === String(form.program_id))) {
        form.program_id = '';
    }
});

onMounted(async () => {
    modalInstance = modalRef.value ? new Modal(modalRef.value) : null;
    await Promise.all([loadUsers(), fetchOptions()]);
});
</script>

<style scoped>
.label-required::after {
    content: " *";
    color: #ef4444;
}

.btn-emerald {
    background-color: #10b981;
    color: white;
    border: none;
}

.btn-emerald:hover {
    background-color: #059669;
    color: white;
}

.btn-icon {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: none;
    transition: 0.2s;
}

.btn-light-success {
    color: #10b981;
    background-color: #ecfdf5;
}

.btn-light-danger {
    color: #ef4444;
    background-color: #fef2f2;
}

.bg-emerald {
    background-color: #10b981;
}

.custom-check {
    transform: scale(1.1);
}
</style>
