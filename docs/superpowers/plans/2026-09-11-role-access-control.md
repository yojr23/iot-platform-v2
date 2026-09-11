# Role Access Control Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make metrics available to every authenticated user while restricting every product mutation except alert resolution to administrators.

**Architecture:** Keep the existing boolean `User::is_admin` and `admin` middleware as the sole authorization source. Move only metric reads into authenticated route groups; put inherited configuration and dashboard-preference writes behind `admin`. The Vue client mirrors those rules by making Metrics visible to all authenticated sessions and showing persistent dashboard-personalization controls only to administrators; it never substitutes for backend authorization.

**Tech Stack:** Laravel 12 / Sanctum / PHPUnit feature tests, Vue 3 / Pinia / Vue Router / Vitest.

**Spec:** `docs/superpowers/specs/2026-09-11-role-access-control-design.md`

## Global Constraints

- Preserve `is_admin` as the single role source; do not add roles, token types, or a second policy layer.
- Implementation baseline: `9be5e95` (`docs: restrict standard user mutations`); inspect the final authorization diff against this commit.
- Preserve the protections already present on `main`: `is_admin` remains non-fillable, model updates require an authenticated administrator, and the `admin` middleware remains on every resource-management route.
- A standard authenticated user may read monitoring data, alerts, and metrics, and may resolve one or all alerts. No other product write is allowed.
- Identity lifecycle actions (login, logout, verification, password recovery) remain outside this role policy.
- `GET /api/internal/metrics/*` remains administrator-only; only `GET /api/metrics` becomes authenticated-read access.
- Standard users can make temporary dashboard monitoring selections (sensor and time range), but cannot add, remove, reorder, or save dashboard widgets.
- No production code before a test that fails for the intended missing behavior. Do not change dependencies.

---

### Task 1: Enforce the backend role boundary for metrics, preferences, and legacy Blade routes

**Files:**
- Modify: `back/routes/api.php:84-153`
- Modify: `back/routes/web.php:40-115`
- Modify: `back/tests/Feature/AdminAccessTest.php`
- Modify: `back/tests/Feature/DashboardPreferenceControllerTest.php`
- Modify: `back/tests/Feature/ApiAuthTokenTest.php`

**Interfaces:**
- Consumes: `User::is_admin`, the `admin` middleware alias, existing `MetricsController`, `Api\MetricsController`, and `DashboardPreferenceController`.
- Produces: `GET /api/metrics`, `GET /metrics`, and `GET /metrics/data` available to verified/authenticated standard users; `PUT /api/dashboard/preferences`, `POST /dashboard/preferences`, and `GET /config` denied to standard users; all retained admin APIs unchanged.

- [ ] **Step 1: Write the failing backend authorization tests**

  In `back/tests/Feature/AdminAccessTest.php`, replace the existing expectation that a non-admin is forbidden from `metrics.index` with an authenticated-read test. Add a second assertion for `metrics.data`, which validates the JSON envelope:

  ```php
  public function test_standard_user_can_access_legacy_metrics_reads(): void
  {
      $user = User::factory()->create(['is_admin' => false]);

      $this->actingAs($user)->get(route('metrics.index'))->assertOk();
      $this->actingAs($user)->getJson(route('metrics.data'))
          ->assertOk()
          ->assertJsonStructure(['generated_at', 'snapshot']);
  }
  ```

  Add the configuration guard to the same class:

  ```php
  public function test_standard_user_cannot_access_legacy_configuration(): void
  {
      $user = User::factory()->create(['is_admin' => false]);

      $this->actingAs($user)->get(route('config.index'))->assertForbidden();
  }
  ```

  In `back/tests/Feature/DashboardPreferenceControllerTest.php`, add a standard-user write denial while retaining its current read test:

  ```php
  public function test_standard_user_cannot_store_dashboard_preferences(): void
  {
      $user = User::factory()->create(['is_admin' => false]);

      $this->actingAs($user)->postJson(route('dashboard.preferences.store'), [
          'layout' => ['main' => ['id' => 'main', 'range' => '5m']],
      ])->assertForbidden();

      $this->assertDatabaseMissing('dashboard_preferences', ['user_id' => $user->id]);
  }
  ```

  Change the existing successful preference-store fixtures in that file to create `['is_admin' => true]`; their behavior must remain an admin capability.

  In `back/tests/Feature/ApiAuthTokenTest.php`, use the non-admin bearer token obtained in `test_api_me_returns_authenticated_user_data_with_bearer_token` (or a dedicated equivalent test) to add:

  ```php
  $this->withToken($token)->getJson('/api/metrics')
      ->assertOk()
      ->assertJsonStructure(['generated_at', 'snapshot']);

  $this->withToken($token)->putJson('/api/dashboard/preferences', [
      'layout' => ['main' => ['id' => 'main', 'range' => '5m']],
  ])->assertForbidden();
  ```

