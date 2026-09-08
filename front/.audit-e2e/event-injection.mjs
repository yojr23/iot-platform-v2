// Stage 0.7 (PLAN.md) — permanent event-injection harness.
//
// EVENT_HANDLER_SIMULATED, not WEBSOCKET_TRANSPORT_VERIFIED: this environment has no
// VITE_PUSHER_APP_KEY configured (front/.env), so front/src/realtime/echo.js#getEcho()
// returns null and the real channel.listen(...) binding in useAlertsRealtime.js /
// useSensorRealtime.js never happens. There is no local Pusher-compatible server here either
// (that is a later-stage/real-infrastructure concern per audit.md §21 "Real transport").
//
// What this DOES prove: the actual, unmodified front/src event-adapter code
// (useAlertsRealtime.js, useSensorRealtime.js, stores/alerts.js) — loaded via the project's
// own Vite config/aliases with Vite's SSR module loader, so it is the real module graph, not a
// hand-copied reimplementation — reacts correctly to a synthetic transport event. Only the
// Pusher/Echo transport itself is substituted (a "fake"/"substitute" Echo with .channel()/
// .listen()/.leaveChannel(), matching audit.md §21's own "in the substitute" methodology).
//
// Run: node .audit-e2e/event-injection.mjs
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import { createPinia, setActivePinia } from 'pinia';

import baseConfig from '../vite.config.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const results = [];
function record(name, pass, detail) {
  results.push({ name, pass, detail });
  const label = pass ? 'PASS' : 'FAIL';
  console.log(`[${label}] ${name}${detail ? ` — ${JSON.stringify(detail)}` : ''}`);
}

// A minimal in-memory substitute for laravel-echo/pusher-js. Exposes the same shape
// useAlertsRealtime.js / useSensorRealtime.js actually call (channel/listen/leaveChannel),
// plus __emit()/__reset() test hooks that are not part of the real Echo API.
const FAKE_ECHO_SRC = `
class FakeChannel {
  // event -> Set<callback>, matching real pusher-js: multiple .listen() calls for the same
  // event on the same channel all get invoked (needed to simulate Stage 5.1's ref-counted
  // registry, where two consumers can share one channel/event).
  constructor(name) { this.name = name; this._listeners = new Map(); }
  listen(event, cb) {
    if (!this._listeners.has(event)) this._listeners.set(event, new Set());
    this._listeners.get(event).add(cb);
    return this;
  }
  stopListening(event, cb) {
    if (!this._listeners.has(event)) return this;
    if (cb) this._listeners.get(event).delete(cb);
    else this._listeners.delete(event);
    return this;
  }
  emit(event, payload) {
    const callbacks = this._listeners.get(event);
    if (callbacks) callbacks.forEach((cb) => cb(payload));
  }
}

class FakeEcho {
  constructor() {
    this.channels = new Map();
    this.connector = { pusher: { connection: { bind() {} } } };
  }
  channel(name) {
    if (!this.channels.has(name)) this.channels.set(name, new FakeChannel(name));
    return this.channels.get(name);
  }
  leaveChannel(name) {
    // Mirrors real pusher-js: leaving a channel detaches its bound callbacks (the connection
    // stops routing events to it) even if some caller still holds a stale reference to the
    // old channel object.
    this.channels.get(name)?._listeners.clear();
    this.channels.delete(name);
  }
  disconnect() { this.channels.clear(); }
}

let instance = null;
export function getEcho() { if (!instance) instance = new FakeEcho(); return instance; }
export function createEcho() { return getEcho(); }
export function disconnectEcho() { instance = null; }
export function __reset() { instance = null; }

// Stage 5 added a shared connection-state/resync listener API on top of the real echo.js
// singleton (useAlertsRealtime.js / useSensorRealtime.js now import these). This sandbox has
// no real Pusher connection to bind to, so these are inert stubs that only need to exist so
// the destructured import doesn't throw — no test below asserts on connection-state/resync
// behavior (that remains EVENT_HANDLER_SIMULATED / untested here, real transport only).
export function onConnectionStateChange() { return () => {}; }
export function onResync() { return () => {}; }
`;

function fakeEchoPlugin() {
  const virtualId = 'virtual:fake-echo';
  return {
    name: 'audit-fake-echo-substitute',
    enforce: 'pre',
    resolveId(source, importer) {
      // Stage 5 added src/realtime/channelRegistry.js between the composables and echo.js;
      // match any importer under src/realtime/ (not just the two composable filenames) so
      // channelRegistry.js's `import { getEcho } from './echo'` resolves to the same fake
      // singleton the composables get, instead of silently falling through to the real
      // echo.js (which returns null here — no VITE_PUSHER_APP_KEY — and would desync the
      // two module instances).
      if (source === './echo' && importer && importer.includes('/realtime/')) {
        return virtualId;
      }
      if (source === virtualId) return virtualId;
      return null;
    },
    load(id) {
      if (id === virtualId) return FAKE_ECHO_SRC;
      return null;
    }
  };
}

