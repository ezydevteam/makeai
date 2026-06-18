# User Notification & Email Preferences — Implementation Plan

## Current State

### Notification System
- **Channel:** Database + Broadcast (WebSocket/polling) via `InAppNotificationService`
- **Single class:** `InAppNotification` with categories: `system`, `credits`, `subscription`, `payment`, `affiliate`, `document`, `media`, `announcement`, `ai_tool`, `security`, `users`, `comments`, `license`, `ai`, `export`, `contact`, `billing`
- **Centralized events:** `NotificationEventService` defines all notification triggers
- **No per-user preferences** — users cannot opt out of any category

### Email System
- **Primary:** `MailService::send(slug, to, data)` → `SendTemplatedEmail` job → `Mail::html()`
- **Templates** in `mail_templates` table with categories: `auth`, `account`, `subscription`, `newsletter`, `custom`
- **Only opt-out:** `email_marketing` boolean on `users` table (newsletter only)
- **Auth emails** (OTP, password reset) are always sent — no toggle needed

### User Dashboard
- Settings pages: Profile, Security, Privacy, API Keys, Billing
- Privacy page already has: email marketing toggle, cookie preferences, data improvement toggle
- No dedicated notification preferences page

---

## Goals

1. Add a **Notification Preferences** page in user dashboard
2. Group similar notifications into logical categories
3. Allow users to toggle **in-app** and **email** delivery per group
4. **Exclude auth-related emails** from opt-in (always sent)
5. Respect existing `email_marketing` for newsletter campaigns

---

## Notification Groups

| Group | Key | In-App Events | Email Events |
|-------|-----|---------------|--------------|
| **Billing & Credits** | `billing` | Credits added/low/exhausted, payment received/failed, subscription active/canceled/expired, renewal reminder | Credits low, payment failed, subscription expiring |
| **Content & Documents** | `content` | Document ready, media ready, export ready | Document ready, export ready |
| **Security & Account** | `security` | Password changed, new login detected | Password changed, new login detected |
| **Affiliate & Rewards** | `affiliate` | Referral reward earned, payout approved/canceled | Payout approved |
| **Product Updates** | `updates` | Admin announcements, new tool launched | Announcements, new features |
| **Admin Messages** | `admin` | Admin-sent notifications | Admin-sent emails |

> **Auth emails** (`email_verify_otp`, `reset_password_otp`, `password_changed` security email) are **always sent** — not toggleable.

---

## Implementation Steps

### Phase 1: Database & Model

#### 1.1 Migration: Add `notification_preferences` column to `users`
```php
// database/migrations/xxxx_add_notification_preferences_to_users_table.php
$table->json('notification_preferences')->nullable();
```

**Default structure** (all enabled by default):
```json
{
  "in_app": {
    "billing": true,
    "content": true,
    "security": true,
    "affiliate": true,
    "updates": true,
    "admin": true
  },
  "email": {
    "billing": true,
    "content": true,
    "security": true,
    "affiliate": true,
    "updates": true,
    "admin": true
  }
}
```

#### 1.2 Update `User` model
- Add `notification_preferences` to `$casts` as `array`
- Add helper methods:
  - `wantsInAppNotification(string $group): bool`
  - `wantsEmailNotification(string $group): bool`
  - `notificationPreferences(): array` (returns with defaults merged)

---

### Phase 2: Backend — Notification Service Updates

#### 2.1 Update `InAppNotificationService`
- Before sending in-app notification, check `user->wantsInAppNotification($group)`
- Map notification `category` to preference `group`:
  - `credits`, `subscription`, `payment`, `billing` → `billing`
  - `document`, `media`, `export` → `content`
  - `security` → `security`
  - `affiliate` → `affiliate`
  - `announcement`, `ai_tool`, `system` → `updates`
  - (admin-sent) → `admin`

#### 2.2 Update `MailService` / Email Jobs
- Before sending email, check `user->wantsEmailNotification($group)`
- Add group parameter to `MailService::send()` or create wrapper
- Map template slugs to groups:
  - `credits_low`, payment/subscription templates → `billing`
  - Document/export ready templates → `content`
  - Password changed, new login → `security`
  - Payout approved → `affiliate`
  - Announcements, newsletters → `updates`
  - `admin_notification` → `admin`
- **Skip check for auth templates** (`email_verify_otp`, `reset_password_otp`, 2FA OTP)

#### 2.3 Update `NotificationEventService`
- Pass group info when dispatching notifications
- Or add a mapping method: `getGroupForCategory(string $category): string`

---

### Phase 3: Backend — API Endpoints

#### 3.1 New Controller: `User\NotificationPreferencesController`
```php
// routes/web.php (inside user.dashboard middleware group)
Route::get('/user/dashboard/notifications/preferences', [NotificationPreferencesController::class, 'index'])->name('user.dashboard.notifications.preferences');
Route::put('/user/dashboard/notifications/preferences', [NotificationPreferencesController::class, 'update']);
```

**Methods:**
- `index()` — Return Inertia page with current preferences
- `update(Request $request)` — Validate and save preferences