- [ ] **Step 2: Run the focused tests and verify expected failures**

  Run:

  ```powershell
  Set-Location back
  php artisan test --filter="AdminAccessTest|DashboardPreferenceControllerTest|ApiAuthTokenTest"
  ```

  Expected: the added metric-read tests fail with 403, and the new standard-user preference/configuration denial tests fail because the relevant route is currently available to an authenticated non-admin. Existing administrator preference tests may still pass before their fixtures are changed.

- [ ] **Step 3: Make the minimal route changes**

  In `back/routes/api.php`, add the metric read directly in the outer `auth:sanctum` group and remove the duplicate from the nested `admin` group:

  ```php
  Route::get('/metrics', [ApiMetricsController::class, 'index'])
      ->middleware('throttle:api-read');
  ```

  Change the preference write to retain its current throttle but add the existing middleware alias:

  ```php
  Route::put('/preferences', [ApiDashboardPreferenceController::class, 'store'])
      ->middleware(['admin', 'throttle:api-write']);
  ```

  Do not alter `GET /dashboard/preferences`, either alert-resolution route, or either `/api/internal/metrics/*` route.

  In `back/routes/web.php`, place the two metric reads inside the outer `['auth', 'verified']` group before the nested `admin` group:

  ```php
  Route::get('metrics', [MetricsController::class, 'index'])->name('metrics.index');
  Route::get('metrics/data', [MetricsController::class, 'data'])->name('metrics.data');
  ```

  Move `GET config` into the nested `Route::middleware('admin')` group. Change the dashboard preference POST route to require the existing `admin` middleware while leaving its GET route verified/authenticated:

  ```php
  Route::post('preferences', [DashboardPreferenceController::class, 'store'])
      ->middleware('admin')
      ->name('dashboard.preferences.store');
  ```

- [ ] **Step 4: Run the focused backend tests and verify green**

  Run:

  ```powershell
  Set-Location back
  php artisan test --filter="AdminAccessTest|DashboardPreferenceControllerTest|ApiAuthTokenTest"
  ```

  Expected: all focused tests pass; standard users receive 200 only for metrics reads and 403 for configuration/preference writes, while administrator preference persistence remains successful.

- [ ] **Step 5: Commit the backend boundary**

  ```powershell
  git add back/routes/api.php back/routes/web.php back/tests/Feature/AdminAccessTest.php back/tests/Feature/DashboardPreferenceControllerTest.php back/tests/Feature/ApiAuthTokenTest.php
  git commit -m "feat: enforce monitoring role boundaries"
  ```

### Task 2: Expose the Metrics SPA route and navigation to authenticated users

**Files:**
- Modify: `front/src/router/index.js:135-157`
- Modify: `front/src/router/configRoutes.test.js`
- Modify: `front/src/components/layout/NavBar.vue:78-100`
- Modify: `front/src/components/layout/NavBar.test.js`
- Create: `front/src/components/dashboard/lab/LabShell.roles.test.js`
- Modify: `front/src/components/dashboard/lab/LabShell.vue:90-114`

**Interfaces:**
- Consumes: `authStore.isAuthenticated` and `authStore.user.is_admin` from `useAuthStore`.
- Produces: route metadata `{ requiresAuth: true }` for `metrics`; authenticated navigation containing “Métricas”; admin-only navigation containing “Configuración”.

- [ ] **Step 1: Write the failing route and navigation tests**

  In `front/src/router/configRoutes.test.js`, add a separate metrics assertion rather than weakening the configuration loop:

  ```js
  it('allows every authenticated user to navigate to metrics', () => {
    const route = router.getRoutes().find((candidate) => candidate.name === 'metrics');

    expect(route?.meta).toMatchObject({ requiresAuth: true });
    expect(route?.meta.requiresAdmin).toBeUndefined();
  });
  ```

  In `front/src/components/layout/NavBar.test.js`, add the standard-user expectation:

  ```js
  it('shows metrics but not settings to an authenticated standard user', async () => {
    const { el, unmount } = await mountNavBar({ authenticated: true, admin: false });

    expect(el.textContent).toContain('Métricas');
    expect(el.textContent).not.toContain('Configuración');
    unmount();
  });
  ```

  Create `front/src/components/dashboard/lab/LabShell.roles.test.js`. Mount `LabShell` with Pinia, a stubbed `RouterLink`, and mocked `vue-router` route state. Its two assertions must prove the actual Lab Blue shell—the one used by `AppLayout` for `metrics`—shows “Métricas” for `{ token: 'test', user: { id: 1, is_admin: false } }`, and shows “Configuración” only once `is_admin` is true.

