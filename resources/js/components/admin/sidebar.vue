<template>
    <aside class="sidebar" :class="{ 'collapsed': isCollapsed }">
        <div class="nav-links">
            <div class="section-label" v-if="!isCollapsed">MAIN</div>

            <router-link to="/admin/dashboard" class="nav-item">
                <i class="bi bi-grid-fill"></i>
                <span v-if="!isCollapsed">Dashboard</span>
            </router-link>

            <div class="section-label" v-if="!isCollapsed">TABLES</div>

            <div class="nav-group">
                <button @click="toggleManagement" class="nav-item btn-dropdown"
                    :class="{ 'active': isMgmtOpen && !isCollapsed }">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-folder-fill"></i>
                        <span v-if="!isCollapsed">Management</span>
                    </div>
                    <i v-if="!isCollapsed" class="bi bi-chevron-down ms-auto arrow"
                        :class="{ 'rotate': isMgmtOpen }"></i>
                </button>

                <div class="sub-nav" v-if="isMgmtOpen && !isCollapsed">
                    <router-link to="/admin/Subjects" class="sub-item">
                        <i class="bi bi-book-half"></i> Subjects
                    </router-link>
                    <router-link to="/admin/Offices" class="sub-item">
                        <i class="bi bi-building-fill"></i> Offices
                    </router-link>
                    <router-link to="/admin/Colleges" class="sub-item">
                        <i class="bi bi-mortarboard-fill"></i> Colleges
                    </router-link>
                    <router-link to="/admin/Programs" class="sub-item">
                        <i class="bi bi-journal-text"></i> Programs
                    </router-link>
                    <router-link to="/admin/Schedules" class="sub-item">
                        <i class="bi bi-calendar3"></i> Entrance Exam Schedules
                    </router-link>
                </div>
            </div>
            <router-link to="/admin/scheduled-students" class="nav-item">
                <i class="bi bi-card-list"></i> Download Schedules
            </router-link>
            <router-link to="/admin/users" class="nav-item">
                <i class="bi bi-people-fill"></i>
                <span v-if="!isCollapsed">Users</span>
            </router-link>

            <router-link to="/admin/exam-reports" class="nav-item">
                <i class="bi bi-journal-richtext"></i>
                <span v-if="!isCollapsed">Exam Reports</span>
            </router-link>

            <router-link to="/admin/reports" class="nav-item"
                :class="{ 'has-dot': hasDot('reports') && !reportsActive }" @click="handleNavClick('reports')">
                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                <span v-if="!isCollapsed">Reports</span>
            </router-link>
        </div>
    </aside>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useNotifications } from '../../composables/useNotifications';
defineProps(['isCollapsed']);

const route = useRoute();
const isMgmtOpen = ref(false);
const { hasDot, markSeen, summary } = useNotifications();
const reportsActive = computed(() => route.path === '/admin/reports');

const handleNavClick = (tabKey) => {
    const latest = summary.value?.tabs?.[tabKey]?.latest_at || null;
    markSeen(tabKey, latest || new Date().toISOString());
};

watch(
    [reportsActive, () => summary.value?.tabs?.reports?.latest_at || null],
    ([isActive, latest]) => {
        if (!isActive) return;
        markSeen('reports', latest || new Date().toISOString());
    },
    { immediate: true }
);
const toggleManagement = () => {
    isMgmtOpen.value = !isMgmtOpen.value;
};
</script>

<style scoped>
/* Keep your existing .sidebar, .section-label, and .nav-item styles */

.sidebar {
    width: 300px;
    background-color: var(--sidebar-bg);
    /* Deep Green */
    height: 100%;
    transition: width 0.3s ease;
    padding: 20px 12px;
    overflow-x: hidden;
    white-space: nowrap;
}

.sidebar.collapsed {
    width: 90px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 18px;
    color: var(--text-muted);
    /* Light Mint */
    text-decoration: none;
    border-radius: 10px;
    margin-bottom: 5px;
    transition: all 0.2s;
    width: 100%;
    border: none;
    background: transparent;
    text-align: left;
    position: relative;
}

.nav-item:hover {
    background: var(--sidebar-hover);
    color: white;
}

.router-link-active,
.active {
    background: var(--primary-color) !important;
    /* Bright Emerald */
    color: white !important;
}

/* Dropdown Specific Styles */
.btn-dropdown {
    cursor: pointer;
}

.arrow {
    transition: transform 0.3s ease;
    font-size: 0.8rem;
}

.arrow.rotate {
    transform: rotate(180deg);
}

.sub-nav {
    display: flex;
    flex-direction: column;
    padding-left: 20px;
    margin-bottom: 10px;
}

.sub-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 18px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.9rem;
    border-radius: 8px;
    transition: 0.2s;
}

.sub-item i {
    font-size: 1rem;
}

.sub-item:hover {
    color: white;
}

.sub-item.router-link-active {
    background: transparent !important;
    color: #34d399 !important;
    /* Lighter Emerald for sub-links */
    box-shadow: none;
    font-weight: 600;
}

.section-label {
    color: var(--primary-color);
    /* Emerald 500 */
    font-size: 0.75rem;
    font-weight: 600;
    margin: 20px 0 10px 10px;
    letter-spacing: 1px;
}

.nav-item.has-dot::after {
    content: '';
    position: absolute;
    top: 8px;
    right: 12px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ef4444;
    box-shadow: 0 0 0 2px var(--sidebar-bg);
}
</style>
