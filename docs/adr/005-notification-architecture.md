# ADR-005: Three-Tier Notification Architecture

## Status

Accepted (DBP-001, DBP-002)

## Context

The original notification system had conceptual confusion:

1. **NotificationPreference** stored both user preferences AND landlord defaults
2. **Setting** stored provider credentials mixed with other settings
3. **Four UI locations** for notification configuration (confusing for users)
4. Provider config (Twilio API keys) mixed with preference defaults

This caused:
- Maintenance nightmares when updating notification logic
- User confusion about where to configure what
- Risk of exposing provider credentials

## Decision

Implement three-tier architecture with clear separation:

### Tier 1: Provider Configuration (`NotificationProviderConfig`)

**Purpose**: Store provider credentials (API keys, sender IDs)
**Owner**: Landlord
**Location**: Notifications Center > Settings tab

```php
NotificationProviderConfig::create([
    'landlord_id' => $landlord->id,
    'sms_provider' => 'africastalking',
    'sms_api_key' => encrypt($apiKey),
    'sms_sender_id' => 'PropManager',
    // ...
]);
```

### Tier 2: Default Preferences (`NotificationDefaults`)

**Purpose**: Landlord-set defaults for new tenants
**Owner**: Landlord
**Location**: Settings > Notifications tab

```php
NotificationDefaults::create([
    'landlord_id' => $landlord->id,
    'rent_reminder_days_before' => 3,
    'enable_sms_notifications' => true,
    'quiet_hours_enabled' => true,
    'quiet_hours_start' => '22:00',
    'quiet_hours_end' => '07:00',
    // ...
]);
```

### Tier 3: User Preferences (`NotificationPreference`)

**Purpose**: Individual user overrides
**Owner**: Each user (landlord, caretaker, tenant)
**Location**: Profile > Notifications tab

```php
NotificationPreference::create([
    'user_id' => $user->id,
    'email_enabled' => true,
    'sms_enabled' => false,  // User opted out
    'quiet_hours_start' => '22:00',
    'quiet_hours_end' => '07:00',
    // ...
]);
```

### Resolution Order

When sending notification:
1. Check user's `NotificationPreference` (highest priority)
2. Fall back to landlord's `NotificationDefaults`
3. Use system defaults from config

## Consequences

### Positive

- **Clear ownership**: Each table has single responsibility
- **Security**: Credentials separate from preferences
- **Intuitive UX**: Three locations (Notifications Center > Settings tab for provider config, Settings > Notifications tab for defaults, Profile > Notifications tab for user preferences)
- **Testable**: Each tier can be tested independently
- **Maintainable**: Changes to one tier don't affect others

### Negative

- Migration required from old dual-purpose tables
- More queries for preference resolution
- Developers need to understand three-tier hierarchy

### Neutral

- Feature flag used during migration (`notification_v2`)
- Repository pattern handles dual-write during transition
- Old tables deprecated but not removed immediately

## References

- DBP-001: Create Unified Notification Configuration Architecture
- DBP-002: Consolidate Notification Settings UI to Single Location
- DBP-024: Cleanup Legacy Notification Config Code
