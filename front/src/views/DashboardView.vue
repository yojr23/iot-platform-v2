<template>
    <section class="lab-dashboard">
        <div v-if="loading" class="lab-loading" role="status">
            Cargando tu espacio de monitoreo…
        </div>
        <div v-else-if="error" class="lab-empty" role="alert">
            <h1>No se pudo cargar el tablero</h1>
            <p>{{ error }}</p>
            <button class="lab-button" @click="load">Reintentar</button>
        </div>
        <template v-else>
            <!-- Operational metrics are authenticated-only. Guests get the public graph solely
                 (guest boundary — see DashboardView guest containment test): no device/alert
                 counts leak to an anonymous visitor. -->
            <div v-if="auth.isAuthenticated" class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="content-panel p-3 h-100">
                        <p class="text-muted small mb-1">Dispositivos</p>
                        <p class="h4 mb-0">{{ metrics.total_devices }}</p>
                        <p class="text-muted small mb-0">
                            <span class="text-success">{{ metrics.online_devices }} en linea</span>
                            · {{ metrics.offline_devices }} fuera
                        </p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="content-panel p-3 h-100">
                        <p class="text-muted small mb-1">Sensores</p>
                        <p class="h4 mb-0">{{ metrics.total_sensors }}</p>
                        <p class="text-muted small mb-0">Lecturas hoy: {{ metrics.readings_today }}</p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="content-panel p-3 h-100">
                        <p class="text-muted small mb-1">Alertas activas</p>
                        <p class="h4 mb-0" :class="metrics.active_alerts > 0 ? 'text-danger' : 'text-success'">
                            {{ metrics.active_alerts }}
                        </p>
                        <p class="text-muted small mb-0">{{ metrics.total_alert_rules }} reglas ({{ metrics.enabled_rules }} activas)</p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="content-panel p-3 h-100">
                        <p class="text-muted small mb-1">Uptime</p>
                        <p class="h4 mb-0" :class="metrics.uptime_percent >= 99 ? 'text-success' : metrics.uptime_percent >= 95 ? 'text-warning' : 'text-danger'">
                            {{ metrics.uptime_percent }}%
                        </p>
                        <p class="text-muted small mb-0">{{ metrics.total_labs }} laboratorios</p>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12">
                    <SensorMonitorBoard :devices="graphDevices" />
                </div>
            </div>
        </template>
    </section>
</template>
<script setup>
import { onBeforeUnmount, ref, watch } from "vue";
import { getGraphBootstrap } from "@/api/graph";
import { getMetrics } from "@/api/metrics";
import { getApiErrorMessage, unwrapData } from "@/api/client";
import SensorMonitorBoard from "@/components/dashboard/SensorMonitorBoard.vue";
import { useAuthStore } from "@/stores/auth";
const auth = useAuthStore(),
    loading = ref(true),
    error = ref(""),
    graphDevices = ref([]),
    metrics = ref({
        total_devices: 0,
        online_devices: 0,
        offline_devices: 0,
        total_sensors: 0,
        active_alerts: 0,
        total_alert_rules: 0,
        enabled_rules: 0,
        readings_today: 0,
        uptime_percent: 0,
        total_labs: 0,
    });
let controller,
    generation = 0;
async function load() {
    const g = ++generation;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = "";
    try {
        const graphPayload = await getGraphBootstrap({ signal: controller.signal });
        if (g === generation) {
            graphDevices.value = unwrapData(graphPayload)?.devices || [];
        }
    } catch (e) {
        if (g === generation && e.code !== "ERR_CANCELED")
            error.value = getApiErrorMessage(
                e,
                "No se pudieron cargar las gráficas.",
            );
    } finally {
        if (g === generation) loading.value = false;
    }
    if (auth.isAuthenticated) {
        try {
            const metricsResponse = await getMetrics({ signal: controller.signal });
            if (g === generation) {
                metrics.value = unwrapData(metricsResponse) || metrics.value;
            }
        } catch { /* metrics are optional for public view */ }
    }
}
watch(() => auth.isAuthenticated, load, { immediate: true });
onBeforeUnmount(() => {
    generation++;
    controller?.abort();
});
</script>
