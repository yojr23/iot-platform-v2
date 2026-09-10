<template>
    <div class="lab-workspace">
        <div class="lab-toolbar">
            <div class="lab-title">
                <h1>Mi tablero</h1>
                <span>{{
                    selectedSensor?.deviceName || "Monitoreo de sensores"
                }}</span>
            </div>
            <div class="lab-actions">
                <span
                    v-if="auth.isAuthenticated"
                    class="lab-save-state"
                    role="status"
                    >{{ saveLabels[saveState] }}</span
                ><button
                    class="lab-button"
                    :disabled="
                        auth.isAuthenticated &&
                        (!dirty || saveState === 'saving')
                    "
                    @click="saveClick"
                >
                    <I name="save" /><span>Guardar</span></button
                ><button
                    class="lab-button primary"
                    :disabled="!catalog.length"
                    @click="openAdd"
                >
                    <I name="plus" /><span>Agregar gráfica</span>
                </button>
            </div>
        </div>
        <div v-if="auth.isAuthenticated" class="lab-summary-row">
            <div
                v-for="c in summaryCards"
                :key="c.key"
                class="lab-card lab-summary-card"
            >
                <span class="lab-summary-label">{{ c.label }}</span>
                <strong class="lab-spark-value">{{
                    c.value ?? (metricsLoading ? "…" : "—")
                }}</strong>
            </div>
        </div>
        <p v-if="metricsError" role="alert" class="lab-notice">
            {{ metricsError }}
        </p>
        <p v-if="message" role="alert" class="lab-notice">{{ message }}</p>
        <div class="lab-workspace-grid">
            <div class="lab-primary">
                <RouterLink
                    v-if="auth.isAuthenticated && critical"
                    :to="`/alerts/${critical.id}`"
                    class="lab-critical"
                    ><I name="alert" /><strong>{{
                        critical.alert_rule?.message || "Alerta crítica"
                    }}</strong
                    ><span>{{ critical.device?.name }}</span
                    ><span class="lab-critical-time">{{
                        time(critical.created_at)
                    }}</span
                    ><I name="arrow"
                /></RouterLink>
                <div v-else-if="!auth.isAuthenticated" class="lab-public-note">
                    <span class="lab-dot" /> Explora las gráficas públicas, sin
                    registro.<span class="lab-note-right"
                        >Tu tablero es temporal</span
                    >
                </div>
                <article v-if="selected" class="lab-card lab-main-chart">
                    <div class="lab-chart-selectors">
                        <label
                            ><span class="visually-hidden">Dispositivo</span
                            ><select
                                :value="selected.device_id"
                                @change="changeDevice($event.target.value)"
                            >
                                <option
                                    v-for="d in devices"
                                    :value="d.id"
                                    :key="d.id"
                                >
                                    {{ d.name }}
                                </option>
                            </select></label
                        ><label
                            ><span class="visually-hidden">Sensor</span
                            ><select
                                :value="selected.sensor_id"
                                @change="changeSensor($event.target.value)"
                            >
                                <option
                                    v-for="s in catalog.filter(
                                        (s) =>
                                            s.device_id === selected.device_id,
                                    )"
                                    :value="s.id"
                                    :key="s.id"
                                >
                                    {{ s.name }}
                                </option>
                            </select></label
                        ><span class="lab-chart-caption">{{
                            selectedSensor?.unit
                        }}</span>
                    </div>
                    <div class="lab-reading-row">
                        <div class="lab-reading">
                            <span>{{ number(latest?.value) }}</span
                            ><span class="lab-unit">{{
                                selectedSensor?.unit
                            }}</span>
                        </div>
                        <div class="lab-connection">
                            <span
                                :class="[
                                    'lab-connection-label',
                                    { connected: connection === 'connected' },
                                ]"
                                ><span class="lab-dot" />{{
                                    connection === "connected"
                                        ? "Transporte conectado"
                                        : "Sin conexión en vivo"
                                }}</span
                            ><small
                                >Último dato ·
                                {{ time(latest?.reading_time) }}</small
                            >
                        </div>
                    </div>
                    <div class="lab-chart-tools">
                        <span
                            >{{ selectedSensor?.name }}
                            <span class="lab-muted"
                                >({{ selectedSensor?.unit }})</span
                            ></span
                        >
                        <div
                            class="lab-ranges"
                            role="group"
                            aria-label="Rango de tiempo"
                        >
                            <button
                                v-for="(_, r) in ranges"
                                :key="r"
                                :aria-pressed="selected.range === r"
                                :class="{ active: selected.range === r }"
                                @click="changeRange(r)"
                            >
                                {{ r }}
                            </button>
                        </div>
                    </div>
                    <div class="lab-realtime-row">
                        <button
                            type="button"
                            class="lab-button lab-realtime-toggle"
                            :class="{ active: realtimeEnabled }"
                            :aria-pressed="realtimeEnabled"
                            @click="toggleRealtime"
                        >
                            <span
                                class="lab-dot"
                                :class="{ off: !realtimeEnabled }"
                                aria-hidden="true"
                            /><span>{{
                                realtimeEnabled ? "Tiempo real" : "Pausado"
                            }}</span>
                        </button>
                    </div>
                    <SensorReadingChart
                        v-bind="displayViewModel"
                        :loading="history[selected.id]?.loading"
                        :error="activeError"
                    />
                    <button
                        v-if="activeError"
                        class="lab-button"
                        @click="load(selected)"
                    >
                        Reintentar
                    </button>
                    <details class="lab-readings-table">
                        <summary>
                            Consultar últimas lecturas <I name="down" />
                        </summary>
                        <table>
                            <thead>
                                <tr>
                                    <th>Hora (UTC)</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="p in activeData.points
                                        .slice(-8)
                                        .reverse()"
                                    :key="p.id"
                                >
                                    <td>{{ time(p.reading_time) }}</td>
                                    <td>
                                        {{ number(p.value) }}
                                        {{ selectedSensor?.unit }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </details>
                </article>
                <div v-else class="lab-card lab-empty">
                    <I name="chart" />
                    <h2>Tu próximo dato empieza aquí</h2>
                    <p>
                        {{
                            catalog.length
                                ? "Agrega una gráfica para comenzar a monitorear."
                                : "No hay sensores públicos disponibles."
                        }}
                    </p>
                    <button
                        v-if="catalog.length"
                        class="lab-button primary"
                        @click="openAdd"
                    >
                        Agregar gráfica
                    </button>
                </div>
                <div class="lab-spark-grid">
                    <button
                        v-for="w in secondary"
                        :key="w.id"
                        class="lab-card lab-spark"
                        @click="select(w.id)"
                    >
                        <div class="lab-spark-title">
                            <span class="lab-sensor-icon"
                                ><I :name="icon(sensor(w)?.name)"
                            /></span>
                            <div>
                                <strong>{{ sensor(w)?.name }}</strong
                                ><small>{{ sensor(w)?.deviceName }}</small>
                            </div>
                            <I name="right" />
                        </div>
                        <div class="lab-spark-value">
                            {{ number(points(w).points.at(-1)?.value) }}
                            <small>{{ sensor(w)?.unit }}</small>
                        </div>
                        <svg
                            viewBox="0 0 280 45"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                        >
                            <path
                                :d="spark(w)"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />
                        </svg>
                    </button>
                </div>
                <section
                    v-if="auth.isAuthenticated"
                    class="lab-card lab-events"
                >
                    <div class="lab-card-heading">
                        <h2>Alertas recientes</h2>
                        <RouterLink to="/alerts"
                            >Ver historial <I name="arrow"
                        /></RouterLink>
                    </div>
                    <p v-if="!alerts.activeAlerts.length" class="lab-muted">
                        No hay alertas activas.
                    </p>
                    <div v-else class="lab-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha y hora</th>
                                    <th>Evento</th>
                                    <th>Dispositivo</th>
                                    <th>Severidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="a in alerts.activeAlerts.slice(0, 4)"
                                    :key="a.id"
                                >
                                    <td>{{ time(a.created_at) }}</td>
                                    <td>
                                        <RouterLink :to="`/alerts/${a.id}`">{{
                                            a.alert_rule?.message || a.message
                                        }}</RouterLink>
                                    </td>
                                    <td>{{ a.device?.name }}</td>
                                    <td>
                                        <span
                                            :class="[
                                                'lab-severity',
                                                a.alert_rule?.severity,
                                            ]"
                                            >{{
                                                a.alert_rule?.severity ===
                                                "danger"
                                                    ? "Crítica"
                                                    : "Advertencia"
                                            }}</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <aside class="lab-inspector">
                <section id="my-charts" class="lab-card lab-chart-list">
                    <div class="lab-card-heading">
                        <h2>
                            Mis gráficas <span>({{ widgets.length }})</span>
                        </h2>
                        <button
                            class="lab-text-button"
                            @click="editing = !editing"
                        >
                            {{ editing ? "Listo" : "Editar" }}
                        </button>
                    </div>
                    <div
                        v-for="(w, i) in widgets"
                        :key="w.id"
                        class="lab-list-item"
                        :class="{ selected: w.id === selectedId }"
                        :draggable="editing"
                        @dragstart="dragged = w.id"
                        @dragover.prevent
                        @drop.prevent="drop(w.id)"
                    >
                        <button
                            class="lab-list-select"
                            :aria-pressed="w.id === selectedId"
                            @click="select(w.id)"
                        >
                            <I
                                :name="editing ? 'grip' : icon(sensor(w)?.name)"
                            /><span
                                ><strong>{{ sensor(w)?.name }}</strong
                                ><small>{{
                                    sensor(w)?.deviceName
                                }}</small></span
                            ><b
                                >{{ number(points(w).points.at(-1)?.value)
                                }}<small>{{ sensor(w)?.unit }}</small></b
                            >
                        </button>
                        <div v-if="editing" class="lab-edit-actions">
                            <button
                                :disabled="i === 0"
                                :aria-label="`Subir ${sensor(w)?.name}`"
                                @click="move(w.id, -1)"
                            >
                                <I name="up" /></button
                            ><button
                                :disabled="i === widgets.length - 1"
                                :aria-label="`Bajar ${sensor(w)?.name}`"
                                @click="move(w.id, 1)"
                            >
                                <I name="down" /></button
                            ><button
                                :aria-label="`Quitar ${sensor(w)?.name}`"
                                @click="remove(w.id)"
                            >
                                <I name="trash" />
                            </button>
                        </div>
                    </div>
                    <button
                        class="lab-add-row"
                        :disabled="!catalog.length"
                        @click="openAdd"
                    >
                        <I name="plus" /> Agregar gráfica
                    </button>
                    <p v-if="removed" class="lab-undo" role="status">
                        Gráfica quitada. <button @click="undo">Deshacer</button>
                    </p>
                </section>
                <details
                    v-if="selected"
                    class="lab-card lab-details"
                    :open="wide"
                >
                    <summary>Detalle del sensor <I name="down" /></summary>
                    <dl>
                        <div>
                            <dt>Dispositivo</dt>
                            <dd>{{ selectedSensor?.deviceName }}</dd>
                        </div>
                        <div>
                            <dt>Sensor</dt>
                            <dd>{{ selectedSensor?.name }}</dd>
                        </div>
                        <div>
                            <dt>Unidad</dt>
                            <dd>{{ selectedSensor?.unit }}</dd>
                        </div>
                        <div>
                            <dt>Rango mostrado</dt>
                            <dd>{{ selected.range }}</dd>
                        </div>
                        <div>
                            <dt>Muestras devueltas</dt>
                            <dd>{{ viewModel.stats?.count || 0 }}</dd>
                        </div>
                        <div>
                            <dt>Último dato</dt>
                            <dd>{{ time(latest?.reading_time) }}</dd>
                        </div>
                        <div>
                            <dt>Zona horaria</dt>
                            <dd>UTC</dd>
                        </div>
                    </dl>
                    <p>Estadísticas del período seleccionado.</p>
                </details>
                <section
                    v-if="auth.isAuthenticated"
                    class="lab-card lab-active-alerts"
                >
                    <div class="lab-card-heading">
                        <h2>
                            Alertas activas
                            <span>({{ alerts.unresolvedCount }})</span>
                        </h2>
                        <RouterLink to="/alerts"
                            ><I name="arrow" /><span class="visually-hidden"
                                >Ver alertas</span
                            ></RouterLink
                        >
                    </div>
                    <p v-if="alerts.error" role="alert">{{ alerts.error }}</p>
                    <p
                        v-else-if="!alerts.activeAlerts.length"
                        class="lab-muted"
                    >
                        Sin alertas activas.
                    </p>
                    <RouterLink
                        v-for="a in alerts.activeAlerts.slice(0, 3)"
                        :key="a.id"
                        :to="`/alerts/${a.id}`"
                        class="lab-alert-item"
                        ><span
                            :class="['lab-alert-icon', a.alert_rule?.severity]"
                            ><I name="alert"
                        /></span>
                        <div>
                            <strong>{{ a.device?.name }}</strong>
                            <p>{{ a.alert_rule?.message || a.message }}</p>
                            <small>{{ time(a.created_at) }} UTC</small>
                        </div>
                        <I name="right"
                    /></RouterLink>
                </section>
                <section v-else class="lab-card lab-access">
                    <span class="lab-access-icon"><I name="lock" /></span>
                    <h2>Tu espacio de trabajo</h2>
                    <p>
                        Inicia sesión para guardar tu tablero y consultar las
                        alertas autorizadas.
                    </p>
                    <RouterLink to="/login" class="lab-button"
                        >Iniciar sesión <I name="arrow" /></RouterLink
                    ><small>Las gráficas públicas siguen disponibles.</small>
                </section>
            </aside>
        </div>
        <dialog ref="dialog" class="lab-dialog" @click="onBackdrop">
            <form @submit.prevent="confirmAdd">
                <div class="lab-card-heading">
                    <h2>Agregar gráfica</h2>
                    <button
                        type="button"
                        class="lab-icon-button"
                        aria-label="Cerrar"
                        @click="dialog.close()"
                    >
                        <I name="close" />
                    </button>
                </div>
                <p>Elige una señal para tu tablero.</p>
                <label
                    >Dispositivo<select v-model="newDevice">
                        <option
                            v-for="d in devices"
                            :key="d.id"
                            :value="String(d.id)"
                        >
                            {{ d.name }}
                        </option>
                    </select></label
                ><label
                    >Sensor<select v-model="newSensor" required>
                        <option
                            v-for="s in catalog.filter(
                                (s) => s.device_id === newDevice,
                            )"
                            :key="s.id"
                            :value="s.id"
                        >
                            {{ s.name }} · {{ s.unit }}
                        </option>
                    </select></label
                >
                <p class="lab-muted">
                    Si ya existe, se seleccionará su gráfica.
                </p>
                <div class="lab-dialog-actions">
                    <button
                        type="button"
                        class="lab-button"
                        @click="dialog.close()"
                    >
                        Cancelar</button
                    ><button class="lab-button primary">Agregar gráfica</button>
                </div>
            </form>
        </dialog>
    </div>
