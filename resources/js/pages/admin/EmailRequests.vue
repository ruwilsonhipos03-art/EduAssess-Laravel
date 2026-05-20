<template>
    <div class="container-fluid py-4 email-requests-page">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center g-3">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">Email Requests</h4>
                        <p class="text-muted small mb-0">Track messages sent to users and their delivery status.</p>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-emerald fw-bold px-4 shadow-sm" @click="fetchEmailRequests" :disabled="isLoading">
                            <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                            <i v-else class="bi bi-arrow-clockwise me-2"></i>REFRESH
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="px-4 py-3 border-bottom bg-light">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input v-model="searchQuery" type="text" class="form-control border-start-0 ps-0 shadow-none"
                                    placeholder="Search name, email, subject, or message...">
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <select v-model="statusFilter" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="success">Success</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">No.</th>
                                <th class="py-3 text-secondary small fw-bold">FULL NAME</th>
                                <th class="py-3 text-secondary small fw-bold">MESSAGE</th>
                                <th class="pe-4 py-3 text-secondary small fw-bold">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="5" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                    <div class="mt-2 text-muted small">Loading email requests...</div>
                                </td>
                            </tr>

                            <template v-else>
                                <tr v-for="(row, index) in filteredRows" :key="row.id" class="clickable-row"
                                    tabindex="0" @click="openDetails(row)" @keyup.enter="openDetails(row)">
                                    <td class="ps-4 text-muted">{{ index + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ row.full_name || '-' }}</div>
                                        <div class="small text-muted">{{ row.email || '-' }}</div>
                                    </td>
                                    <td class="message-cell">
                                        <div class="fw-semibold text-dark mb-1">{{ row.subject || 'Email Message' }}</div>
                                        <div class="message-preview">{{ messagePreview(row) }}</div>
                                        <div v-if="row.error_message" class="small text-danger mt-2">
                                            {{ row.error_message }}
                                        </div>
                                    </td>
                                    <td class="pe-4">
                                        <span class="status-pill" :class="statusClass(row.status)">
                                            {{ prettyStatus(row.status) }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="filteredRows.length === 0">
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        No email requests found.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="selectedRow" class="detail-backdrop" @click.self="closeDetails">
            <div class="detail-modal" role="dialog" aria-modal="true" aria-labelledby="emailRequestTitle">
                <div class="detail-header">
                    <div>
                        <h5 id="emailRequestTitle" class="fw-bold mb-1">{{ selectedRow.subject || 'Email Message' }}</h5>
                        <div class="small text-muted">{{ selectedRow.full_name || '-' }} · {{ selectedRow.email || '-' }}</div>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close" @click="closeDetails"></button>
                </div>
                <div class="detail-body">
                    <div class="mb-3">
                        <span class="status-pill" :class="statusClass(selectedRow.status)">
                            {{ prettyStatus(selectedRow.status) }}
                        </span>
                        <button
                            v-if="selectedRow.status === 'failed'"
                            type="button"
                            class="btn btn-sm btn-outline-emerald ms-2"
                            :disabled="resendingId === selectedRow.id"
                            @click="resendEmail(selectedRow)"
                        >
                            <span v-if="resendingId === selectedRow.id" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-send me-1"></i>
                            Resend
                        </button>
                    </div>
                    <div class="full-message">{{ selectedRow.message || '-' }}</div>
                    <div v-if="selectedRow.error_message" class="alert alert-danger mt-3 mb-0">
                        {{ selectedRow.error_message }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const rows = ref([]);
const isLoading = ref(false);
const resendingId = ref(null);
const searchQuery = ref('');
const statusFilter = ref('');
const selectedRow = ref(null);

const filteredRows = computed(() => {
    const term = searchQuery.value.trim().toLowerCase();
    return rows.value.filter((row) => {
        const matchesStatus = !statusFilter.value || row.status === statusFilter.value;
        const searchable = [
            row.full_name,
            row.email,
            row.subject,
            row.message,
        ].join(' ').toLowerCase();

        return matchesStatus && (!term || searchable.includes(term));
    });
});

const prettyStatus = (value) => {
    const text = String(value || '').trim();
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : 'Pending';
};

const statusClass = (value) => {
    const status = String(value || '').toLowerCase();
    if (status === 'success') return 'status-success';
    if (status === 'failed') return 'status-failed';
    return 'status-pending';
};

const messagePreview = (row) => {
    const subject = String(row?.subject || 'Email Message').trim();
    const fullName = String(row?.full_name || '').trim() || 'User';

    if (subject.toLowerCase() === 'entrance exam schedule details') {
        return `Hello ${fullName}, your Entrance Exam schedule has been confirmed.`;
    }

    const firstLine = String(row?.message || '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .find(Boolean);

    return firstLine || '-';
};

const openDetails = (row) => {
    selectedRow.value = row;
};

const closeDetails = () => {
    selectedRow.value = null;
};

const fetchEmailRequests = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/api/admin/email-requests');
        rows.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        rows.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load email requests',
            text: error?.response?.data?.message || 'Please refresh and try again.',
            confirmButtonColor: '#10b981',
        });
    } finally {
        isLoading.value = false;
    }
};

const resendEmail = async (row) => {
    if (!row?.id || resendingId.value) return;

    resendingId.value = row.id;
    try {
        await axios.post(`/api/admin/email-requests/${row.id}/resend`);
        window.Toast?.fire({ icon: 'success', title: 'Email resend queued.' });
        await fetchEmailRequests();
        if (selectedRow.value?.id === row.id) {
            selectedRow.value = rows.value.find((item) => item.id === row.id) || selectedRow.value;
        }
    } catch (error) {
        window.Swal?.fire({
            icon: 'error',
            title: 'Resend failed',
            text: error?.response?.data?.message || 'Please check the mail settings and try again.',
            confirmButtonColor: '#10b981',
        });
    } finally {
        resendingId.value = null;
    }
};

onMounted(fetchEmailRequests);
</script>

<style scoped>
.email-requests-page {
    --emerald: #10b981;
    --emerald-deep: #059669;
}

.btn-emerald {
    background-color: var(--emerald);
    color: white;
    border: none;
}

.btn-emerald:hover {
    background-color: var(--emerald-deep);
    color: white;
}

.btn-outline-emerald {
    border-color: var(--emerald);
    color: var(--emerald-deep);
    font-weight: 700;
}

.btn-outline-emerald:hover,
.btn-outline-emerald:focus {
    background-color: var(--emerald);
    border-color: var(--emerald);
    color: #fff;
}

.text-emerald {
    color: var(--emerald);
}

.message-cell {
    min-width: 360px;
    max-width: 680px;
}

.message-preview {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: #475569;
    font-size: 0.9rem;
    line-height: 1.45;
}

.clickable-row {
    cursor: pointer;
}

.clickable-row:focus {
    outline: 2px solid rgba(16, 185, 129, 0.35);
    outline-offset: -2px;
}

.detail-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1050;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.detail-modal {
    width: min(720px, 100%);
    max-height: min(82vh, 760px);
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}

.detail-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.detail-body {
    padding: 24px;
    overflow: auto;
    max-height: calc(min(82vh, 760px) - 88px);
}

.full-message {
    white-space: pre-wrap;
    color: #334155;
    line-height: 1.6;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.38rem 0.78rem;
    font-size: 0.8rem;
    font-weight: 700;
}

.status-pending {
    background: #fff7ed;
    color: #c2410c;
}

.status-success {
    background: #ecfdf5;
    color: #047857;
}

.status-failed {
    background: #fef2f2;
    color: #b91c1c;
}
</style>
