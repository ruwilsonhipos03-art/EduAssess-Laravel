<template>
    <div class="dashboard-container">
        <div class="content-header mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold text-dark">Welcome back, Instructor! 👋</h1>
                <p class="text-muted mb-0">Manage your exams and students here.</p>
            </div>

            <div class="text-end d-none d-md-block">
                <div class="fw-bold text-dark">{{ currentTime }}</div>
                <div class="small text-muted">System Status: Online</div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="action-card card border-0 shadow-sm p-4 rounded-4 bg-white">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="action-text">
                            <h3 class="fw-bold mb-1">Ready to check exams?</h3>
                            <p class="text-muted mb-0">Capture from camera.</p>
                        </div>
                        <button
                            class="btn btn-scan d-flex align-items-center gap-3 px-5 py-3 rounded-3 shadow"
                            :disabled="isScanning"
                            @click="openCameraModal"
                        >
                            <i class="bi bi-camera-video fs-2"></i>
                            <span class="fs-4 fw-bold">{{ isScanning ? 'PROCESSING...' : 'OPEN CAMERA' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="cameraModalOpen" class="camera-modal-backdrop" @click.self="closeCameraModal">
            <div class="camera-modal card border-0 shadow-lg rounded-4">
                <div class="camera-modal-header d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">Capture Answer Sheet</h5>
                </div>

                <div class="camera-modal-body p-4">
                    <p class="text-muted mb-3">
                        NETUM camera will be selected automatically if detected. You can switch camera below.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Camera Device</label>
                        <select
                            class="form-select"
                            v-model="selectedCameraId"
                            @change="onCameraSelectionChanged"
                            :disabled="isCameraLoading || isScanning || !cameras.length"
                        >
                            <option v-if="!cameras.length" value="">No camera detected</option>
                            <option v-for="camera in cameras" :key="camera.deviceId" :value="camera.deviceId">
                                {{ camera.label }}
                            </option>
                        </select>
                    </div>

                    <div class="camera-preview-wrap rounded-3 overflow-hidden bg-dark-subtle">
                        <video ref="videoRef" class="camera-preview" autoplay playsinline muted></video>
                    </div>

                    <p v-if="cameraError" class="text-danger small mt-3 mb-0">{{ cameraError }}</p>
                </div>

                <div class="camera-modal-footer d-flex justify-content-end gap-2 px-4 py-3 border-top">
                    <button type="button" class="btn btn-outline-secondary" :disabled="isScanning" @click="closeCameraModal">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-scan"
                        :disabled="isScanning || isCameraLoading || Boolean(cameraError)"
                        @click="captureAndScan"
                    >
                        {{ isScanning ? 'Processing...' : 'Capture and Scan' }}
                    </button>
                </div>
            </div>
        </div>

        <canvas ref="canvasRef" class="d-none"></canvas>

        <div class="row g-4 mb-4">
            <div class="col-md-4" v-for="(stat, index) in stats" :key="index">
                <button
                    type="button"
                    class="card border-0 shadow-sm p-4 rounded-4 stat-card h-100 bg-white text-start"
                    :class="{ 'stat-clickable': Boolean(stat.route) }"
                    :disabled="!stat.route"
                    @click="goToStat(stat)"
                >
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div :class="['stat-icon', stat.colorClass]">
                            <i :class="stat.icon"></i>
                        </div>
                    </div>
                    <div class="stat-value h2 fw-bold mb-1">{{ stat.value }}</div>
                    <div class="stat-label text-muted fw-medium">{{ stat.label }}</div>
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">Recent Activity</h5>
                <a href="#" class="text-emerald text-decoration-none small fw-bold">View All</a>
            </div>
            <div class="card-body p-0">
                <div v-if="!activities.length" class="p-4 text-center text-muted">
                    No recent activity.
                </div>
                <div v-else v-for="a in activities" :key="a.id" class="p-4 border-bottom d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3 bg-emerald-light text-emerald">
                        <i :class="a.icon"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark">{{ a.title }}</div>
                        <div class="small text-muted">{{ a.time }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const currentTime = ref('');
const isScanning = ref(false);
const router = useRouter();
let statsRefreshTimer = null;
const cameraModalOpen = ref(false);
const videoRef = ref(null);
const canvasRef = ref(null);
const cameras = ref([]);
const selectedCameraId = ref('');
const isCameraLoading = ref(false);
const cameraError = ref('');
const cameraSessionId = ref(0);
let activeStream = null;

const stats = ref([
    { key: 'total_students', label: 'Total Students', value: '0', icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald', route: '/instructor/students' },
    { key: 'subjects', label: 'Subjects', value: '0', icon: 'bi-book-fill', colorClass: 'bg-info-subtle text-info', route: '/instructor/subjects' },
    { key: 'passing_rate', label: 'Passing Rate', value: '0%', icon: 'bi-graph-up-arrow', colorClass: 'bg-emerald-light text-emerald', route: '/instructor/reports' }
]);

const activities = ref([]);

const formatActivityTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString();
};

const loadStats = async () => {
    try {
        const { data } = await axios.get('/api/instructor/dashboard/stats');
        stats.value.forEach((stat) => {
            if (stat.key === 'total_students') {
                stat.value = Number(data.total_students || 0).toLocaleString();
                return;
            }
            if (stat.key === 'subjects') {
                stat.value = Number(data.subjects || 0).toLocaleString();
                return;
            }
            if (stat.key === 'passing_rate') {
                stat.value = `${Number(data.passing_rate || 0).toFixed(2)}%`;
            }
        });
        const recent = Array.isArray(data?.recent_activities) ? data.recent_activities : [];
        const extractSubjectName = (value) => {
            if (!value) return '';
            const match = String(value).match(/subject \"([^\"]+)\"/i);
            return match?.[1] || '';
        };

        activities.value = recent.map((item) => {
            const meta = item?.meta || {};
            const subjectName = meta.subject_name
                || extractSubjectName(item?.description)
                || extractSubjectName(item?.title)
                || 'Subject';
            const studentName = meta.student_name || 'Student';
            const actionType = item?.action_type;

            let title = item?.title || item?.description || 'Activity';
            if (actionType === 'instructor_subject_assigned') {
                title = `You got assigned to a new subject "${subjectName}"`;
            } else if (actionType === 'student_subject_assigned') {
                title = `You got a new student "${studentName}" in "${subjectName}"`;
            }

            return {
                id: item?.id ?? `${actionType || 'activity'}-${item?.created_at || Math.random()}`,
                title,
                time: formatActivityTime(item?.created_at),
                icon: actionType === 'instructor_subject_assigned' ? 'bi-journal-plus' : 'bi-person-plus',
            };
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Failed to load dashboard stats',
            text: 'Please try refreshing the page.',
            confirmButtonColor: '#ef4444'
        });
    }
};

const goToStat = (stat) => {
    if (!stat?.route) return;
    router.push(stat.route);
};

const stopActiveStream = () => {
    if (!activeStream) return;
    activeStream.getTracks().forEach((track) => track.stop());
    activeStream = null;
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
};

const selectPreferredCamera = (list) => {
    if (!list.length) return '';

    const netum = list.find((device) => String(device.label || '').toLowerCase().includes('netum'));
    if (netum) return netum.deviceId;

    const scannerLike = list.find((device) => {
        const label = String(device.label || '').toLowerCase();
        return label.includes('scanner') || label.includes('document') || label.includes('usb');
    });

    return scannerLike?.deviceId || list[0].deviceId;
};

const loadCameraList = async () => {
    const devices = await navigator.mediaDevices.enumerateDevices();
    const list = devices
        .filter((device) => device.kind === 'videoinput')
        .map((device, index) => ({
            deviceId: device.deviceId,
            label: device.label || `Camera ${index + 1}`,
        }));

    cameras.value = list;
    return list;
};

const isCameraSessionActive = (sessionId) => {
    return cameraModalOpen.value && cameraSessionId.value === sessionId;
};

const startCamera = async (deviceId = '', sessionId = cameraSessionId.value) => {
    stopActiveStream();

    const constraints = deviceId
        ? { video: { deviceId: { exact: deviceId } }, audio: false }
        : { video: { facingMode: 'environment' }, audio: false };

    activeStream = await navigator.mediaDevices.getUserMedia(constraints);
    if (!isCameraSessionActive(sessionId)) {
        activeStream.getTracks().forEach((track) => track.stop());
        activeStream = null;
        return;
    }

    if (!videoRef.value) return;
    videoRef.value.srcObject = activeStream;
    await videoRef.value.play();
};

const initializeCamera = async (sessionId = cameraSessionId.value) => {
    if (!navigator?.mediaDevices?.getUserMedia || !navigator?.mediaDevices?.enumerateDevices) {
        cameraError.value = 'This browser does not support camera access.';
        return;
    }

    isCameraLoading.value = true;
    cameraError.value = '';

    try {
        const permissionStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        permissionStream.getTracks().forEach((track) => track.stop());
        if (!isCameraSessionActive(sessionId)) {
            return;
        }

        const availableCameras = await loadCameraList();
        if (!isCameraSessionActive(sessionId)) {
            return;
        }
        if (!availableCameras.length) {
            cameraError.value = 'No camera was detected on this device.';
            return;
        }

        selectedCameraId.value = selectPreferredCamera(availableCameras);
        await startCamera(selectedCameraId.value, sessionId);
    } catch (error) {
        cameraError.value = 'Unable to open camera. Please allow camera permission and try again.';
    } finally {
        if (isCameraSessionActive(sessionId)) {
            isCameraLoading.value = false;
        }
    }
};

const openCameraModal = async () => {
    if (isScanning.value) return;
    cameraSessionId.value += 1;
    const sessionId = cameraSessionId.value;
    cameraModalOpen.value = true;
    await nextTick();
    await initializeCamera(sessionId);
};

const closeCameraModal = () => {
    cameraSessionId.value += 1;
    stopActiveStream();
    cameraModalOpen.value = false;
    isCameraLoading.value = false;
    cameraError.value = '';
};

const onCameraSelectionChanged = async () => {
    if (!selectedCameraId.value || !cameraModalOpen.value) return;

    isCameraLoading.value = true;
    cameraError.value = '';
    try {
        await startCamera(selectedCameraId.value, cameraSessionId.value);
    } catch (error) {
        cameraError.value = 'Could not switch to the selected camera.';
    } finally {
        isCameraLoading.value = false;
    }
};

const captureFrameAsFile = async () => {
    const video = videoRef.value;
    const canvas = canvasRef.value;
    if (!video || !canvas) return null;

    const width = video.videoWidth || 1280;
    const height = video.videoHeight || 720;
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, width, height);

    const frameGray = context.getImageData(0, 0, width, height);
    let sum = 0;
    let sumSq = 0;
    for (let i = 0; i < frameGray.data.length; i += 4) {
        const g = 0.299 * frameGray.data[i] + 0.587 * frameGray.data[i + 1] + 0.114 * frameGray.data[i + 2];
        sum += g;
        sumSq += g * g;
    }
    const pixelCount = Math.max(1, frameGray.data.length / 4);
    const mean = sum / pixelCount;
    const variance = Math.max(0, sumSq / pixelCount - mean * mean);

    if (mean < 55 || mean > 235 || variance < 140) {
        return { file: null, reason: 'Frame is too dark/bright or blurry. Keep full sheet in frame, improve lighting, then capture again.' };
    }

    const blob = await new Promise((resolve) => {
        canvas.toBlob(resolve, 'image/jpeg', 0.95);
    });

    if (!blob) return { file: null, reason: 'Could not encode captured image.' };
    return {
        file: new File([blob], `capture-${Date.now()}.jpg`, { type: 'image/jpeg' }),
        reason: '',
    };
};

const captureAndScan = async () => {
    if (isScanning.value || cameraError.value) return;

    const captured = await captureFrameAsFile();
    const file = captured?.file || null;
    if (!file) {
        Swal.fire({
            icon: 'warning',
            title: 'Capture failed',
            text: captured?.reason || 'Could not capture an image from camera.',
            confirmButtonColor: '#f59e0b',
        });
        return;
    }

    const formData = new FormData();
    formData.append('image', file);

    closeCameraModal();
    await submitOmr(formData, 'captured image');
};

const submitOmr = async (formData, sourceLabel = 'image') => {
    isScanning.value = true;
    try {
        Swal.fire({
            title: 'Processing...',
            text: `Checking ${sourceLabel}. Please wait.`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        const endpoint = '/api/instructor/omr/check-term';

        const { data } = await axios.post(endpoint, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const processed = Array.isArray(data?.processed) ? data.processed : [];
        const successCount = processed.filter((item) => item?.success).length;
        const failed = processed.filter((item) => !item?.success);
        const failureText = failed
            .slice(0, 3)
            .map((item) => `${item?.file || 'file'}: ${item?.message || 'Failed to process.'}`)
            .join('\n');
        const summaryText = data?.message || `Processed ${successCount} file(s).`;
        const detailText = failureText ? `${summaryText}\n\n${failureText}` : summaryText;

        await Swal.fire({
            icon: successCount > 0 ? 'success' : 'warning',
            title: 'Scanning Completed',
            text: detailText,
            showCancelButton: true,
            confirmButtonText: 'Open Reports',
            cancelButtonText: 'Close',
            confirmButtonColor: '#10b981',
        }).then((res) => {
            if (res.isConfirmed) {
                router.push('/instructor/reports');
            }
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Scanning failed',
            text: error?.response?.data?.message || 'Could not process uploaded file(s).',
            confirmButtonColor: '#ef4444',
        });
    } finally {
        isScanning.value = false;
    }
};

onMounted(() => {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentTime.value = new Date().toLocaleDateString(undefined, options);
    loadStats();
    statsRefreshTimer = setInterval(loadStats, 30000);
});

onUnmounted(() => {
    if (statsRefreshTimer) {
        clearInterval(statsRefreshTimer);
        statsRefreshTimer = null;
    }
    stopActiveStream();
});
</script>

<style scoped>
/* Emerald UI Colors */
.text-emerald {
    color: #10b981;
}

.bg-emerald-light {
    background-color: #ecfdf5;
}

/* Dashboard Card Animations */
.stat-card {
    transition: transform 0.2s ease-in-out;
    box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.08) !important;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-clickable {
    cursor: pointer;
    border: none;
    background: transparent;
    display: block;
    width: 100%;
    margin: 0;
    font: inherit;
    line-height: inherit;
}

.stat-clickable::-moz-focus-inner {
    border: 0;
    padding: 0;
}

.stat-clickable:disabled {
    cursor: default;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.action-card {
    border-left: 6px solid #10b981 !important;
}

.btn-scan {
    background-color: #10b981;
    color: white;
    border: none;
    transition: all 0.3s ease;
}

.btn-scan:hover {
    background-color: #059669;
    transform: scale(1.02);
    color: white;
}

.btn-scan:active {
    transform: scale(0.98);
}

.camera-modal-backdrop {
    position: fixed;
    inset: 0;
    background-color: rgba(15, 23, 42, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    z-index: 1200;
}

.camera-modal {
    width: min(780px, 100%);
    max-height: calc(100vh - 2rem);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.camera-modal-header,
.camera-modal-footer {
    flex-shrink: 0;
}

.camera-modal-body {
    overflow-y: auto;
}

.camera-preview-wrap {
    aspect-ratio: 16 / 9;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.camera-preview {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #0f172a;
}

/* Custom Warning/Info states for PH dashboard */
.bg-warning-subtle {
    background-color: #fffbeb;
}

.text-warning {
    color: #f59e0b;
}

.bg-info-subtle {
    background-color: #eff6ff;
}

.text-info {
    color: #3b82f6;
}
</style>
