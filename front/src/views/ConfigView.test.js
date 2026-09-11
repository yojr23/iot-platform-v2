import { createApp, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const configApi = vi.hoisted(() => ({
  getAlertConfig: vi.fn(),
  getEmailConfig: vi.fn(),
  getGeneralConfig: vi.fn(),
  getRuntimeConfig: vi.fn(),
  getSystemInfo: vi.fn(),
  testEmailConfig: vi.fn(),
  updateAlertConfig: vi.fn(),
  updateEmailConfig: vi.fn(),
  updateGeneralConfig: vi.fn()
}));

vi.mock('@/api/config', () => configApi);

const response = (data, message) => ({ data: { data, ...(message ? { message } : {}) } });
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountView(View) {
  const el = document.createElement('div');
  const app = createApp(View);
  app.component('RouterLink', {
    props: ['to'],
    template: '<a :href="to"><slot /></a>'
  });
  app.mount(el);
  await nextTick();
  await flush();
  return { el, unmount: () => app.unmount() };
}

function setResponses() {
  configApi.getRuntimeConfig.mockResolvedValue(response({ app_name: 'SINOA', app_url: 'https://sinoa.test' }));
  configApi.getGeneralConfig.mockResolvedValue(response({ app_name: 'SINOA', app_url: 'https://sinoa.test' }));
  configApi.getAlertConfig.mockResolvedValue(response({
    mail_enabled: true,
    alert_sound_enabled: false,
    alert_threshold: 70,
    sensor_update_interval: 1000,
    danger_email_rate_limit_seconds: 60
  }));
  configApi.getEmailConfig.mockResolvedValue(response({
    mail_mailer: 'smtp',
    mail_host: 'smtp.sinoa.test',
    mail_port: 587,
    mail_username: 'alerts@sinoa.test',
    mail_encryption: 'tls',
    mail_from_address: 'alerts@sinoa.test',
    mail_from_name: 'SINOA',
    mail_to: 'ops@sinoa.test',
    password_configured: true
  }));
  configApi.getSystemInfo.mockResolvedValue(response({
    php_version: '8.3', laravel_version: '12', environment: 'production', db_driver: 'mysql'
  }));
  configApi.updateGeneralConfig.mockResolvedValue(response({ app_name: 'SINOA', app_url: 'https://sinoa.test' }, 'Guardado.'));
  configApi.updateAlertConfig.mockResolvedValue(response({}));
  configApi.updateEmailConfig.mockResolvedValue(response({}));
  configApi.testEmailConfig.mockResolvedValue(response({}, 'Prueba enviada.'));
}

describe('configuration screens', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setActivePinia(createPinia());
    setResponses();
  });

  it('keeps every settings entry available when a secondary summary fails', async () => {
    configApi.getAlertConfig.mockRejectedValueOnce(new Error('forbidden'));
    const { default: ConfigView } = await import('./ConfigView.vue');
    const { el, unmount } = await mountView(ConfigView);

    expect(el.querySelector('[href="/config/general"]')).not.toBeNull();
    expect(el.querySelector('[href="/config/alerts"]')).not.toBeNull();
    expect(el.querySelector('[href="/config/email"]')).not.toBeNull();
    expect(el.querySelector('[href="/config/diagnostics"]')).not.toBeNull();

    unmount();
  });

  it('saves the existing general configuration payload from its focused screen', async () => {
    const { default: GeneralConfigView } = await import('./config/GeneralConfigView.vue');
    const { el, unmount } = await mountView(GeneralConfigView);
    const name = el.querySelector('[name="app_name"]');
    const url = el.querySelector('[name="app_url"]');
    name.value = 'SINOA Lab';
    name.dispatchEvent(new Event('input', { bubbles: true }));
    url.value = 'https://lab.sinoa.test';
    url.dispatchEvent(new Event('input', { bubbles: true }));
    el.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(configApi.updateGeneralConfig).toHaveBeenCalledWith({
      app_name: 'SINOA Lab', app_url: 'https://lab.sinoa.test'
    });

    unmount();
  });

  it('keeps the alert, email test, and diagnostics operations in their focused screens', async () => {
    const { default: AlertConfigView } = await import('./config/AlertConfigView.vue');
    const { default: EmailConfigView } = await import('./config/EmailConfigView.vue');
    const { default: DiagnosticsConfigView } = await import('./config/DiagnosticsConfigView.vue');

    const alert = await mountView(AlertConfigView);
    alert.el.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();
    expect(configApi.updateAlertConfig).toHaveBeenCalledWith(expect.objectContaining({
      mail_enabled: true, alert_threshold: 70
    }));
    alert.unmount();

    const email = await mountView(EmailConfigView);
    const testForm = email.el.querySelector('[data-test-email-form]');
    testForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();
    expect(configApi.testEmailConfig).toHaveBeenCalledWith({ test_email: 'ops@sinoa.test' });
    email.unmount();

    const diagnostics = await mountView(DiagnosticsConfigView);
    expect(diagnostics.el.textContent).toContain('mysql');
    diagnostics.unmount();
  });
});
