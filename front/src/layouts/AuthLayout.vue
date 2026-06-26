<template>
  <main class="auth-page">
    <section class="auth-panel p-4 shadow-sm">
      <div class="mb-4">
        <p class="text-muted text-uppercase small fw-semibold mb-1">iot-platform-v2</p>
        <h1 class="h4 mb-0">{{ title }}</h1>
      </div>

      <slot />
    </section>
  </main>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: 'Acceso'
  }
});
</script>
