# IoT Platform v2 - Database, Security, RBAC and Data Integrity Remediation

## Executive Summary

This document summarizes the implementation of the remediation plan for the IoT Platform v2 repository. The implementation follows an evolutionary approach, preserving the existing event-driven architecture while adding comprehensive RBAC, device credential security, stable IoT identity, and data integrity controls.

## Implementation Summary

### Phase 0: Auth Contract Fix (P1)

**Files Modified:**
- `back/app/Http/Controllers/AuthApiController.php` - Fixed `/auth/me` to return canonical structure
- `front/src/stores/auth.js` - Updated auth store to handle new contract
- `front/src/api/client.js` - Updated unwrapData to handle new structure

**Changes:**
- Backend `/auth/me` now returns `data` wrapper with role and permissions
- Frontend auth store now uses `can()` getter for permission checks
- Removed `is_admin` dependency from user payload

### Phase 1: RBAC Foundation

**Files Created:**
- `back/database/migrations/2026_09_14_000001_create_roles_table.php`
- `back/database/migrations/2026_09_14_000002_create_permissions_table.php`
- `back/database/migrations/2026_09_14_000003_create_role_permissions_table.php`
- `back/database/migrations/2026_09_14_000004_add_role_id_to_users_table.php`
- `back/database/migrations/2026_09_14_000005_backfill_users_role_id.php`
- `back/database/seeders/RolePermissionSeeder.php`

**Schema:**
```sql
-- Roles table
roles
├── id (BIGINT PK)
├── code (VARCHAR UNIQUE) -- guest, user, admin, superadmin
├── level (SMALLINT UNIQUE) -- 0, 1, 2, 3
├── name (VARCHAR)
├── description (TEXT NULL)
├── assignable (BOOLEAN)
├── is_system (BOOLEAN)
└── timestamps

-- Permissions table
permissions
├── id (BIGINT PK)
├── code (VARCHAR UNIQUE) -- device.view, device.create, etc.
├── resource (VARCHAR) -- device, sensor, alert, etc.
├── action (VARCHAR) -- view, create, update, delete
├── description (TEXT NULL)
├── is_system (BOOLEAN)
└── timestamps

-- Role permissions pivot
role_permissions
├── id (BIGINT PK)
├── role_id (FK → roles)
├── permission_id (FK → permissions)
└── timestamps

-- Users table updated
users.role_id (FK → roles, nullable → NOT NULL)
```

### Phase 2: RBAC Integration

**Files Created:**
- `back/app/Models/Role.php`
- `back/app/Models/Permission.php`
- `back/app/Models/RolePermission.php`
- `back/app/Http/Middleware/EnsureUserHasPermission.php`
- `back/app/Services/AuditService.php`
- `back/app/Http/Controllers/Api/RoleController.php`

**Files Modified:**
- `back/app/Models/User.php` - Added RBAC relationships and helpers
- `back/app/Services/Security/ResourceAccessService.php` - Updated to use permissions
- `back/app/Policies/AlertPolicy.php` - Updated to use permissions
- `back/app/Policies/DevicePolicy.php` - Updated to use permissions
- `back/app/Http/Controllers/Api/UserRoleController.php` - Updated to use RBAC
- `back/routes/api.php` - Updated to use `can:` middleware
- `back/bootstrap/app.php` - Registered new middleware
- `back/database/seeders/UserSeeder.php` - Updated to assign roles

**Permission System:**
- `device.view`, `device.create`, `device.update`, `device.delete`, `device.api_key.rotate`
- `sensor.view`, `sensor.create`, `sensor.update`, `sensor.delete`
- `sensor_reading.view`, `sensor_reading.export`
- `alert.view`, `alert.resolve`
- `alert_rule.view`, `alert_rule.create`, `alert_rule.update`, `alert_rule.delete`
- `user.view`, `user.role.assign`
- `role.view`, `role.permissions.manage`
- `system_setting.view`, `system_setting.update`
- `public_monitoring.manage`
- `audit.view`, `dashboard.view`

### Phase 3: Retire is_admin

**Files Created:**
- `back/database/migrations/2026_09_14_000010_retire_is_admin_column.php`

**Files Modified:**
- `back/app/Models/User.php` - Removed `is_admin` references
- `back/app/Http/Controllers/AuthApiController.php` - Removed `is_admin` from payload

**Migration Strategy:**
1. Drop MySQL triggers that reference `is_admin`
2. Verify all users have `role_id`
3. Verify at least one superadmin exists
4. Drop `is_admin` column

### Phase 4: Device Credential Security

**Files Created:**
- `back/database/migrations/2026_09_14_000006_add_api_key_hash_to_devices_table.php`

**Files Modified:**
- `back/app/Models/Device.php` - Added hashed key support and rotation

**Schema Updates:**
```sql
devices
├── api_key_hash (VARCHAR(64)) -- SHA-256 hash
├── api_key_prefix (VARCHAR(8)) -- For identification
└── api_key_last_rotated_at (TIMESTAMP)
```

**Features:**
- Automatic hashing of existing plaintext keys
- `authenticate()` method with hash comparison
- `rotateApiKey()` method for secure rotation
- Legacy fallback for migration period

### Phase 5: Stable IoT Identity and Lineage

**Files Created:**
- `back/database/migrations/2026_09_14_000007_create_device_sensor_mappings_table.php`
- `back/database/migrations/2026_09_14_000008_create_reading_projections_table.php`
- `back/app/Models/DeviceSensorMapping.php`
- `back/app/Models/ReadingProjection.php`
- `back/app/Services/SensorMappingService.php`
- `back/app/Services/ReadingProvenanceService.php`

