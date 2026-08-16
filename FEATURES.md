# Smart Gate — Complete Feature Inventory

## Public / Landing
- Minimalist Golden Homes landing page.
- Large `G` logo.
- Login, Guest registration, and credential/status checking.
- Light/dark mode.
- Responsive mobile layout and iOS-style frosted UI.
- Footer: Golden Homes 2026 / KUN3H0/BISM4RCK 2026.

## Authentication & Accounts
- Resident, guard, admin, and super-admin roles.
- Email/password login with hide/show password.
- Persistent authentication across reloads.
- Logout and account settings.
- Active/inactive/suspended/locked states.
- Five failed attempts -> one-hour lock.
- Admin lock/unlock controls.
- Password/email management.
- Account deletion confirmation.
- Admins cannot delete themselves.
- KUN3H0 cannot be deleted.
- Super-admin accounts cannot be deleted.

## Resident Dashboard
- Phase, block, lot, letter, and account status.
- Up to 20 vehicles.
- Vehicle types: CAR, MOTORCYCLE, TRUCK, TRICYCLE, EBIKE, OTHER.
- Custom plates, color management, vehicle deletion.
- Guest pre-registration with automatic approval.
- Guest barcode + six-character code.
- Guest stay duration and expiration.
- Guest credential revocation.
- Guest history and notifications.
- Account settings.

## Guest System
- Guest registration with phase and separate block/lot/letter.
- Optional ID upload.
- Up to 10 guest vehicles.
- Stay duration in days.
- Barcode and six-character code.
- Expiration and revocation.
- Status checking.
- Entry/exit validation.
- Expired/revoked credentials are denied.
- Guest history.

## Guard Dashboard
- Entry/exit RFID reader online/offline status.
- RFID heartbeats.
- Live entry/exit gate animation.
- Gate override in the dashboard overview.
- Emergency entry/exit override and acknowledgement.
- Barcode scanner popup with gate selection.
- RFID result popup with gate, account, vehicle plate, and opened/denied result.
- Ten recent gate logs.
- Gate logs, filtering, and automatic updates.
- 50 gate logs per page.
- Walk-in guests.
- Blacklist management.
- Incident center and notifications.

## Admin Dashboard
- Gate monitoring and dashboard gate override.
- RFID reader monitoring.
- Gate logs with filtering and 50-per-page pagination.
- Account logs with 50-per-page pagination.
- Resident/staff vehicle separation.
- Add/manage/delete vehicles and edit color.
- Up to 20 vehicles per account.
- Role-aware vehicle assignment.
- Resident phase/block/lot/letter.
- Guard gate assignment 1/2/3.
- Five total entry/exit gates.
- Resident/staff account separation.
- Sort residents by phase then block.
- Online/account status.
- Add-account flow with owner name, email, username, role and role-specific details.
- Manage-account popup, password/email controls, lock/unlock, delete confirmation.
- KUN3H0 deletion option hidden.
- Guest/blacklist notifications.
- Incident center.
- Admin-only CSV exports.
- Admin-only system health.
- RFID section placeholder: “To be added.”
- Settings.

## Gate Control
- Entry and exit gates.
- Live gate status and animation.
- RFID heartbeat monitoring.
- Emergency/manual overrides.
- Override acknowledgement.
- Automatic gate-log updates.
- Gate reason codes.

## Gate Reason Codes
- RFID_APPROVED
- RFID_UNREGISTERED
- VEHICLE_BLACKLISTED
- GUEST_APPROVED
- GUEST_PENDING
- GUEST_REJECTED
- GUEST_NOT_APPROVED
- GUEST_NOT_FOUND
- GUEST_BLACKLISTED
- GUEST_EXPIRED
- GUEST_REVOKED
- EMERGENCY_OVERRIDE
- MANUAL_OVERRIDE

## Incidents & Audit
- Blacklist hits, denied access, guest credential failures, overrides, and reader outages.
- Incident acknowledgement and notifications.
- Login/logout logs.
- Vehicle actions.
- Guest registration/revocation.
- Guard/admin gate actions.
- Blacklist actions.
- Account-management actions.
- Manila/GMT+8 timestamps.
- Protected audit logs.
- Only KUN3H0/super-admin can delete gate/account logs.

## Notifications
- Resident guest-request notifications.
- Guard/admin blacklist alerts.
- Dismiss/close controls.
- Automatic refresh.

## RFID Lifecycle
- Unassigned, Assigned, Active, Suspended, Lost, Revoked, Void.
- UID uniqueness.
- Account assignment.
- Optional vehicle assignment.
- Issue/void tracking.
- Admin RFID section.

## ESP32 / MQTT
- ESP32 heartbeat and gate command polling/completion.
- RFID scan reporting.
- Device ID, firmware, IP, last heartbeat.
- Wi-Fi/MQTT status.
- RSSI, free heap, uptime.
- Restart command.
- MQTT/Mosquitto integration.
- ESP32 device management and diagnostics are super-admin only.

## Super Admin Command Center
- Database, MQTT, RFID reader, and Cloudflare status.
- ESP32 telemetry and device controls.
- Active overrides.
- Recent incidents.
- System diagnostics.
- Gate statistics.
- Disaster-recovery tools.
- Backup/restore.
- Backup integrity testing.
- Protected audit-log deletion.

## Statistics
- Approved today.
- Denied today.
- Guest requests today.
- Entry/exit activity.
- Seven-day activity.
- Daily approved/denied breakdown.
- Gate activity trends.
- Statistics are super-admin only.

## Backup & Disaster Recovery
- SQL database backup/download.
- Restore from SQL backup with confirmation.
- Backup integrity/disaster-recovery test.
- Validation of important Smart Gate tables.
- Super-admin only.

## Security
- Role-based authorization.
- Super-admin-only advanced controls.
- Admin-only exports/system health.
- Super-admin-only diagnostics and backups.
- Protected KUN3H0 account.
- Account lockout.
- Guest expiration/revocation.
- Audit logging.

## UI / UX
- Minimalist macOS/iOS-inspired appearance.
- iOS-style switches.
- Frosted/blurred settings dropdown.
- Minimalist gear button.
- Responsive mobile view.
- Mobile bottom navigation.
- Desktop collapsible sidebar.
- Dashboard controls remain on the dashboard where specified.
- Popups instead of unnecessary new pages.

## Infrastructure / API
- Docker Compose.
- Laravel, PHP, Nginx, MySQL, Mosquitto MQTT.
- Cloudflare Tunnel support.
- HTTPS production configuration.
- Environment-based configuration.
- Database migrations.
- Automated container startup tasks.
- Authentication, account, vehicle, guest, gate, RFID, heartbeat, health, and override APIs.
- MQTT hardware integration.

## Deliberate Non-Features
- No separate gate-maintenance mode.
- Full RFID workflow can remain staged behind the RFID section placeholder.
- README is intentionally not changed by this rebuild.
