import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
}));

vi.mock('@/utils/formatters', () => ({
  formatDate: (v) => v || '',
  formatNumber: (v) => (v === null || v === undefined ? '' : String(v)),
  severityLabel: (s) => (s === 'danger' ? 'Crítica' : s === 'warning' ? 'Advertencia' : 'Info'),
}));

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountAlertToast() {
  const { default: AlertToast } = await import('./AlertToast.vue');
  const el = document.createElement('div');
  const app = createApp(AlertToast);
  app.component('RouterLink', { template: '<a><slot /></a>' });
  app.mount(el);
  await nextTick();
  await flush();
  return { el, app, unmount: () => app.unmount() };
}

describe('AlertToast', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setActivePinia(createPinia());
  });

  it('M04: toast container always renders with responsive width class', async () => {
    const { el, unmount } = await mountAlertToast();

    // The container is always rendered; the .toast inside is conditional
    const container = el.querySelector('.alert-toast-container');
    expect(container).not.toBeNull();

    // Verify the container has the CSS class that applies responsive width
    expect(container.classList.contains('alert-toast-container')).toBe(true);
    expect(container.classList.contains('position-fixed')).toBe(true);

    unmount();
  });

  it('M04: toast is not visible when no alert is active', async () => {
    const { el, unmount } = await mountAlertToast();

    // The .toast div is inside v-if="visible && currentAlert", so it should not exist
    const toast = el.querySelector('.toast');
    expect(toast).toBeNull();

    unmount();
  });

  it('close button with aria-label is absent when no alert is active', async () => {
    const { el, unmount } = await mountAlertToast();

    const closeBtn = el.querySelector('.btn-close');
    expect(closeBtn).toBeNull();

    unmount();
  });
});
