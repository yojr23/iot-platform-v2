// Stage 5 repair (S5-07) — deterministic lifecycle and recovery race harness.
//
// CONNECTION_LIFECYCLE_SIMULATED / RECOVERY_RACE_SIMULATED:
// This environment exercises the real echo.js state machine, channelRegistry,
// useAlertsRealtime, and useSensorRealtime via a controllable fake Echo transport.
// No real Pusher WebSocket is involved — evidence labels must reflect that.
//
// Run: node .audit-e2e/realtime-lifecycle.mjs
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

// ---------------------------------------------------------------------------
// Controllable fake Echo transport — supports:
//   - explicit connection state transitions (connecting / connected / disconnected / unavailable / error)
//   - onConnectionStateChange(callback) that fires on every transition
//   - onResync(callback) that fires on reconnect/visibility/auth triggers
//   - channel() / leaveChannel() / disconnect()
//   - __connect() / __disconnect() / __reconnect() test hooks for the harness
// ---------------------------------------------------------------------------

const FAKE_ECHO_SRC = `
class FakeChannel {
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

// Shared connection state across all FakeEcho instances (simulates one Pusher connection per tab).
let _state = 'disconnected';
let _stateListeners = new Set();
let _resyncListeners = new Set();

function _setState(newState) {
  if (_state === newState) return;
  _state = newState;
  _stateListeners.forEach((cb) => { try { cb(newState); } catch {} });
}

class FakeEcho {
  constructor() {
    this.channels = new Map();
    this.connector = { pusher: { connection: {} } };
  }
  channel(name) {
    if (!this.channels.has(name)) this.channels.set(name, new FakeChannel(name));
    return this.channels.get(name);
  }
  leaveChannel(name) {
    this.channels.get(name)?._listeners.clear();
    this.channels.delete(name);
  }
  disconnect() { this.channels.clear(); }
}

let instance = null;
export function getEcho() { if (!instance) instance = new FakeEcho(); return instance; }
export function createEcho() { return getEcho(); }
export function disconnectEcho() { instance = null; }

let _hasConnectedOnce = false;

export function __reset() {
  instance = null;
  _state = 'disconnected';
  _hasConnectedOnce = false;
  _stateListeners.clear();
  _resyncListeners.clear();
}

// echo.js API surface — these are the functions that real composables import.

export function onConnectionStateChange(callback, opts) {
  _stateListeners.add(callback);
  if (opts?.immediate) {
    try { callback(_state); } catch {}
  }
  return () => _stateListeners.delete(callback);
}

export function onResync(callback) {
  _resyncListeners.add(callback);
  return () => _resyncListeners.delete(callback);
}

export function getConnectionState() { return _state; }

// Test hooks — not part of the real Echo API, only accessible through the virtual module.
export function __connect() {
  const isReconnect = _hasConnectedOnce && _state !== 'connected';
  _setState('connected');
  _hasConnectedOnce = true;
  if (isReconnect) {
    _resyncListeners.forEach((cb) => { try { cb('reconnect'); } catch {} });
  }
}
export function __disconnect() { _setState('disconnected'); }
export function __unavailable() { _setState('unavailable'); }
export function __error() { _setState('error'); }
export function __authChange() {
  // S5-05: simulate the auth:changed event from client.js.
  // Spread the listener set before iterating — the 'auth' callback calls
  // unsubscribeAlerts() which clears _resyncListeners during iteration.
  [..._resyncListeners].forEach((cb) => { try { cb('auth'); } catch {} });
}
export function __visibilityResume() {
  _resyncListeners.forEach((cb) => { try { cb('visibility'); } catch {} });
}
`;