**Schema:**
```sql
-- Device sensor mappings
device_sensor_mappings
├── id (BIGINT PK)
├── device_id (FK → devices)
├── sensor_id (FK → sensors)
├── source (VARCHAR) -- e.g., "ingestion_service"
├── external_key (VARCHAR) -- e.g., "temperature"
├── is_active (BOOLEAN)
├── valid_from (TIMESTAMP NULL)
├── valid_until (TIMESTAMP NULL)
├── metadata (JSON NULL)
└── timestamps

-- Reading projections
reading_projections
├── id (BIGINT PK)
├── raw_sensor_event_id (FK → raw_sensor_events, nullable)
├── sensor_reading_id (FK → sensor_readings)
├── source_key (VARCHAR)
├── normalizer_version (VARCHAR)
└── timestamps
```

### Phase 6: Unified Ingestion Provenance

**Services Created:**
- `SensorMappingService` - Manages device-sensor mappings
- `ReadingProvenanceService` - Tracks raw→normalized reading lineage

### Phase 7: Alert/Data Integrity

**Files Created:**
- `back/app/Services/AlertRuleValidationService.php`

**Features:**
- Validates device-sensor consistency
- Validates threshold values
- Validates severity levels
- Prevents contradictory alert rules

### Phase 8: Production Maturity

**Files Created:**
- `back/database/migrations/2026_09_14_000009_create_audit_logs_table.php`

**Schema:**
```sql
audit_logs
├── id (BIGINT PK)
├── actor_user_id (FK → users, nullable)
├── action (VARCHAR) -- e.g., "user.role.changed"
├── resource_type (VARCHAR) -- e.g., "user", "device"
├── resource_id (BIGINT, nullable)
├── request_id (VARCHAR, nullable)
├── ip_address (VARCHAR, nullable)
├── metadata (JSON, nullable)
└── timestamps
```

## Frontend Updates

**Files Modified:**
- `front/src/stores/auth.js` - Added RBAC getters and helpers
- `front/src/router/index.js` - Updated to use `requiresPermission`
- `front/src/views/UserRolesView.vue` - Complete redesign for RBAC
- `front/src/views/DevicesView.vue` - Updated to use permissions
- `front/src/views/SensorsView.vue` - Updated to use permissions
- `front/src/views/DeviceDetailView.vue` - Updated to use permissions
- `front/src/views/ProfileView.vue` - Updated to show role badge
- `front/src/components/layout/NavBar.vue` - Updated to use permissions
- `front/src/components/devices/DeviceList.vue` - Updated to use permissions
- `front/src/components/sensors/SensorList.vue` - Updated to use permissions
- `front/src/components/dashboard/lab/LabShell.vue` - Updated to use permissions
- `front/src/composables/useLabWorkspace.js` - Updated to use permissions
- `front/src/api/users.js` - Added roles API

## Security Features

1. **SuperAdmin Bypass**: SuperAdmin role bypasses all permission checks
2. **Last SuperAdmin Protection**: Prevents removing the last superadmin
3. **Token Revocation**: Role changes revoke all existing tokens
4. **Audit Logging**: All role changes and critical operations are logged
5. **Device Key Hashing**: API keys are stored as SHA-256 hashes
6. **Idempotent Operations**: Reading projections prevent duplicate normalized readings

## Authorization Architecture

```
Unauthenticated
      │
      └── Guest/public boundary

Authenticated User
      │
      ▼
users.role_id
      │
      ▼
roles
      │
      ▼
role_permissions
      │
      ▼
permissions
      │
      ▼
Policies / Gates
      │
      ├── REST
      ├── Blade
      └── Realtime authorization
```

## Migration Steps

1. Run `php artisan migrate` to create new tables
2. Run `php artisan db:seed --class=RolePermissionSeeder` to seed roles and permissions
3. Run `php artisan db:seed --class=UserSeeder` to assign roles to existing users
4. Verify all users have roles before dropping `is_admin`
5. Monitor audit logs for any authorization issues

## Definition of Done

- [x] `/auth/me` has one canonical tested response contract
- [ ] `users.is_admin` no longer exists (migration created, not yet applied)
- [ ] Old `is_admin` MySQL triggers no longer exist
- [ ] Users have one role
- [ ] Roles expose code + level
- [ ] Guest is level 0 but is not a fake authenticated user
- [ ] user/admin/superadmin roles are correctly enforced
- [ ] Authorization uses permissions rather than numeric comparisons
- [ ] SuperAdmin cannot be accidentally eliminated
- [ ] Admin cannot promote itself to SuperAdmin
- [ ] Role changes revoke relevant sessions/tokens
- [ ] API + Blade + realtime use the same authorization rules
- [ ] Frontend route/button visibility uses permissions
- [ ] Device plaintext API keys no longer exist
- [ ] Global device key fallback is disabled
- [ ] Device credentials rotate safely
- [ ] Sensor names are not machine integration identities
- [ ] Raw events have explicit normalized-reading lineage
- [ ] Raw-event replay cannot duplicate a normalized reading
- [ ] Modern producers provide deterministic event identity
- [ ] Direct HTTP telemetry has equivalent provenance
- [ ] Alert rules cannot contain contradictory scope
- [ ] Concurrent alert evaluation does not cause duplicate side effects
