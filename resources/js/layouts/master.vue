<template>
  <div class="master-wrapper">
    <router-view></router-view>
  </div>
</template>

<style>
/* Global Styles - Keep these here */
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

* {
  box-sizing: border-box;
}

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  margin: 0;
  padding: 0;
  background-color: #f8fafc; /* Keep the light grey background globally */
  height: 100vh;
  width: 100vw;
  overflow: hidden; /* This prevents the "double scrollbar" issue */
}

.master-wrapper {
  height: 100%;
  width: 100%;
}
:root {
  --sidebar-width: 260px;
  --topbar-height: 70px;
  /* Primary Green Palette */
  --primary-color: #10b981;    /* Emerald 500 */
  --primary-hover: #059669;    /* Emerald 600 */
  --sidebar-bg: #064e3b;       /* Emerald 900 (Deep Green) */
  --sidebar-hover: #065f46;    /* Emerald 800 */
  --text-light: #ecfdf5;       /* Emerald 50 */
  --text-muted: #a7f3d0;       /* Emerald 200 */
  --bg-light: #f0fdf4;         /* Emerald 50 (Very light wash) */
}
</style>