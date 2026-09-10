# SINOA Lab Blue frontend handoff

## Repository and delivery

Based on `yojr23/iot-platform-v2`, branch `refraccion`, base commit `a589df2`. This change is intended for review on `refraccion`. No application deployment is included.

The dashboard follows the desktop/mobile image embedded in `SINOA_Plan_Desarrollo_Diseno_Final_v1.1_PUBLIC_REALTIME(4).docx`. The accompanying implementation plan and rebuild adjustments take precedence over unsupported fields shown in that image.

## Run locally

```bash
cd front
npm ci
npm run dev:demo
```

Open http://localhost:5173/dashboard. The clearly labeled review bar switches between public and private previews. All demo readings, identities and alerts are synthetic. The demo uses a local HTTP/WebSocket fixture server; it needs no Laravel process. Demo preferences persist in the ignored `front/.demo-data` directory. Keep this mode local.

Use `npm run dev` with the repository's existing backend/environment configuration for real API data. `npm run build` builds the normal application; the demo server is development-only. PHP, database and production realtime integration were not exercised in this environment.

## Visual hierarchy and brand

- White sidebar, cool pale background, restrained blue outlines, dark blue primary reading.
- Desktop: 208px navigation, 64px header, 24px content spacing, 280px inspector. Main chart first; three secondary charts and recent authorized alerts below. Summary statistics sit beside the reading on wide screens.
- Mobile: single column, visible device/sensor selectors and time ranges; chart then statistics, chart list, collapsible sensor details. Fixed bottom navigation. Interactive mobile controls retain 44px touch targets.
- Inter for navigation and prose; JetBrains Mono for numeric readings. Fonts are bundled locally. Primary reading 44px desktop / 36px mobile.
- Brand `#1E40AF`, actions `#2563EB`, canvas `#F8FAFC`, surfaces `#FFFFFF`; green/red/amber communicate actual transport or alert state alongside text.
- Voice: concise Spanish, specific nouns and actions: “Mi tablero”, “Agregar gráfica”, “Guardar”, “Deshacer”. Explain empty/error states without claiming that missing measurements are healthy.

## Implementation ownership

`LabShell.vue` owns the dashboard frame and responsive navigation. Existing layout lifecycle still owns authenticated alert/device subscriptions. `SensorMonitorBoard.vue` composes the workspace; `useLabWorkspace.js` manages widget identity, selection, bounded queries, live overlays, removal/undo and explicit persistence. `SensorReadingChart.vue` remains the shared chart renderer. Lab-specific styles live in `lab-blue.css`.

Keep the established API, Pinia query cache and realtime channel registry. Initial history uses explicit half-open UTC windows; streaming readings overlay the historical projection without HTTP polling. Null measurements remain gaps, never zero. Statistics describe returned observations, with partial-data disclosure.

Public users receive the public catalogue/history only and an in-memory draft; saving routes to login. Private users additionally receive authorized alerts and explicit preference saving. Failed saves preserve the draft; edits made while saving remain dirty. Route exit and tab close warn about unsaved authenticated changes. Remove/undo preserves stable widget identity.

## Intentional differences from the image

Do not add threshold bands, signal quality, sampling cadence, inferred sensor health, global system-health claims, invented labs or dead search/settings/report actions. The supplied V1 adjustments explicitly exclude unsupported contracts. “Transporte conectado” describes the websocket transport, not freshness or sensor health. Authorized alerts replace an unsupported generic event feed.

Authenticated chart history currently uses the existing public graph catalogue endpoint. A restricted private graph catalogue/history needs backend work. Preference revision/conflict handling is not implemented because the existing API does not expose that contract; verify server acceptance of widget range/selection metadata before production rollout. Guest drafts are not transferred across the login route yet.

## Verification

- Production Vite build passes.
- 133 unit tests across 27 files pass, including public/private boundaries, obsolete requests, null gaps and persistence races.
- `node scripts/check-lab-demo.mjs`: public catalogue, protected endpoint denial, bounded history, local login and websocket reading smoke checks.
- `node scripts/capture-lab-demo.mjs`: Chromium screenshots for both scopes at 1440px desktop and 390px mobile, guest privacy, remove/undo, private alerts, 320px overflow and page error assertions. Requires `npx playwright install chromium`.
- Screenshots were visually compared with the embedded Word reference; adjusted desktop density, statistics placement, mobile ordering and compact axis labels.
- Existing jsdom chart tests emit canvas-context diagnostics; real canvas rendering is checked in Chromium.

## Further implementation priorities

1. Connect and verify against the actual Laravel environment with real authorized accounts and sensor streams.
2. Confirm preference schema behavior for range and selection metadata; add revision conflict behavior only with an explicit server contract.
3. Implement private graph catalogue/history authorization on the server before exposing restricted sources.
4. Transfer the guest draft through login if that behavior is required.
5. Extend the visual system to device, sensor and alert detail routes separately; this change focuses on the requested dashboard.
