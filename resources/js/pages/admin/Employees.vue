<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">Employee Management</h4>
                        <p class="text-muted small mb-0">Manage employee accounts and system credentials</p>
                    </div>
                    <div class="col-auto">
                        <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-person-plus me-2"></i> ADD EMPLOYEE
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
                            placeholder="Search name, ID, or college...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">FULL NAME</th>
                                <th class="py-3 text-secondary small fw-bold">ROLE(S)</th>
                                <th class="py-3 text-secondary small fw-bold">COLLEGE / OFFICE</th>
                                <th class="py-3 text-secondary small fw-bold">USERNAME</th>
                                <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="5" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                    <div class="mt-2 text-muted small">Fetching records...</div>
                                </td>
                            </tr>
                            <template v-else>
                                <tr v-for="emp in filteredEmployees" :key="emp.id">
                                    <td class="ps-4">
                                        <div class="fw-semibold text-dark">
                                            {{ emp.user?.last_name }}, {{ emp.user?.first_name }}
                                        </div>
                                        <div class="small text-muted">{{ emp.Employee_Number }}</div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <span class="badge rounded-pill bg-light text-dark border px-2 py-1 small">
                                                {{ formatRole(emp.user?.role) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark">{{ emp.department?.College_Name || 'N/A'
                                        }}</div>
                                        <div class="small text-muted">{{ emp.office?.Office_Name || 'N/A' }}</div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark">{{ emp.user?.username }}</div>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button @click="openModal(emp)" class="btn btn-icon btn-light-success me-2"
                                            title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteEmployee(emp)" class="btn btn-icon btn-light-danger"
                                            :disabled="deletingId === emp.id" title="Delete">
                                            <span v-if="deletingId === emp.id"
                                                class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Edit Employee' : 'New Employee Account' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveEmployee">
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-uppercase label-required">First
                                        Name</label>
                                    <input v-model="form.first_name" type="text" class="form-control" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-uppercase">M.I.</label>
                                    <input v-model="form.middle_initial" type="text" class="form-control" maxlength="2">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-uppercase label-required">Last
                                        Name</label>
                                    <input v-model="form.last_name" type="text" class="form-control" required>
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
                                                <label class="form-check-label fw-bold text-dark"
                                                    for="roleDept">College
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
                                                        id="chkInst" v-model="form.roles">
                                                    <label class="form-check-label small"
                                                        for="chkInst">Instructor</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                        value="entrance_examiner" id="chkExam" v-model="form.roles">
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

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">College</label>
                                    <select v-model="form.college_id" class="form-select">
                                        <option value="">None / Unassigned</option>
                                        <option v-for="dept in colleges" :key="dept.id" :value="dept.id">{{
                                            dept.College_Name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Office</label>
                                    <select v-model="form.office_id" class="form-select">
                                        <option value="">None / Unassigned</option>
                                        <option v-for="off in offices" :key="off.id" :value="off.id">{{ off.Office_Name
                                        }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-12" v-if="editMode">
                                    <label class="form-label small fw-bold text-uppercase">Employee Number</label>
                                    <input v-model="form.employee_number" type="text" class="form-control" readonly
                                        disabled>
                                </div>

                                <div class="col-md-12"> <label
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
                                        :class="{ 'label-required': !editMode || form.password.length > 0 }">Confirm
                                        Password</label>
                                    <input v-model="form.password_confirmation" type="password" class="form-control"
                                        :class="{ 'is-invalid': showPassError }" @focus="isConfirmFocused = true">
                                    <div class="invalid-feedback">Passwords do not match.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal"
                                :disabled="isSaving">CANCEL</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold"
                                :disabled="isSaving || form.roles.length === 0 || (form.password !== form.password_confirmation)">
                                <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"></span>
                                {{ isSaving ? 'SAVING...' : 'SAVE DATA' }}
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

const employees = ref([]);
const colleges = ref([]);
const offices = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const modalRef = ref(null);
let modalInstance = null;

const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);
const isConfirmFocused = ref(false);
const roleSelectionType = ref('staff');

const form = reactive({
    first_name: '',
    middle_initial: '',
    last_name: '',
    extension_name: '',
    employee_number: '',
    college_id: '',
    office_id: '',
    username: '',
    // email removed from here
    password: '',
    password_confirmation: '',
    roles: []
});

const handleRoleTypeChange = () => {
    form.roles = roleSelectionType.value === 'college_dean' ? ['college_dean'] : [];
};

const passwordsDoNotMatch = computed(() => form.password !== form.password_confirmation);
const showPassError = computed(() => isConfirmFocused.value && passwordsDoNotMatch.value && form.password_confirmation.length > 0);

const filteredEmployees = computed(() => {
    return employees.value.filter(e => {
        const str = `${e.user?.first_name} ${e.user?.last_name} ${e.Employee_Number} ${e.department?.College_Name}`.toLowerCase();
        return str.includes(searchQuery.value.toLowerCase());
    });
});

onMounted(() => {
    fetchEmployees();
    fetchOptions();
    modalInstance = new Modal(modalRef.value);
});

const fetchOptions = async () => {
    try {
        const [deptRes, offRes] = await Promise.all([axios.get('/api/admin/colleges'), axios.get('/api/admin/offices')]);
        colleges.value = deptRes.data.data || deptRes.data;
        offices.value = offRes.data.data || offRes.data;
    } catch (err) { console.error(err); }
};

const fetchEmployees = async () => {
    isLoading.value = true;
    try {
        const res = await axios.get('/api/admin/employees');
        employees.value = res.data.data || res.data;
    } catch (err) { console.error(err); }
    finally { isLoading.value = false; }
};

const openModal = (emp = null) => {
    editMode.value = !!emp;
    currentId.value = emp ? emp.id : null;
    isConfirmFocused.value = false;

    if (emp) {
        const currentRole = emp.user?.role || '';
        roleSelectionType.value = currentRole === 'college_dean' ? 'college_dean' : 'staff';

        Object.assign(form, {
            first_name: emp.user.first_name,
            middle_initial: emp.user.middle_initial,
            last_name: emp.user.last_name,
            extension_name: emp.user.extension_name,
            employee_number: emp.Employee_Number || '',
            college_id: emp.college_id || '',
            office_id: emp.office_id || '',
            username: emp.user.username,
            // email assignment removed
            roles: currentRole.split(','),
            password: '',
            password_confirmation: ''
        });
    } else {
        Object.keys(form).forEach(k => form[k] = k === 'roles' ? [] : '');
        roleSelectionType.value = 'staff';
    }
    modalInstance.show();
};

const saveEmployee = async () => {
    isSaving.value = true;
    try {
        const method = editMode.value ? 'put' : 'post';
        const url = editMode.value ? `/api/admin/employees/${currentId.value}` : '/api/admin/employees';
        await axios[method](url, form);
        modalInstance.hide();
        fetchEmployees();
        window.Toast.fire({ icon: 'success', title: 'Action Successful' });
    } catch (e) {
        window.Swal.fire({ icon: 'error', title: 'Action Failed', text: e.response?.data?.message || 'Check your inputs.' });
    } finally { isSaving.value = false; }
};

const formatRole = (role) => role ? role.replace(/_/g, ' ').toUpperCase() : 'N/A';

const deleteEmployee = async (emp) => {
    const result = await window.Swal.fire({
        title: 'Are you sure?',
        text: `Deleting ${emp.user.first_name} ${emp.user.last_name} will remove system access.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete'
    });

    if (result.isConfirmed) {
        deletingId.value = emp.id;
        try {
            await axios.delete(`/api/admin/employees/${emp.id}`);
            fetchEmployees();
            window.Toast.fire({ icon: 'success', title: 'Deleted' });
        } catch (err) { window.Swal.fire('Error', 'Delete failed.', 'error'); }
        finally { deletingId.value = null; }
    }
};
</script>

<style scoped>
/* Emerald UI Kit Styles */
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

.custom-check {
    transform: scale(1.1);
}
</style>
