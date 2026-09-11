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
import { getDevices } from "@/api/devices";
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
        const graphPayload = await getGraphBootstrap({ signal: controller.signal });
        if (g !== generation) return;
        const publicDevices = unwrapData(graphPayload)?.devices || [];

        // DOCX RF01: authenticated users can see authorized restricted sensors.
        // Merge public bootstrap devices with the full authorized device catalog.
        let allDevices = publicDevices;
        if (auth.isAuthenticated) {
            try {
                const authResponse = await getDevices({ signal: controller.signal });
                if (g === generation) {
                    const authDevices = unwrapData(authResponse)?.data || [];
                    // Merge: auth devices take precedence (they include restricted sensors),
                    // public devices fill gaps for sensors not in the auth catalog.
                    const authMap = new Map(authDevices.map((d) => [String(d.id), d]));
                    const merged = [...authDevices];
                    for (const pd of publicDevices) {
                        if (!authMap.has(String(pd.id))) {
                            merged.push(pd);
                        }
                    }
                    allDevices = merged;
                }
            } catch {
                // Authorized device list is optional; fall back to public catalog.
            }
        }
        if (g === generation) {
            graphDevices.value = allDevices;
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
}
watch(() => auth.isAuthenticated, load, { immediate: true });
onBeforeUnmount(() => {
    generation++;
    controller?.abort();
});
</script>
