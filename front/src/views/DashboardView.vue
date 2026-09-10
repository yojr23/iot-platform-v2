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
        <SensorMonitorBoard v-else :devices="graphDevices" />
    </section>
</template>
<script setup>
import { onBeforeUnmount, ref, watch } from "vue";
import { getGraphBootstrap } from "@/api/graph";
import { getApiErrorMessage, unwrapData } from "@/api/client";
import SensorMonitorBoard from "@/components/dashboard/SensorMonitorBoard.vue";
import { useAuthStore } from "@/stores/auth";
const auth = useAuthStore(),
    loading = ref(true),
    error = ref(""),
    graphDevices = ref([]);
let controller,
    generation = 0;
async function load() {
    const g = ++generation;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = "";
    try {
        const payload = unwrapData(
            await getGraphBootstrap({ signal: controller.signal }),
        );
        if (g === generation) graphDevices.value = payload?.devices || [];
    } catch (e) {
        if (g === generation && e.code !== "ERR_CANCELED")
            error.value = getApiErrorMessage(
                e,
                "No se pudieron cargar las gráficas.",
            );
    } finally {
        if (g === generation) loading.value = false;
    }
}
watch(() => auth.isAuthenticated, load, { immediate: true });
onBeforeUnmount(() => {
    generation++;
    controller?.abort();
});
</script>
