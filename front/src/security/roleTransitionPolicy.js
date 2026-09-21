export function availableRoleTransitions({
  actorRoleCode,
  targetUser,
  roles,
  hasAssignPermission
}) {
  if (!hasAssignPermission || !actorRoleCode || !targetUser?.role?.code) {
    return [];
  }

  const targetRoleCode = targetUser.role.code;
  const assignableRoles = roles.filter(
    (role) => role.assignable && role.code !== targetRoleCode
  );

  if (actorRoleCode === 'superadmin') {
    return targetRoleCode === 'superadmin' ? [] : assignableRoles;
  }

  if (actorRoleCode === 'admin') {
    return targetRoleCode === 'user'
      ? assignableRoles.filter((role) => role.code === 'user')
      : [];
  }

  return [];
}
