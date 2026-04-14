<template>
    <div class="ph-wrapper">
        <TopNav @toggle-sidebar="isCollapsed = !isCollapsed" />

        <div class="main-body">
            <Sidebar :isCollapsed="isCollapsed" />

            <main class="content-container" :class="{ 'sidebar-collapsed': isCollapsed }">
                <router-view></router-view>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import TopNav from '../components/college-dean/topnav.vue';
import Sidebar from '../components/college-dean/sidebar.vue';

const isCollapsed = ref(false);
</script>

<style scoped>
/* Identical styling to Admin for visual consistency */
.ph-wrapper {
    display: flex;
    flex-direction: column;
    height: 100vh;
}

.main-body {
    display: flex;
    flex: 1;
    overflow: hidden;
    /* Prevents double scrollbars */
}

.content-container {
    flex: 1;
    overflow-y: auto;
    padding: 32px;
    /* Matches Admin's spacious padding */
    background-color: #f8fafc;
    transition: all 0.3s ease;
}

/* Ensures the container adapts if the sidebar width changes */
.sidebar-collapsed {
    margin-left: 80px;
    /* Adjust based on collapsed sidebar width */
}
</style>