**Validation rules:**
```php
[
  'in_app.billing' => 'boolean',
  'in_app.content' => 'boolean',
  'in_app.security' => 'boolean',
  'in_app.affiliate' => 'boolean',
  'in_app.updates' => 'boolean',
  'in_app.admin' => 'boolean',
  'email.billing' => 'boolean',
  'email.content' => 'boolean',
  'email.security' => 'boolean',
  'email.affiliate' => 'boolean',
  'email.updates' => 'boolean',
  'email.admin' => 'boolean',
]
```

---

### Phase 4: Frontend — Vue Components

#### 4.1 New Page: `resources/js/Pages/User/NotificationPreferences.vue`

**Layout:**
```
┌─────────────────────────────────────────────────────────────┐
│  Notification Preferences                                   │
│  Control how and when you receive notifications             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Group           │ In-App  │ Email                   │   │
│  ├─────────────────┼─────────┼─────────────────────────┤   │
│  │ Billing &       │ [toggle]│ [toggle]                │   │
│  │ Credits         │         │ Credits alerts, payment │   │
│  │                 │         │ confirmations, etc.     │   │
│  ├─────────────────┼─────────┼─────────────────────────┤   │
│  │ Content &       │ [toggle]│ [toggle]                │   │
│  │ Documents       │         │ Document processing,    │   │
│  │                 │         │ export ready, etc.      │   │
│  ├─────────────────┼─────────┼─────────────────────────┤   │
│  │ Security &      │ [toggle]│ [toggle]                │   │
│  │ Account         │         │ Password changes, new   │   │
│  │                 │         │ login alerts            │   │
│  ├─────────────────┼─────────┼─────────────────────────┤   │
│  │ Affiliate &     │ [toggle]│ [toggle]                │   │
│  │ Rewards         │         │ Commission earned,      │   │
│  │                 │         │ payout notifications    │   │
│  ├─────────────────┼─────────┼─────────────────────────┤   │
│  │ Product Updates │ [toggle]│ [toggle]                │   │
│  │                 │         │ Announcements, new      │   │
│  │                 │         │ features, tips          │   │
│  ├─────────────────┼─────────┼─────────────────────────┤   │
│  │ Admin Messages  │ [toggle]│ [toggle]                │   │
│  │                 │         │ Direct messages from    │   │
│  │                 │         │ our team                │   │
│  └─────────────────┴─────────┴─────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ℹ️ Security-critical emails (password reset, 2FA)   │   │
│  │ are always sent to keep your account safe.          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  [Save Preferences]                                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Component structure:**
- Use existing `AppToggle` or similar switch component
- Group rows with icon, name, description, and two toggles
- Info banner about auth emails
- Save button with loading state

#### 4.2 Update Sidebar Navigation

Add to `UserDashboardLayout.vue` sidebar under Account section or as standalone:
```
🔔 Notifications ▼
   ├─ Inbox              → /notifications
   └─ Preferences        → /user/dashboard/notifications/preferences
```

Or add as a sub-item under existing Notifications link.

#### 4.3 Translations

Add translation keys for:
- Page title, description
- Group names and descriptions
- Toggle labels ("In-App", "Email")
- Info banner text
- Save button, success message

---

### Phase 5: Migration of Existing `email_marketing`

- On migration, populate `notification_preferences.email.updates` from `email_marketing`
- Keep `email_marketing` column for backward compatibility with newsletter campaigns
- Or deprecate `email_marketing` and update `SendNewsletterCampaign` to check `notification_preferences.email.updates`

---

## Files to Create/Modify

### New Files
| File | Purpose |
|------|---------|
| `database/migrations/xxxx_add_notification_preferences_to_users_table.php` | Add JSON column |
| `app/Http/Controllers/User/NotificationPreferencesController.php` | Handle preferences page |
| `resources/js/Pages/User/NotificationPreferences.vue` | Preferences UI |

### Modified Files
| File | Changes |
|------|---------|
| `app/Models/User.php` | Add cast, helper methods |
| `app/Services/InAppNotificationService.php` | Check preferences before sending |
| `app/Services/MailService.php` | Check preferences before sending email |
| `app/Services/NotificationEventService.php` | Add group mapping |
| `routes/web.php` | Add preference routes |
| `resources/js/Layouts/UserDashboardLayout.vue` | Add sidebar link |
| `resources/js/Pages/User/Privacy.vue` | Optionally link to new preferences page |
| `lang/en.json` (or translation files) | Add translation keys |

---

## Testing Checklist

- [ ] New user gets default preferences (all enabled)
- [ ] User can toggle individual groups on/off
- [ ] In-app notifications respect preferences
- [ ] Emails respect preferences (except auth)
- [ ] Auth emails (OTP, password reset) always sent
- [ ] Newsletter campaigns respect `email.updates` preference
- [ ] Preferences persist after page reload
- [ ] Admin announcements respect `updates` preference
- [ ] Credit alerts respect `billing` preference
- [ ] Document ready notifications respect `content` preference

---

## Future Enhancements (Out of Scope)

- Per-event granularity (e.g., only "credits low" but not "credits added")
- Quiet hours / do not disturb schedule
- Email digest (daily/weekly summary)
- Push notifications (browser/ mobile)
- SMS notifications
- Notification history/archive