- [ ] **Step 2: Run the focused frontend tests and verify expected failures**

  Run:

  ```powershell
  Set-Location front
  npm run test:unit -- src/router/configRoutes.test.js src/components/layout/NavBar.test.js src/components/dashboard/lab/LabShell.roles.test.js
  ```

  Expected: the metrics route test fails because it currently has `requiresAdmin: true`; standard-user navigation tests fail because both shells currently condition Metrics on `is_admin`.

- [ ] **Step 3: Change the route and both navigation surfaces**

  In `front/src/router/index.js`, replace the metrics metadata with:

  ```js
  meta: { requiresAuth: true }
  ```

  In `NavBar.vue`, make the metrics entry part of the authenticated navigation and leave only configuration as admin-only:

  ```js
  const authenticatedItems = [
    { label: 'Métricas', to: '/metrics' }
  ];
  const adminItems = [
    { label: 'Configuración', to: '/config' }
  ];
  ```

  Spread `authenticatedItems` after `laboratoryItems` for every authenticated session and spread `adminItems` only when `authStore.user?.is_admin` is true.

  In `LabShell.vue`, add `{ to: '/metrics', label: 'Métricas', icon: 'chart' }` to the `auth.isAuthenticated` branch and leave `{ to: '/config', label: 'Configuración', icon: 'grip' }` in the `auth.user?.is_admin` branch.

- [ ] **Step 4: Run the focused frontend tests and verify green**

  Run:

  ```powershell
  Set-Location front
  npm run test:unit -- src/router/configRoutes.test.js src/components/layout/NavBar.test.js src/components/dashboard/lab/LabShell.roles.test.js
  ```

  Expected: all tests pass; unauthenticated navigation remains unchanged, standard users see Metrics only, and admins see both Metrics and Configuration.

- [ ] **Step 5: Commit the SPA visibility change**

  ```powershell
  git add front/src/router/index.js front/src/router/configRoutes.test.js front/src/components/layout/NavBar.vue front/src/components/layout/NavBar.test.js front/src/components/dashboard/lab/LabShell.vue front/src/components/dashboard/lab/LabShell.roles.test.js
  git commit -m "feat: show metrics to authenticated users"
  ```

### Task 3: Restrict persistent dashboard personalization to administrators

**Files:**
- Modify: `front/src/composables/useLabWorkspace.js:250-344`
- Modify: `front/src/composables/useLabWorkspace.test.js`
- Modify: `front/src/components/dashboard/SensorMonitorBoard.vue`
- Modify: `front/src/components/dashboard/SensorMonitorBoard.test.js`

**Interfaces:**
- Consumes: `auth.user?.is_admin` from `useAuthStore` and `updateDashboardPreferences({ layout })` from `@/api/dashboard`.
- Produces: `canManageDashboard` (a computed boolean returned by `useLabWorkspace`); `save()` makes no request unless `canManageDashboard.value`; only admins receive controls to save, add, remove, reorder, or enter edit mode.

- [ ] **Step 1: Write the failing workspace and rendered-control tests**

  In `front/src/composables/useLabWorkspace.test.js`, change `mount` to accept an `admin` argument and assign it to `auth.user.is_admin`. Convert the existing successful-save tests to call `mount(true, true)`. Add a non-admin safety test:

  ```js
  it('does not persist dashboard edits for an authenticated standard user', async () => {
    const { state } = await mount(true, false);

    state.changeRange('1h');
    await state.save();

    expect(state.canManageDashboard.value).toBe(false);
    expect(api.save).not.toHaveBeenCalled();
  });
  ```

  In `front/src/components/dashboard/SensorMonitorBoard.test.js`, mount once with an authenticated non-admin store and assert that the rendered text does not contain `Guardar`, `Agregar gráfica`, or `Editar`. Mount again as admin and assert each control is present. Keep an assertion that the time-range buttons are still present for the standard user, because range selection is monitoring, not persisted administration.