</template>
<script setup>
import { computed, onBeforeUnmount, onMounted, ref, toRef, watch } from "vue";
import { useRouter, onBeforeRouteLeave } from "vue-router";
import I from "./lab/LabIcon.vue";
import SensorReadingChart from "@/components/charts/SensorReadingChart.vue";
import { useLabWorkspace, ranges } from "@/composables/useLabWorkspace";
import { useAlertsStore } from "@/stores/alerts";
import { getDashboardMetrics } from "@/api/dashboard";
const props = defineProps({ devices: { type: Array, default: () => [] } });
const router = useRouter(),
    alerts = useAlertsStore();
const {
    auth,
    widgets,
    selectedId,
    selected,
    editing,
    saveState,
    message,
    removed,
    connection,
    catalog,
    sensor,
    points,
    viewModel,
    activeData,
    activeError,
    history,
    dirty,
    add,
    select,
    changeDevice,
    changeSensor,
    changeRange,
    remove,
    undo,
    move,
    save,
    load,
} = useLabWorkspace(toRef(props, "devices"));
const selectedSensor = computed(() => sensor(selected.value)),
    latest = computed(() =>
        activeData.value.points.filter((p) => p.value !== null).at(-1),
    ),
    secondary = computed(() =>
        widgets.value.filter((w) => w.id !== selectedId.value).slice(0, 3),
    );