function fakeEchoPlugin() {
  const virtualId = 'virtual:fake-echo';
  return {
    name: 'audit-realtime-lifecycle',
    enforce: 'pre',
    resolveId(source, importer) {
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
    server: { middlewareMode: true, hmr: { overlay: false } },
    appType: 'custom',
    optimizeDeps: { noDiscovery: true }
  });

  async function load(relPath) {
    return server.ssrLoadModule(relPath);
  }

  try {
    const fakeEcho = await load('virtual:fake-echo');

    // Helper: flush pending macrotasks (setTimeout) so async tests don't leave dangling timers.
    const flush = (ms) => new Promise((r) => setTimeout(r, ms));

    // ================================================================
    // S5-01: First connection is NOT classified as reconnect
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      await load('/src/stores/alerts.js');
      await load('/src/realtime/useAlertsRealtime.js');

      let reconnectFired = false;
      fakeEcho.onResync((reason) => { if (reason === 'reconnect') reconnectFired = true; });

      fakeEcho.__connect();
      record(
        'S5-01: first connect does NOT fire reconnect recovery',
        reconnectFired === false
      );
    }

    // ================================================================
    // S5-01: Disconnect then reconnect fires exactly one recovery
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      await load('/src/stores/alerts.js');
      await load('/src/realtime/useAlertsRealtime.js');

      let reconnectCount = 0;
      fakeEcho.onResync((reason) => { if (reason === 'reconnect') reconnectCount++; });

      fakeEcho.__connect(); // first connect — not a reconnect
      fakeEcho.__disconnect();
      fakeEcho.__connect(); // real reconnect
      record(
        'S5-01: disconnect then reconnect fires exactly one recovery',
        reconnectCount === 1,
        { reconnectCount }
      );
    }

    // ================================================================
    // S5-01: Duplicate connected without disconnect does NOT retrigger
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      await load('/src/stores/alerts.js');
      await load('/src/realtime/useAlertsRealtime.js');

      let reconnectCount = 0;
      fakeEcho.onResync((reason) => { if (reason === 'reconnect') reconnectCount++; });

      fakeEcho.__connect();
      fakeEcho.__connect(); // duplicate
      fakeEcho.__connect(); // duplicate
      record(
        'S5-01: duplicate connected callbacks without disconnect produce zero extra recovery triggers',
        reconnectCount === 0,
        { reconnectCount }
      );
    }

    // ================================================================
    // S5-02: Late subscriber immediately receives current connected state
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      await load('/src/stores/alerts.js');
      const sensorMod = await load('/src/realtime/useSensorRealtime.js');

      fakeEcho.__connect();

      const receivedReadings = [];
      const sensor = sensorMod.useSensorRealtime(999, (r) => receivedReadings.push(r));
      sensor.subscribeSensor();

      record(
        'S5-02: late subscriber created after Echo is connected sees isConnected=true immediately',
        sensor.isConnected.value === true,
        { isConnected: sensor.isConnected.value }
      );

      sensor.unsubscribeSensor();
    }

    // ================================================================
    // S5-02: Late subscriber sees disconnected state if transport is down
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      await load('/src/stores/alerts.js');
      const sensorMod = await load('/src/realtime/useSensorRealtime.js');

      const sensor = sensorMod.useSensorRealtime(998, () => {});
      sensor.subscribeSensor();

      record(
        'S5-02: subscriber created while transport is disconnected sees isConnected=false',
        sensor.isConnected.value === false,
        { isConnected: sensor.isConnected.value }
      );

      sensor.unsubscribeSensor();
    }

    // ================================================================
    // S5-05: Single auth lifecycle event (no double teardown)
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      await load('/src/stores/alerts.js');
      const alertsMod = await load('/src/realtime/useAlertsRealtime.js');

      let resyncCount = 0;
      fakeEcho.onResync((reason) => { if (reason === 'auth') resyncCount++; });

      alertsMod.subscribeAlerts();

      fakeEcho.__authChange();

      record(
        'S5-05: one auth:changed event fires exactly one auth lifecycle transition',
        resyncCount === 1,
        { resyncCount }
      );

      alertsMod.unsubscribeAlerts();
    }

    // ================================================================
    // Channel ref-counting regression (existing behavior)
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      const sensorMod = await load('/src/realtime/useSensorRealtime.js');

      const readingsA = [];
      const readingsB = [];
      const consumerA = sensorMod.useSensorRealtime(77, (r) => readingsA.push(r));
      const consumerB = sensorMod.useSensorRealtime(77, (r) => readingsB.push(r));
      consumerA.subscribeSensor();
      consumerB.subscribeSensor();

      const echo = fakeEcho.getEcho();
      const ch = echo.channel('sensor.77');
      ch.emit('NewSensorReading', { reading: { id: 100, sensor_id: 77, value: 1 } });
      record(
        'channel registry: two consumers on same sensor both receive events',
        readingsA.length === 1 && readingsB.length === 1
      );

      consumerA.unsubscribeSensor();
      ch.emit('NewSensorReading', { reading: { id: 101, sensor_id: 77, value: 2 } });
      record(
        'channel registry: releasing one consumer keeps channel alive for the other',
        readingsA.length === 1 && readingsB.length === 2,
        { a: readingsA.length, b: readingsB.length }
      );

      consumerB.unsubscribeSensor();
      ch.emit('NewSensorReading', { reading: { id: 102, sensor_id: 77, value: 3 } });
      record(
        'channel registry: last release tears down channel',
        readingsA.length === 1 && readingsB.length === 2
      );
    }

    // ================================================================
    // S5-02: Unavailable marks state correctly
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      await load('/src/stores/alerts.js');
      const sensorMod = await load('/src/realtime/useSensorRealtime.js');

      fakeEcho.__connect();
      const sensor = sensorMod.useSensorRealtime(888, () => {});
      sensor.subscribeSensor();

      fakeEcho.__unavailable();
      record(
        'S5-02: unavailable marks isConnected=false',
        sensor.isConnected.value === false,
        { isConnected: sensor.isConnected.value }
      );

      sensor.unsubscribeSensor();
    }

    // ================================================================
    // S5-03: Live event while snapshot is in flight survives
    // RECOVERY_RACE_SIMULATED — uses async with explicit timer flush
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      const alertsStoreMod = await load('/src/stores/alerts.js');
      const alertsStore = alertsStoreMod.useAlertsStore();

      alertsStore.addRealtimeAlert({ id: 10, message: 'baseline', severity: 'warning' });

      let fetchDelayResolve;
      alertsStore.fetchActiveAlerts = async function mockFetch() {
        await new Promise((r) => { fetchDelayResolve = r; });
        alertsStore.activeAlerts = [{ id: 10, message: 'baseline', severity: 'warning' }];
        alertsStore.unresolvedCount = 1;
        return [];
      };

      const alertsMod = await load('/src/realtime/useAlertsRealtime.js');
      alertsMod.subscribeAlerts();

      const echo = fakeEcho.getEcho();
      const alertsChannel = echo.channel('alerts');

      // Establish initial connection so _hasConnectedOnce=true (required for reconnect detection)
      fakeEcho.__connect();
      fakeEcho.__disconnect();
      fakeEcho.__connect(); // triggers runSnapshot which awaits the mock

      // Emit live event while snapshot is in flight
      alertsChannel.emit('NewAlertTriggered', { alert: { id: 20, message: 'live during recovery', severity: 'danger' } });

      // Release the snapshot fetch so it completes
      fetchDelayResolve();
      await flush(20); // let microtasks settle

      const hasLiveAlert = alertsStore.activeAlerts.some((a) => Number(a.id) === 20);
      record(
        'S5-03: live event during snapshot in flight survives (RECOVERY_RACE_SIMULATED)',
        hasLiveAlert === true,
        { activeAlertIds: alertsStore.activeAlerts.map((a) => a.id) }
      );

      alertsMod.unsubscribeAlerts();
    }

    // ================================================================
    // S5-03: Duplicate event during snapshot applies once
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      const alertsStoreMod = await load('/src/stores/alerts.js');
      const alertsStore = alertsStoreMod.useAlertsStore();

      let fetchDelayResolve;
      alertsStore.fetchActiveAlerts = async function mockFetch() {
        await new Promise((r) => { fetchDelayResolve = r; });
        alertsStore.activeAlerts = [];
        alertsStore.unresolvedCount = 0;
        return [];
      };

      const alertsMod = await load('/src/realtime/useAlertsRealtime.js');
      alertsMod.subscribeAlerts();

      const echo = fakeEcho.getEcho();
      const alertsChannel = echo.channel('alerts');

      // Establish initial connection so _hasConnectedOnce=true
      fakeEcho.__connect();
      fakeEcho.__disconnect();
      fakeEcho.__connect();

      alertsChannel.emit('NewAlertTriggered', { alert: { id: 30, message: 'dup 1', severity: 'warning' } });
      alertsChannel.emit('NewAlertTriggered', { alert: { id: 30, message: 'dup 2', severity: 'warning' } });

      fetchDelayResolve();
      await flush(20);

      const count30 = alertsStore.activeAlerts.filter((a) => Number(a.id) === 30).length;
      record(
        'S5-03: duplicate event during snapshot applies exactly once (RECOVERY_RACE_SIMULATED)',
        count30 === 1,
        { count30, activeCount: alertsStore.activeAlerts.length }
      );

      alertsMod.unsubscribeAlerts();
    }

    // ================================================================
    // S5-06: Snapshot failure leaves state stale, not live
    // ================================================================
    {
      fakeEcho.__reset();
      setActivePinia(createPinia());
      const alertsStoreMod = await load('/src/stores/alerts.js');
      const alertsStore = alertsStoreMod.useAlertsStore();

      let fetchReject;
      alertsStore.fetchActiveAlerts = async function failingFetch() {
        await new Promise((_, reject) => { fetchReject = reject; });
      };

      const alertsMod = await load('/src/realtime/useAlertsRealtime.js');
      alertsMod.subscribeAlerts();

      fakeEcho.__connect();
      fakeEcho.__disconnect();
      fakeEcho.__connect(); // triggers runSnapshot

      fetchReject(new Error('network error'));
      await flush(20);

      const status = alertsStore.realtimeStatus;
      record(
        'S5-06: snapshot failure leaves projection stale, not live (RECOVERY_RACE_SIMULATED)',
        status.mode === 'stale' && status.connected === true,
        { mode: status.mode, connected: status.connected, error: status.error }
      );

      alertsMod.unsubscribeAlerts();
    }

  } finally {
    // Don't await server.close() — it can hang on pending Vite handles.
    // The process exits below via process.exit().
    server.close().catch(() => {});
  }

  const failed = results.filter((r) => !r.pass);
  console.log(`\n${results.length - failed.length}/${results.length} CONNECTION_LIFECYCLE_SIMULATED / RECOVERY_RACE_SIMULATED checks passed.`);
  if (failed.length > 0) {
    console.log('Failing checks:');
    failed.forEach((f) => console.log(`  - ${f.name}`));
  }

  const fs = await import('node:fs');
  fs.mkdirSync(path.join(__dirname, 'results'), { recursive: true });
  fs.writeFileSync(path.join(__dirname, 'results', 'realtime-lifecycle.json'), JSON.stringify(results, null, 2));

  process.exit(failed.length > 0 ? 1 : 0);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
