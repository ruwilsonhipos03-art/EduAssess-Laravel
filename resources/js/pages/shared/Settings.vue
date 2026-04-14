<template>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h4 class="fw-bold mb-1">Settings</h4>
                                <div class="text-muted small">Manage preferences and account security</div>
                            </div>
                            <i class="bi bi-gear fs-3 text-emerald"></i>
                        </div>

                        <div class="border-top pt-4">
                            <h6 class="fw-bold mb-3">Notifications</h6>
                            <div class="d-flex align-items-center justify-content-between py-2">
                                <div>
                                    <div class="fw-semibold">Email updates</div>
                                    <div class="text-muted small">Receive announcements and reminders.</div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" v-model="emailUpdates">
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between py-2">
                                <div>
                                    <div class="fw-semibold">Exam alerts</div>
                                    <div class="text-muted small">Be notified when schedules change.</div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" v-model="examAlerts">
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-4">
                            <h6 class="fw-bold mb-3">Appearance</h6>
                            <div class="d-flex align-items-center justify-content-between py-2">
                                <div>
                                    <div class="fw-semibold">Compact layout</div>
                                    <div class="text-muted small">Reduce spacing on lists and cards.</div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" v-model="compactLayout">
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <h6 class="fw-bold mb-1">Change Password</h6>
                                    <div class="text-muted small">Update your password in a secure popup.</div>
                                </div>
                                <button class="btn btn-emerald px-4" type="button" @click="openPasswordModal">
                                    <i class="bi bi-shield-lock me-2"></i>
                                    Change Password
                                </button>
                            </div>
                        </div>

                        <div class="border-top pt-4 d-flex justify-content-end">
                            <button class="btn btn-outline-emerald px-4" @click="saveSettings">
                                Save Preferences
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="saved" class="alert alert-success mt-3">Preferences saved.</div>
            </div>
        </div>

        <div v-if="showPasswordModal" class="modal-backdrop-custom" @click.self="closePasswordModal">
            <div class="modal-card shadow-lg">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">Change Password</h5>
                        <div class="text-muted small">Use your current password to set a new one.</div>
                    </div>
                    <button class="btn-close" type="button" aria-label="Close" @click="closePasswordModal"></button>
                </div>

                <form class="row g-3" @submit.prevent="changePassword">
                    <div class="col-12">
                        <label class="form-label small fw-bold text-secondary">CURRENT PASSWORD</label>
                        <input v-model="passwordForm.current_password" type="password" class="form-control"
                            :disabled="passwordSaving" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">NEW PASSWORD</label>
                        <input v-model="passwordForm.password" type="password" class="form-control"
                            :disabled="passwordSaving" minlength="8" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">CONFIRM PASSWORD</label>
                        <input v-model="passwordForm.password_confirmation" type="password" class="form-control"
                            :disabled="passwordSaving" minlength="8" required>
                        <div v-if="passwordMismatch" class="text-danger small mt-1">
                            Password confirmation does not match.
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button class="btn btn-light border px-4" type="button" :disabled="passwordSaving"
                            @click="closePasswordModal">
                            Cancel
                        </button>
                        <button class="btn btn-emerald px-4" type="submit"
                            :disabled="passwordSaving || passwordMismatch || !canSubmitPassword">
                            <span v-if="passwordSaving" class="spinner-border spinner-border-sm me-2"></span>
                            Save Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import axios from 'axios';

const emailUpdates = ref(true);
const examAlerts = ref(true);
const compactLayout = ref(false);
const saved = ref(false);
const passwordSaving = ref(false);
const showPasswordModal = ref(false);

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: ''
});

const passwordMismatch = computed(() => {
    return passwordForm.password_confirmation.length > 0 && passwordForm.password !== passwordForm.password_confirmation;
});

const canSubmitPassword = computed(() => {
    return Boolean(passwordForm.current_password && passwordForm.password && passwordForm.password_confirmation);
});

const saveSettings = () => {
    saved.value = true;
    window.setTimeout(() => {
        saved.value = false;
    }, 2000);
};

const resetPasswordForm = () => {
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
};

const openPasswordModal = () => {
    showPasswordModal.value = true;
};

const closePasswordModal = () => {
    if (passwordSaving.value) return;
    showPasswordModal.value = false;
    resetPasswordForm();
};

const changePassword = async () => {
    if (passwordMismatch.value || !canSubmitPassword.value) return;

    passwordSaving.value = true;
    try {
        await axios.put('/api/profile/password', passwordForm);
        resetPasswordForm();
        showPasswordModal.value = false;
        window.Toast?.fire({ icon: 'success', title: 'Password changed' });
    } catch (error) {
        const msg = error.response?.data?.message || 'Failed to change password.';
        window.Swal?.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#10b981' });
    } finally {
        passwordSaving.value = false;
    }
};
</script>

<style scoped>
.text-emerald {
    color: #10b981;
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

.btn-outline-emerald {
    border: 1px solid #10b981;
    color: #10b981;
}

.btn-outline-emerald:hover {
    background-color: #10b981;
    color: white;
}

.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    z-index: 1050;
}

.modal-card {
    width: min(100%, 640px);
    background: #fff;
    border-radius: 1rem;
    padding: 1.5rem;
}
</style>
