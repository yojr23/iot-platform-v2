import { createApp, nextTick, ref } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';

const mountedApps = [];

async function mountCredentialModal(apiKey = 'device-secret-key') {
  const { default: DeviceApiKeyModal } = await import('./DeviceApiKeyModal.vue');
  const show = ref(true);
  const key = ref(apiKey);
  const el = document.createElement('div');
  const app = createApp({
    components: { DeviceApiKeyModal },
    setup() {
      function close() {
        key.value = '';
        show.value = false;
      }
      return { show, key, close };
    },
    template: '<DeviceApiKeyModal :show="show" :api-key="key" @close="close" />'
  });
  app.mount(el);
  mountedApps.push(app);
  await nextTick();
  return { el, key };
}

describe('DeviceApiKeyModal', () => {
  afterEach(() => {
    mountedApps.splice(0).forEach((app) => app.unmount());
    vi.restoreAllMocks();
  });

  it('offers a one-time credential with copy and clears it when dismissed', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });
    const { el, key } = await mountCredentialModal();

    expect(el.textContent).toContain('no se mostrará de nuevo');
    expect(el.querySelector('#one_time_device_api_key').value).toBe('device-secret-key');

    [...el.querySelectorAll('button')].find((button) => button.textContent.trim() === 'Copiar')
      .dispatchEvent(new Event('click', { bubbles: true }));
    await nextTick();
    expect(writeText).toHaveBeenCalledWith('device-secret-key');

    el.querySelector('button[aria-label="Cerrar"]').dispatchEvent(new Event('click', { bubbles: true }));
    await nextTick();
    expect(key.value).toBe('');
    expect(el.querySelector('#one_time_device_api_key')).toBeNull();
  });
});