- [ ] **Step 2: Run the focused frontend tests and verify expected failures**

  Run:

  ```powershell
  Set-Location front
  npm run test:unit -- src/composables/useLabWorkspace.test.js src/components/dashboard/SensorMonitorBoard.test.js
  ```

  Expected: the new standard-user test fails because `save()` currently writes for every authenticated user, and rendered-control assertions fail because the dashboard currently displays personalization controls for every session.

- [ ] **Step 3: Add one capability gate and use it consistently**

  In `useLabWorkspace.js`, define and return one computed capability:

  ```js
  const canManageDashboard = computed(() => Boolean(auth.user?.is_admin));
  ```

  Guard persistence at the source:

  ```js
  async function save() {
    if (!canManageDashboard.value || saveState.value === 'saving') return;
    // existing save body unchanged
  }
  ```

  Also gate the unsaved-change browser confirmation with `canManageDashboard.value`, so standard users are not warned about a layout they cannot save. Keep range, sensor, and selected-chart interactions usable in memory.

  In `SensorMonitorBoard.vue`, destructure `canManageDashboard` from `useLabWorkspace`. Wrap the save status, Save button, Add-chart buttons (toolbar, empty state, and list), Edit toggle, edit action buttons, and undo message in `v-if="canManageDashboard"`. Do not gate selectors, ranges, chart expansion, alert links, retry, or alert-resolution navigation.

- [ ] **Step 4: Run the focused frontend tests and verify green**

  Run:

  ```powershell
  Set-Location front
  npm run test:unit -- src/composables/useLabWorkspace.test.js src/components/dashboard/SensorMonitorBoard.test.js
  ```

  Expected: standard users never issue a preference write and cannot see persistence controls; administrators retain the existing save workflow; monitoring selectors remain visible for standard users.

- [ ] **Step 5: Commit the dashboard capability gate**

  ```powershell
  git add front/src/composables/useLabWorkspace.js front/src/composables/useLabWorkspace.test.js front/src/components/dashboard/SensorMonitorBoard.vue front/src/components/dashboard/SensorMonitorBoard.test.js
  git commit -m "feat: restrict dashboard personalization to admins"
  ```

### Task 4: Run complete verification and render the affected authenticated flow

**Files:**
- Modify: none expected.

**Interfaces:**
- Consumes: all authorization and UI behavior from Tasks 1–3.
- Produces: fresh evidence that the backend role boundary, frontend unit suites, and production frontend build are valid.

- [ ] **Step 1: Run all relevant backend feature tests**

  Run:

  ```powershell
  Set-Location back
  php artisan test --filter="AdminAccessTest|SecurityAccessControlTest|DashboardPreferenceControllerTest|ApiAuthTokenTest|Phase2ApiEndpointsTest|SpaParityApiTest"
  ```

  Expected: exit code 0. In particular, alert resolution continues to succeed for a standard user, resource-management attempts remain forbidden, `/api/metrics` is readable to a standard bearer token, and internal metrics remain admin-only.

- [ ] **Step 2: Run all frontend unit tests and production build**

  Run:

  ```powershell
  Set-Location front
  npm run test:unit
  npm run build
  ```

  Expected: both commands exit 0 with no test failures or build errors.

- [ ] **Step 3: Perform rendered QA with the app’s Browser path**

  Start the frontend using its existing local command, authenticate once as a standard user and once as an administrator, and validate this flow in each session:

  ```text
  /dashboard -> sidebar navigation -> /metrics -> /alerts -> resolve an alert
  ```

  For the standard user verify: Metrics is visible and loads; Configuration is absent; dashboard save/add/edit controls are absent; alert resolution works. For the admin verify: Metrics and Configuration are visible and dashboard save/add/edit controls remain available. Capture DOM/screenshot and console evidence according to `frontend-testing-debugging`; if the Browser plugin is unavailable, record that and use the project Playwright workflow without adding dependencies.

- [ ] **Step 4: Inspect the final authorization diff**

  Run:

  ```powershell
  git diff 9be5e95..HEAD -- back/routes/api.php back/routes/web.php front/src/router/index.js front/src/components/layout/NavBar.vue front/src/components/dashboard/lab/LabShell.vue front/src/composables/useLabWorkspace.js front/src/components/dashboard/SensorMonitorBoard.vue
  ```

  Expected: only the agreed metrics-read access is broadened; all product writes except alert resolution remain administrator-only for standard users.

- [ ] **Step 5: Commit any verification-only test adjustments, if and only if files changed**

  ```powershell
  git status --short
  ```

  If no source or test file changed, do not create an empty commit. If a test adjustment was required to reflect the already-approved behavior, stage only that adjustment and commit it with a scoped `test:` message.
