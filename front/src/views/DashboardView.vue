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
import { getAuthenticatedGraphCatalog, getGraphBootstrap } from "@/api/graph";
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

        // Authenticated users supplement the public bootstrap with the complete graph-only
        // catalog, which includes restricted sensors without loading the general device payload.
        let allDevices = publicDevices;
        if (auth.isAuthenticated) {
            try {
                const authResponse = await getAuthenticatedGraphCatalog({ signal: controller.signal });
                if (g === generation) {
                    const authDevices = unwrapData(authResponse)?.devices || [];
                    // Authenticated graph entries take precedence; retain a public entry only if
                    // it is absent from the authorized catalog during a transient rollout.
                    const authMap = new Map(authDevices.map((d) => [String(d.id), d]));
                    const merged = [...authDevices];
                    for (const pd of publicDevices) {
                        if (!authMap.has(String(pd.id))) {
                            merged.push(pd);
                        }
                    }
                    allDevices = merged;
                }
            } catch (e) {
                // Authorized device list is optional; fall back to public catalog.
                console.warn('[DashboardView] Authenticated graph catalog unavailable, using public only:', e?.message || e);
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
