<template>
  <div class="admin-wrapper">
    <AdminTopNav @toggle-sidebar="isCollapsed = !isCollapsed" />
    
    <div class="main-body">
      <AdminSidebar :isCollapsed="isCollapsed" />
      
      <main class="content-container" :class="{ 'sidebar-collapsed': isCollapsed }">
        <router-view></router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import AdminSidebar from '../components/admin/sidebar.vue';
import AdminTopNav from '../components/admin/topnav.vue';

const isCollapsed = ref(false);
</script>

<style scoped>
.admin-wrapper {
  display: flex;
  flex-direction: column;
  height: 100vh;
}
.main-body {
  display: flex;
  flex: 1;
  overflow: hidden; /* Prevents double scrollbars */
}
.content-container {
  flex: 1;
  overflow-y: auto;
  padding: 32px;
  background-color: #f8fafc;
  transition: all 0.3s ease;
}
</style>