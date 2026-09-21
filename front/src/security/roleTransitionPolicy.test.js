import { describe, expect, it } from 'vitest';

import { availableRoleTransitions } from './roleTransitionPolicy';

const roles = [
  { code: 'superadmin', name: 'Super Administrator', assignable: false },
  { code: 'admin', name: 'Administrator', assignable: true },
  { code: 'user', name: 'User', assignable: true },
  { code: 'guest', name: 'Guest', assignable: false }
];

function transitions(actorRoleCode, targetRoleCode, hasAssignPermission = true) {
  return availableRoleTransitions({
    actorRoleCode,
    targetUser: { role: { code: targetRoleCode } },
    roles,
    hasAssignPermission
  }).map((role) => role.code);
}

describe('availableRoleTransitions', () => {
  it('offers a superadmin viewing a user only the administrator transition', () => {
    expect(transitions('superadmin', 'user')).toEqual(['admin']);
  });

  it('offers a superadmin viewing an administrator only the user transition', () => {
    expect(transitions('superadmin', 'admin')).toEqual(['user']);
  });

  it('offers no transition for a superadmin target, including self', () => {
    expect(transitions('superadmin', 'superadmin')).toEqual([]);
  });

  it('offers no effective transition to an administrator managing a user', () => {
    expect(transitions('admin', 'user')).toEqual([]);
  });

  it('offers no transition to an administrator managing an administrator', () => {
    expect(transitions('admin', 'admin')).toEqual([]);
  });

  it('offers no transition to a standard user', () => {
    expect(transitions('user', 'user')).toEqual([]);
  });

  it('requires the user.role.assign permission', () => {
    expect(transitions('superadmin', 'user', false)).toEqual([]);
  });

  it('never offers roles marked non-assignable', () => {
    expect(transitions('superadmin', 'admin')).not.toContain('superadmin');
    expect(transitions('superadmin', 'admin')).not.toContain('guest');
  });
});
