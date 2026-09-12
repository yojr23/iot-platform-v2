import { createApp, nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const authApi = vi.hoisted(() => ({ register: vi.fn() }));
const router = vi.hoisted(() => ({ push: vi.fn() }));

vi.mock('@/api/auth', () => authApi);
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal()),
  useRouter: () => router
}));

const mountedApps = [];
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function mountRegisterView() {
  const { default: RegisterView } = await import('./RegisterView.vue');
  const host = document.createElement('div');
  const app = createApp(RegisterView);
  app.component('RouterLink', { props: ['to'], template: '<a><slot /></a>' });
  app.mount(host);
  mountedApps.push(app);
  await nextTick();

  return host;
}

beforeEach(() => {
  vi.clearAllMocks();
});

afterEach(() => mountedApps.splice(0).forEach((app) => app.unmount()));

describe('RegisterView', () => {
  it('redirects a verification-required registration to the verification state with the submitted email', async () => {
    authApi.register.mockResolvedValue({ data: { verification_required: true } });
    const host = await mountRegisterView();
    const email = host.querySelector('[name="email"]');

    email.value = 'new.user@gmail.com';
    email.dispatchEvent(new Event('input', { bubbles: true }));
    host.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(router.push).toHaveBeenCalledWith({
      name: 'verification-required',
      query: { email: 'new.user@gmail.com' }
    });
  });
});
