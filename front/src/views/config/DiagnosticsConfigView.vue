<template>
  <section class="lab-resource-page settings-page">
    <div class="lab-toolbar lab-resource-toolbar settings-toolbar"><div><RouterLink class="settings-back" to="/config"><LabIcon name="left" /> Volver a Configuración</RouterLink><h1 class="lab-resource-title">Diagnóstico</h1><p class="lab-resource-description">Consulta el contexto técnico disponible para revisar el servicio sin modificarlo.</p></div><span class="settings-section-badge is-neutral"><LabIcon name="server" /> Solo lectura</span></div>
    <BaseAlert v-if="error" variant="danger" :message="error" />
    <LoadingSpinner v-if="loading" label="Cargando diagnóstico..." />
    <section v-else class="settings-diagnostics"><div class="settings-diagnostics-intro"><span class="settings-section-icon is-neutral"><LabIcon name="server" /></span><div><h2>Información del sistema</h2><p>Estos valores son informativos y no exponen credenciales ni secretos.</p></div></div><dl><div v-for="item in items" :key="item.label"><dt>{{ item.label }}</dt><dd>{{ item.value || 'No disponible' }}</dd></div></dl></section>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { getSystemInfo } from '@/api/config';
import { getApiErrorMessage, unwrapData } from '@/api/client';
import BaseAlert from '@/components/base/BaseAlert.vue'; import LoadingSpinner from '@/components/base/LoadingSpinner.vue'; import LabIcon from '@/components/dashboard/lab/LabIcon.vue';
const loading = ref(true); const error = ref(''); const system = ref({});
const items = computed(() => [{ label: 'Versión de PHP', value: system.value.php_version }, { label: 'Versión de Laravel', value: system.value.laravel_version }, { label: 'Entorno', value: system.value.environment }, { label: 'Motor de base de datos', value: system.value.db_driver }]);
async function load() { loading.value = true; error.value = ''; try { system.value = unwrapData(await getSystemInfo()) || {}; } catch (requestError) { error.value = getApiErrorMessage(requestError, 'No se pudo cargar la información de diagnóstico.'); } finally { loading.value = false; } }
onMounted(load);
</script>