const critical = computed(() =>
    alerts.activeAlerts.find((a) => a.alert_rule?.severity === "danger"),
);
// C1: summary cards. Authenticated users get the authoritative system-wide
// counts from GET /dashboard/metrics (mount-time snapshot, no polling refresh
// by design). Alertas activas stays wired to the alerts store instead so it
// keeps moving as realtime events land, rather than going stale between
// snapshots. Public visitors have no metrics endpoint access, so device
// counts fall back to the already-loaded public devices prop.
const metrics = ref(null),
    metricsLoading = ref(false),
    metricsError = ref("");
async function loadMetrics() {
    if (!auth.isAuthenticated) return;
    metricsLoading.value = true;
    metricsError.value = "";
    try {
        metrics.value = (await getDashboardMetrics()).data;
    } catch {
        metricsError.value = "No se pudieron cargar las métricas del panel.";
    } finally {
        metricsLoading.value = false;
    }
}
onMounted(loadMetrics);
const summaryCards = computed(() => {
    // Guest product boundary (frozen): guest = public graph monitoring only, NOT global
    // operational metrics. The public graph bootstrap deliberately omits device.status, so
    // operational cards here would both leak product semantics and be wrong (status undefined
    // → "activos = 0"). Operational summary is authenticated-only, from /dashboard/metrics.
    if (!auth.isAuthenticated) {
        return [];
    }
    return [
        {
            key: "total",
            label: "Dispositivos totales",
            value: metrics.value?.total_devices,
        },
        {
            key: "active",
            label: "Dispositivos activos",
            value: metrics.value?.active_devices,
        },
        {
            key: "alerts",
            label: "Alertas activas",
            value: alerts.unresolvedCount,
        },
    ];
});
// C2: pause/resume toggle. Component-local buffer in front of the chart —
// the store/composable keep merging live readings as always, this just
// stops handing new snapshots to the chart while paused, then catches up
// immediately on resume. Switching sensor/range is a deliberate action, not
// a live event, so it always refreshes the display even while paused.
const realtimeEnabled = ref(true);
const displayViewModel = ref(viewModel.value);
watch(viewModel, (v) => {
    if (realtimeEnabled.value) displayViewModel.value = v;
});
watch(
    () => selected.value?.id,
    () => {
        displayViewModel.value = viewModel.value;
    },
);
function toggleRealtime() {
    realtimeEnabled.value = !realtimeEnabled.value;
    if (realtimeEnabled.value) displayViewModel.value = viewModel.value;
}
const number = (v) =>
    v == null
        ? "—"
        : new Intl.NumberFormat("es-CO", { maximumFractionDigits: 2 }).format(
              v,
          );