async function main() {
  const resolvedBase = typeof baseConfig === 'function' ? baseConfig({ mode: 'development', command: 'serve' }) : baseConfig;
  const server = await createServer({
    ...resolvedBase,
    root,
    configFile: false,
    plugins: [fakeEchoPlugin(), ...(resolvedBase.plugins || [])],
    server: { middlewareMode: true },
    appType: 'custom',
    optimizeDeps: { noDiscovery: true }
  });

  async function load(relPath) {
    return server.ssrLoadModule(relPath);
  }

  try {
    const fakeEcho = await load('virtual:fake-echo');

    // ---- Alerts: create / dedup / resolution / malformed / channel teardown ----
    setActivePinia(createPinia());
    const alertsStoreMod = await load('/src/stores/alerts.js');
    const alertsRealtimeMod = await load('/src/realtime/useAlertsRealtime.js');

    const alertsStore = alertsStoreMod.useAlertsStore();
    const { subscribeAlerts, unsubscribeAlerts } = alertsRealtimeMod.useAlertsRealtime();

    const subscribed = subscribeAlerts();
    record('alerts: subscribeAlerts() binds without a real Pusher connection', subscribed === true);

    const echo = fakeEcho.getEcho();
    const alertsChannel = echo.channel('alerts');

    alertsChannel.emit('NewAlertTriggered', { alert: { id: 501, message: 'Alerta 1', severity: 'danger', sensor_name: 'S1' } });
    record(
      'alerts: creation adds to activeAlerts + sets latestAlert',
      alertsStore.activeAlerts.some((a) => Number(a.id) === 501) && alertsStore.latestAlert?.id === 501,
      { activeCount: alertsStore.activeAlerts.length, latestAlertId: alertsStore.latestAlert?.id }
    );
    const countAfterFirst = alertsStore.unresolvedCount;

    // Duplicate delivery of the same event/id (at-least-once transport) must not double count.
    alertsChannel.emit('NewAlertTriggered', { alert: { id: 501, message: 'Alerta 1 (dup)', severity: 'danger', sensor_name: 'S1' } });
    record(
      'alerts: duplicate id is deduped (alertsStore.addRealtimeAlert wasKnown check — Stage 5 consolidated dedup into the store, no more transport-level seenAlertIds), unresolvedCount unchanged',
      alertsStore.unresolvedCount === countAfterFirst,
      { unresolvedCount: alertsStore.unresolvedCount }
    );

    alertsChannel.emit('NewAlertTriggered', {});
    record('alerts: malformed/missing-id payload does not throw and is ignored', true);

    // Known gap (audit.md §21 "resolution unhandled"): addRealtimeAlert has no branch that
    // removes an alert from activeAlerts or decrements unresolvedCount when resolved=true.
    // This re-confirms that gap still exists against the current HEAD, not just historically.
    const beforeResolve = alertsStore.unresolvedCount;
    alertsChannel.emit('NewAlertTriggered', { alert: { id: 501, message: 'Alerta 1', severity: 'danger', resolved: true } });
    const stillInActiveAfterResolve = alertsStore.activeAlerts.some((a) => Number(a.id) === 501);
    record(
      'alerts: KNOWN GAP — a resolved=true event for an already-active alert does not remove it from activeAlerts or decrement unresolvedCount (no alert.resolved handling exists yet; Stage 7 backlog item)',
      stillInActiveAfterResolve === true && alertsStore.unresolvedCount === beforeResolve,
      { stillInActiveAfterResolve, unresolvedCount: alertsStore.unresolvedCount }
    );

    unsubscribeAlerts();
    alertsChannel.emit('NewAlertTriggered', { alert: { id: 999, message: 'after teardown' } });
    record(
      'alerts: after unsubscribeAlerts(), the torn-down channel object no longer reaches the store',
      !alertsStore.activeAlerts.some((a) => Number(a.id) === 999)
    );

    const resubscribed = subscribeAlerts();
    const newAlertsChannel = echo.channel('alerts');
    newAlertsChannel.emit('NewAlertTriggered', { alert: { id: 501, message: 'Alerta 1 (again)' } });
    record(
      'alerts: resubscribe still dedups the same id from before teardown (store state survives channel teardown; latestAlert stays at its pre-teardown value, not overwritten by the "again" payload)',
      resubscribed === true && alertsStore.latestAlert?.id === 501 && alertsStore.latestAlert?.message !== 'Alerta 1 (again)',
      { latestAlertId: alertsStore.latestAlert?.id ?? null, latestAlertMessage: alertsStore.latestAlert?.message }
    );

    // ---- Sensor readings: creation, mismatched sensor id, malformed value ----
    const sensorRealtimeMod = await load('/src/realtime/useSensorRealtime.js');
    const receivedReadings = [];
    const { subscribeSensor, unsubscribeSensor } = sensorRealtimeMod.useSensorRealtime(42, (reading) => {
      receivedReadings.push(reading);
    });

    const sensorSubscribed = subscribeSensor();
    record('sensor: subscribeSensor() binds without a real Pusher connection', sensorSubscribed === true);

    const sensorChannel = echo.channel('sensor.42');
    sensorChannel.emit('NewSensorReading', { reading: { id: 1, sensor_id: 42, value: 21.5, reading_time: '2026-09-07T10:00:00Z' } });
    record('sensor: matching sensor_id reading reaches the onReading callback', receivedReadings.length === 1, receivedReadings[0]);

    sensorChannel.emit('NewSensorReading', { reading: { id: 2, sensor_id: 43, value: 99, reading_time: '2026-09-07T10:00:01Z' } });
    record('sensor: mismatched sensor_id is ignored, callback not called again', receivedReadings.length === 1);

    sensorChannel.emit('NewSensorReading', { reading: { id: 3, sensor_id: 42, value: 'not-a-number', reading_time: '2026-09-07T10:00:02Z' } });
    record(
      'sensor: malformed numeric value is still normalized+forwarded (useSensorRealtime.js does not itself validate Number.isFinite(value); consumer must)',
      receivedReadings.length === 2 && Number.isNaN(Number(receivedReadings[1]?.value))
    );

    unsubscribeSensor();
    sensorChannel.emit('NewSensorReading', { reading: { id: 4, sensor_id: 42, value: 10, reading_time: '2026-09-07T10:00:03Z' } });
    record('sensor: after unsubscribeSensor(), further events on the old channel do not reach the callback', receivedReadings.length === 2);

    // ---- PLAN.md Stage 5.1 acceptance test: "two monitors same sensor, remove one -> other
    // keeps updating" — the ref-counted channel registry (channelRegistry.js) must not let one
    // consumer's unsubscribe tear down the shared channel out from under a second consumer. ----
    const readingsA = [];
    const readingsB = [];
    const consumerA = sensorRealtimeMod.useSensorRealtime(77, (reading) => readingsA.push(reading));
    const consumerB = sensorRealtimeMod.useSensorRealtime(77, (reading) => readingsB.push(reading));
    consumerA.subscribeSensor();
    consumerB.subscribeSensor();

    const sharedChannel = echo.channel('sensor.77');
    sharedChannel.emit('NewSensorReading', { reading: { id: 10, sensor_id: 77, value: 1 } });
    record(
      'channel registry: two consumers subscribed to the same sensor channel both receive the same event',
      readingsA.length === 1 && readingsB.length === 1
    );

    consumerA.unsubscribeSensor();
    sharedChannel.emit('NewSensorReading', { reading: { id: 11, sensor_id: 77, value: 2 } });
    record(
      'channel registry: releasing ONE consumer keeps the channel alive for the other (ref-counted leave, not full teardown)',
      readingsA.length === 1 && readingsB.length === 2,
      { readingsA: readingsA.length, readingsB: readingsB.length }
    );

    consumerB.unsubscribeSensor();
    sharedChannel.emit('NewSensorReading', { reading: { id: 12, sensor_id: 77, value: 3 } });
    record(
      'channel registry: the LAST consumer releasing does tear down the channel (no dangling listener)',
      readingsA.length === 1 && readingsB.length === 2
    );

    // ---- Device status: no frontend event-adapter exists yet ----
    record(
      'device.status.changed: NOT_APPLICABLE — no useDeviceRealtime.js/composable exists in front/src/realtime (confirmed via source search); audit.md §8/RC2 already documents DeviceStatusUpdated as unimplemented end-to-end. Nothing to inject into on the frontend yet (Stage 8.1).',
      true
    );
  } finally {
    await server.close();
  }

  const failed = results.filter((r) => !r.pass);
  console.log(`\n${results.length - failed.length}/${results.length} EVENT_HANDLER_SIMULATED checks passed.`);
  if (failed.length > 0) {
    console.log('Failing checks (some are EXPECTED known-gap assertions written to fail if the gap were fixed unexpectedly quietly — read names before treating as regressions):');
    failed.forEach((f) => console.log(`  - ${f.name}`));
  }

  const fs = await import('node:fs');
  fs.mkdirSync(path.join(__dirname, 'results'), { recursive: true });
  fs.writeFileSync(path.join(__dirname, 'results', 'event-injection.json'), JSON.stringify(results, null, 2));

  process.exit(failed.length > 0 ? 1 : 0);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
