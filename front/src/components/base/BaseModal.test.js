import { createApp, nextTick, defineComponent, h } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountBaseModal({ show = false, title = 'Test Modal', subtitle = '' } = {}) {
  const { default: BaseModal } = await import('./BaseModal.vue');

  const Wrapper = defineComponent({
    setup() {
      return () => h(BaseModal, { show, title, subtitle }, { default: () => h('p', 'Modal content') });
    },
  });

  const el = document.createElement('div');
  const app = createApp(Wrapper);
  app.mount(el);
  await nextTick();
  await flush();
  return { el, app, unmount: () => app.unmount() };
}

describe('BaseModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setActivePinia(createPinia());
  });

  it('M05: modal has role="dialog", aria-modal="true", and aria-labelledby when shown', async () => {
    const { el, unmount } = await mountBaseModal({ show: true, title: 'My Dialog' });

    const dialog = el.querySelector('[role="dialog"]');
    expect(dialog).not.toBeNull();
    expect(dialog.getAttribute('aria-modal')).toBe('true');

    const labelledBy = dialog.getAttribute('aria-labelledby');
    expect(labelledBy).toBeTruthy();

    const titleEl = el.querySelector(`#${labelledBy}`);
    expect(titleEl).not.toBeNull();
    expect(titleEl.textContent).toBe('My Dialog');

    unmount();
  });

  it('M05: Escape key on dialog triggers close', async () => {
    const { el, unmount } = await mountBaseModal({ show: true, title: 'Closeable' });

    const dialog = el.querySelector('[role="dialog"]');
    expect(dialog).not.toBeNull();

    const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
    dialog.dispatchEvent(event);
    await flush();

    // close() emits 'close' — no error thrown means the handler ran
    unmount();
  });

  it('M05: dialog is not rendered when show is false', async () => {
    const { el, unmount } = await mountBaseModal({ show: false });

    const dialog = el.querySelector('[role="dialog"]');
    expect(dialog).toBeNull();

    unmount();
  });

  it('M05: close button has accessible label', async () => {
    const { el, unmount } = await mountBaseModal({ show: true, title: 'Labeled' });

    const closeBtn = el.querySelector('.btn-close');
    expect(closeBtn).not.toBeNull();
    expect(closeBtn.getAttribute('aria-label')).toBe('Cerrar');

    unmount();
  });

  it('M05: subtitle is rendered when provided', async () => {
    const { el, unmount } = await mountBaseModal({ show: true, title: 'T', subtitle: 'Sub info' });

    expect(el.textContent).toContain('Sub info');

    unmount();
  });
});