const time = (v) =>
    v
        ? new Date(v).toLocaleTimeString("es-CO", {
              hour: "2-digit",
              minute: "2-digit",
              second: "2-digit",
              hour12: false,
              timeZone: "UTC",
          })
        : "Sin dato";
const icon = (name) =>
    /temper/i.test(name)
        ? "temp"
        : /hum/i.test(name)
          ? "drop"
          : /pres/i.test(name)
            ? "pressure"
            : "chart";
const saveLabels = {
    clean: "",
    dirty: "Cambios sin guardar",
    saving: "Guardando…",
    saved: "Guardado",
    error: "Error al guardar",
};
const dialog = ref(null),
    newDevice = ref(""),
    newSensor = ref(""),
    dragged = ref("");
const wide = ref(window.innerWidth >= 992);
watch(
    newDevice,
    (d) =>
        (newSensor.value =
            catalog.value.find((s) => s.device_id === d)?.id || ""),
);
function openAdd() {
    newDevice.value = String(props.devices[0]?.id || "");
    newSensor.value =
        catalog.value.find((s) => s.device_id === newDevice.value)?.id || "";
    dialog.value.showModal();
}
function confirmAdd() {
    add(newSensor.value);
    dialog.value.close();
}
function onBackdrop(e) {
    if (e.target === dialog.value) {
        const r = dialog.value.getBoundingClientRect();
        if (
            e.clientX < r.left ||
            e.clientX > r.right ||
            e.clientY < r.top ||
            e.clientY > r.bottom
        )
            dialog.value.close();
    }
}
function drop(id) {
    const a = widgets.value.findIndex((w) => w.id === dragged.value),
        b = widgets.value.findIndex((w) => w.id === id);
    if (a >= 0) move(dragged.value, b - a);
    dragged.value = "";
}
function spark(w) {
    const a = points(w)
        .points.filter((p) => p.value !== null)
        .slice(-60);
    const min = Math.min(...a.map((p) => p.value)),
        max = Math.max(...a.map((p) => p.value));
    return a
        .map(
            (p, i) =>
                `${i ? "L" : "M"}${(i * 280) / Math.max(a.length - 1, 1)},${40 - ((p.value - min) / (max - min || 1)) * 32}`,
        )
        .join(" ");
}
async function saveClick() {
    if (auth.isAuthenticated) await save();
    else
        await router.push({ name: "login", query: { redirect: "/dashboard" } });
}
// Native route confirmation is deliberately limited to discarding a draft; saving is explicit.
onBeforeRouteLeave(
    () =>
        !auth.isAuthenticated ||
        !dirty.value ||
        window.confirm("Tienes cambios sin guardar. ¿Salir y descartarlos?"),
);
</script>
<style scoped>
/* Kept local to this component (see file-scope note above) — lab-blue.css
   already covers .lab-card/.lab-spark-value, this only adds the bits it
   doesn't have yet: a small responsive grid and the paused dot state. */
.lab-summary-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.lab-summary-card {
    padding: 14px;
}
.lab-summary-label {
    display: block;
    font-size: 11px;
    color: var(--sinoa-text-muted);
    margin-bottom: 6px;
}
.lab-realtime-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 10px;
}
.lab-realtime-toggle {
    min-height: 44px;
}
.lab-realtime-toggle .lab-dot.off {
    background: #94a3b8;
}
</style>
