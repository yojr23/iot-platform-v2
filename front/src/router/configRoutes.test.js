import { describe, expect, it } from 'vitest';

import router from './index';

describe('configuration routes', () => {
  it('makes the verification-required state available to a newly registered guest', () => {
    const route = router.getRoutes().find((candidate) => candidate.name === 'verification-required');

    expect(route).toMatchObject({
      path: '/verification-required',
      meta: { publicOnly: true }
    });
  });

  it('protects the focused configuration screens for administrators', () => {
    const names = ['config-general', 'config-alerts', 'config-email', 'config-diagnostics'];

    names.forEach((name) => {
      const route = router.getRoutes().find((candidate) => candidate.name === name);

      expect(route?.meta).toMatchObject({ requiresAuth: true, requiresAdmin: true });
    });
  });

  it('allows any authenticated user to read metrics without admin requirement', () => {
    const route = router.getRoutes().find((candidate) => candidate.name === 'metrics');

    expect(route?.meta).toMatchObject({ requiresAuth: true });
    expect(route?.meta?.requiresAdmin).toBeUndefined();
  });
});
