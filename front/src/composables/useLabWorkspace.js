import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useSensorReadingsStore } from "@/stores/sensorReadings";
import { useGraphSeriesQueryStore } from "@/stores/graphSeriesQuery";
import { useSensorRealtime } from "@/realtime/useSensorRealtime";
import { onConnectionStateChange } from "@/realtime/echo";
import {
    composeGraphSeries,
    computeStats,
} from "@/components/charts/graphSeriesProjection";
import { buildZonesViewModel } from "@/components/charts/graphZonesProjection";
import { buildSensorChartViewModel } from "@/components/charts/sensorChartViewModel";
import {
    getDashboardPreferences,
    updateDashboardPreferences,
} from "@/api/dashboard";
export const ranges = {
    "1m": 60000,
    "5m": 300000,
    "1h": 3600000,
    "6h": 21600000,
    "24h": 86400000,
};
export function useLabWorkspace(devices) {
    const auth = useAuthStore(),
        live = useSensorReadingsStore(),
        queries = useGraphSeriesQueryStore();
    const widgets = ref([]),
        selectedId = ref(""),
        editing = ref(false),
        saveState = ref("clean"),
        message = ref(""),
        removed = ref(null),
        connection = ref("disconnected");
    const history = ref({});
    const baseline = ref("");
    let alive = true;
    const handles = new Map();
    const stopConnection = onConnectionStateChange(
        (s) => (connection.value = s),
        { immediate: true },
    );
    const catalog = computed(() =>
        devices.value.flatMap((d) =>
            (d.sensors || []).map((s) => ({
                ...s,
                device_id: String(d.id),
                deviceName: d.name,
                id: String(s.id),
            })),
        ),
    );
    const selected = computed(
        () => widgets.value.find((w) => w.id === selectedId.value) || null,
    );
    const sensor = (w) =>
        catalog.value.find(
            (s) =>
                s.id === String(w?.sensor_id) &&
                s.device_id === String(w?.device_id),
        );
    const snapshot = () =>
        JSON.stringify({
            main: widgets.value[0] || { device_id: null, sensor_id: null },
            monitors: widgets.value.slice(1),
            selected_id: selectedId.value,
        });
    const snapshotForLayout = (layout) => {
        const candidates = [
            { id: "main", ...(layout?.main || {}) },
            ...(layout?.monitors || []),
        ];
        const normalized = candidates
            .filter((w) => w.id)
            .map((w) => ({
                ...w,
                id: String(w.id),
                device_id: w.device_id == null ? null : String(w.device_id),
                sensor_id: w.sensor_id == null ? null : String(w.sensor_id),
                range: ranges[w.range] ? w.range : "5m",
            }));
        return JSON.stringify({
            main: normalized[0] || { device_id: null, sensor_id: null },
            monitors: normalized.slice(1),
            selected_id:
                normalized.find((w) => w.id === String(layout?.selected_id))?.id ||
                normalized[0]?.id ||
                "",
        });
    };
    const dirty = computed(() => snapshot() !== baseline.value);
    function mark() {
        if (saveState.value !== "saving")
            saveState.value = dirty.value ? "dirty" : "clean";
    }
    async function load(w) {
        if (!w?.sensor_id) return;
        const id = w.id,
            sid = w.sensor_id,
            range = w.range || "5m",
            to = new Date(),
            from = new Date(to - ranges[range]);
        const descriptor = {
            // Authenticated users hit the private sensor-series endpoint so restricted
            // (public_monitoring_enabled=false) sensors return history instead of 404; guests stay
            // on the anonymous public route.
            authorizationScope: auth.isAuthenticated ? "private" : "public",
            sensorId: sid,
            from,
            to,
            aggregation: "raw",
        };
        const requestId = crypto.randomUUID();
        history.value[id] = { descriptor, requestId, loading: true };
        await queries.fetchWindow(sid, {
            ...descriptor,
            consumerKey: `lab-${id}`,
        });
        if (!alive || history.value[id]?.requestId !== requestId) return;
        history.value[id] = { descriptor, loading: false };
    }
    function subscribe(w) {
        if (handles.get(w.id)?.sensorId === w.sensor_id) return;
        handles.get(w.id)?.realtime.unsubscribeSensor();
        const rt = useSensorRealtime(w.sensor_id, (r) =>
            live.mergeReading(w.sensor_id, r),
        );
        handles.set(w.id, { sensorId: w.sensor_id, realtime: rt });
        rt.subscribeSensor();
    }
    function points(w) {
        if (!w) return { points: [], stats: null };
        const entry = history.value[w.id];
        const h = entry ? queries.resultForQuery(entry.descriptor) : null;
        const all = composeGraphSeries({
            historicalPoints: h?.points || [],
            liveReadings: live.readingsFor(w.sensor_id),
            serverStats: h?.stats,
            partial: h?.truncated,
        });
        const latest = all.points.at(-1);
        const end = Math.max(
            entry?.descriptor.to.getTime() || 0,
            latest ? Date.parse(latest.reading_time) + 1000 : 0,
        );
        const windowPoints = all.points.filter(
            (p) =>
                Date.parse(p.reading_time) >= end - ranges[w.range || "5m"] &&
                Date.parse(p.reading_time) < end,
        );
        return {
            ...all,
            points: windowPoints,
            stats: h?.truncated ? h.stats : computeStats(windowPoints),
        };
    }
    // Full zoned chart view-model for ANY widget (not just the selected one), so the dashboard can
    // render several charts big in parallel. Reuses the same projection + zone builder as before;
    // `viewModel`/`activeError` below are just the selected-widget specialization of these.
    // docs/implementation/graph-semantic-zones-plan.md — server-owned bands (public bootstrap
    // projection of RuleToGraphZones) normalized once here so SensorReadingChart.vue only maps an
    // already-resolved view-model to pixels.
    function widgetViewModel(w) {
        const data = points(w);
        return {
            ...buildSensorChartViewModel([...data.points].reverse(), {
                unit: sensor(w)?.unit || "",
            }),
            labels: data.points.map(p => new Intl.DateTimeFormat("es-CO", {dateStyle: "medium", timeStyle: "medium", timeZone: "UTC", hour12: false}).format(new Date(p.reading_time)) + " UTC"),
            stats: data.stats,
            partial: data.partial,
            zones: buildZonesViewModel({
                zones: sensor(w)?.bands,
                boundaries: sensor(w)?.boundaries,
            }),
        };
    }
    function widgetError(w) {
        const h = w ? history.value[w.id] : null;
        return h ? queries.resultForQuery(h.descriptor)?.error : "";
    }
    const activeData = computed(() => points(selected.value));
    const viewModel = computed(() => widgetViewModel(selected.value));
    const activeError = computed(() => widgetError(selected.value));
    function add(sensorId) {
        const s = catalog.value.find((s) => s.id === String(sensorId));
        if (!s) return;
        const existing = widgets.value.find((w) => w.sensor_id === s.id);
        if (existing) {
            selectedId.value = existing.id;
            return;
        }
        const w = {
            id: crypto.randomUUID(),
            device_id: s.device_id,
            sensor_id: s.id,
            range: "5m",
        };
        widgets.value.push(w);
        selectedId.value = w.id;
        subscribe(w);
        load(w);
        mark();
    }
    function changeDevice(id) {
        const s = catalog.value.find((s) => s.device_id === String(id));
        if (s) changeSensor(s.id);
    }
    function changeSensor(id) {
        const s = catalog.value.find((s) => s.id === String(id)),
            w = selected.value;
        if (!s || !w) return;
        w.sensor_id = s.id;
        w.device_id = s.device_id;
        subscribe(w);
        load(w);
        mark();
    }
    function changeRange(range) {
        if (selected.value && ranges[range]) {
            selected.value.range = range;
            load(selected.value);
            mark();
        }
    }
    function select(id) {
        selectedId.value = id;
        mark();
    }
    function remove(id) {
        const index = widgets.value.findIndex((w) => w.id === id);
        if (index < 0) return;
        removed.value = { widget: widgets.value[index], index };
        handles.get(id)?.realtime.unsubscribeSensor();
        handles.delete(id);
        delete history.value[id];
        widgets.value.splice(index, 1);
        if (selectedId.value === id)
            selectedId.value =
                widgets.value[Math.min(index, widgets.value.length - 1)]?.id ||
                "";
        mark();
    }
    function undo() {
        if (!removed.value) return;
        const { widget, index } = removed.value;
        widgets.value.splice(index, 0, widget);
        selectedId.value = widget.id;
        removed.value = null;
        subscribe(widget);
        load(widget);
        mark();
    }
    function move(id, by) {
        const i = widgets.value.findIndex((w) => w.id === id),
            j = i + by;
        if (i < 0 || j < 0 || j >= widgets.value.length) return;
        widgets.value.splice(j, 0, ...widgets.value.splice(i, 1));
        mark();
    }
    async function save() {
        if (!auth.isAuthenticated || saveState.value === "saving") return;
        const sent = snapshot();
        saveState.value = "saving";
        message.value = "";
        try {
            const response = await updateDashboardPreferences({ layout: JSON.parse(sent) });
            if (!alive) return;
            const confirmed = response?.data?.layout;
            if (!confirmed) {
                throw new Error("Dashboard preferences response did not include a confirmed layout.");
            }
            const confirmedSnapshot = snapshotForLayout(confirmed);
            baseline.value = confirmedSnapshot;
            saveState.value = snapshot() === confirmedSnapshot ? "saved" : "dirty";
        } catch {
            if (alive) {
                saveState.value = "error";
                message.value =
                    "No se pudo guardar. Tus cambios siguen disponibles.";
            }
        }
    }
    async function init() {
        let layout = null;
        if (auth.isAuthenticated) {
            try {
                layout = (await getDashboardPreferences()).data?.layout;
            } catch {
                message.value = "No se pudo recuperar el tablero guardado.";
            }
        }
        if (!alive) return;
        const candidates = layout
            ? [{ id: "main", ...layout.main }, ...(layout.monitors || [])]
            : catalog.value
                  .slice(0, 4)
                  .map((s, i) => ({
                      id: i ? "chart-" + i : "main",
                      sensor_id: s.id,
                      device_id: s.device_id,
                  }));
        const nextWidgets = candidates
            .filter((w) => sensor(w))
            .map((w) => ({
                ...w,
                sensor_id: String(w.sensor_id),
                device_id: String(w.device_id),
                range: ranges[w.range] ? w.range : "5m",
            }));
        const nextById = new Map(nextWidgets.map((w) => [w.id, w]));
        handles.forEach((handle, id) => {
            const next = nextById.get(id);
            if (!next || String(next.sensor_id) !== String(handle.sensorId)) {
                handle.realtime.unsubscribeSensor();
                handles.delete(id);
                delete history.value[id];
            }
        });
        Object.keys(history.value).forEach((id) => {
            const next = nextById.get(id);
            if (!next || String(next.sensor_id) !== String(handles.get(id)?.sensorId))
                delete history.value[id];
        });
        widgets.value = nextWidgets;
        selectedId.value =
            widgets.value.find((w) => w.id === layout?.selected_id)?.id ||
            widgets.value[0]?.id ||
            "";
        baseline.value = snapshot();
        saveState.value = layout ? "saved" : "clean";
        widgets.value.forEach((w) => {
            subscribe(w);
            load(w);
        });
    }
    watch(() => devices.value, init, { immediate: true });
    function beforeUnload(e) {
        if (dirty.value) {
            e.preventDefault();
            e.returnValue = "";
        }
    }
    window.addEventListener("beforeunload", beforeUnload);
    onBeforeUnmount(() => {
        alive = false;
        handles.forEach((h) => h.realtime.unsubscribeSensor());
        stopConnection();
        window.removeEventListener("beforeunload", beforeUnload);
    });
    return {
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
        widgetViewModel,
        widgetError,
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
    };
}
