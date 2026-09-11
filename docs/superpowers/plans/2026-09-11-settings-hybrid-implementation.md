# Configuración híbrida Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the existing monolithic configuration form into a Lab Blue settings hub with focused, functional settings screens.

**Architecture:** `ConfigView` becomes a read-only summary and navigation hub. Dedicated views own their section's API loading and mutation state, retaining the existing API contracts. The existing admin routes remain reachable from action rows but leave both navigation bars.

**Tech Stack:** Vue 3 Composition API, Vue Router 4, Vitest, existing Base components and Lab Blue CSS.

**Spec:** `docs/superpowers/specs/2026-09-11-settings-hybrid-design.md`

## Global Constraints

- Retain the existing `/config/*` API endpoints and never display SMTP passwords.
- Every configuration route requires authenticated administrator access.
- Keep only Métricas and Configuración among administrative links in both navigation components.
- Use only existing Lab Blue tokens and semantic green/amber/red status tints.

---

### Task 1: Route and administrative navigation contracts

**Files:**
- Modify: `front/src/router/index.js`
- Modify: `front/src/components/dashboard/lab/LabShell.vue`
- Modify: `front/src/components/layout/NavBar.vue`
- Modify: `front/src/components/layout/AppLayout.vue`
- Modify: `front/src/components/layout/NavBar.test.js`
- Create: `front/src/router/configRoutes.test.js`

- [ ] **Step 1: Write failing tests** for four named protected configuration routes and for an administrator navbar that omits Reglas, Catálogos and Usuarios while retaining Métricas and Configuración.
- [ ] **Step 2: Run the tests and verify they fail** because the subroutes and the filtered administration menu do not exist.
- [ ] **Step 3: Add the four lazy configuration routes and remove the three administration links from both menu definitions.**
- [ ] **Step 4: Run the focused tests and verify they pass.**

### Task 2: Settings hub and focused functional views

**Files:**
- Modify: `front/src/views/ConfigView.vue`
- Create: `front/src/views/config/GeneralConfigView.vue`
- Create: `front/src/views/config/AlertConfigView.vue`
- Create: `front/src/views/config/EmailConfigView.vue`
- Create: `front/src/views/config/DiagnosticsConfigView.vue`
- Create: `front/src/views/ConfigView.test.js`

- [ ] **Step 1: Write failing tests** that expect the hub to render General, Alertas, Correo and Diagnóstico links after partial API responses, and each focused route to expose its existing save/test behavior.
- [ ] **Step 2: Run the tests and verify they fail** because the focused modules do not exist and the hub still owns forms.
- [ ] **Step 3: Implement the hub and views.** The hub loads endpoint summaries independently; focused views load their own section, submit the existing payload shape, show existing alert feedback, and include a return link.
- [ ] **Step 4: Run the focused tests and verify they pass.**

### Task 3: Lab Blue visual treatment and verification

**Files:**
- Modify: `front/src/assets/styles/lab-resources.css`
- Modify: `front/src/components/dashboard/lab/LabIcon.vue`

- [ ] **Step 1: Add narrow configuration styles** for icon-tinted status summary, accessible list rows, hover/focus behavior, and mobile stacking without changing unrelated resource surfaces.
- [ ] **Step 2: Add only the icon names used by the new views in the existing stroke style.**
- [ ] **Step 3: Run `npm run test:unit` and `npm run build` from `front`.**
- [ ] **Step 4: Run the demo server and verify desktop and mobile rendering plus hub → each screen navigation and email test submission.**
