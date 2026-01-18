# Affiliate System with Paystack Split Payments

## Overview
Implement a robust affiliate system where **affiliates** (a separate user type) can invite others using unique referral codes. When referred users make purchases, the affiliate earns a configurable commission automatically through Paystack split payments.

**Key distinction:** Affiliates are a separate user role with their own isolated dashboard, NOT regular users who happen to refer others.

## Implementation Status (Repo Audit)
Current code implements a **referral program for regular users**, not a separate affiliate role. The items below reflect what exists today in the repo.

### Implemented (Referral Program for Regular Users)
- [x] Database: referral fields on users; referral earnings; referral bank accounts (`database/migrations/2026_01_17_162526_add_referral_fields_to_users_table.php`, `database/migrations/2026_01_17_162526_create_referral_earnings_table.php`, `database/migrations/2026_01_17_162526_create_referral_bank_accounts_table.php`)
- [x] Models: `app/Models/ReferralEarning.php`, `app/Models/ReferralBankAccount.php`; referral relationships and helpers in `app/Models/User.php`; `referralEarning()` in `app/Models/Payment.php`
- [x] Services: `app/Services/ReferralService.php`; Paystack subaccount + split init in `app/Services/PaystackService.php`
- [x] Controllers: `app/Http/Controllers/ReferralController.php`; `app/Http/Controllers/Admin/AdminReferralController.php`; referral handling in `app/Http/Controllers/PaymentController.php` and `app/Http/Controllers/Auth/RegisteredUserController.php`
- [x] Routes: `routes/referral.php` (included in `routes/web.php`); admin referral routes in `routes/web.php`
- [x] Frontend: user referral dashboard `resources/js/pages/Referrals/Index.vue`; admin pages `resources/js/pages/Admin/Referrals/Index.vue`, `resources/js/pages/Admin/Referrals/Users.vue`, `resources/js/pages/Admin/Referrals/Earnings.vue`; referral notice in `resources/js/pages/auth/Register.vue`
- [x] Settings: referral SystemSettingSeeder keys in `database/seeders/SystemSettingSeeder.php`; admin settings list in `resources/js/pages/Admin/System/Settings.vue`

### Not Implemented (Affiliate-Specific Scope From This Plan)
- [ ] Affiliate role + dual-access model; affiliate-only dashboard and auth flows
- [ ] Affiliate invites, applications, approvals, and user status fields
- [ ] Affiliate admin pages (invites, requests, list) and middleware
- [ ] Affiliate registration UI, promo popup, and dashboard switcher
- [ ] Affiliate routes, models, controllers, and tests

---

## Concrete Build Checklist (Missing Affiliate-Specific Features)

### A) Data Model and Roles
- [ ] Add affiliate fields to users table (status, requested/approved timestamps, notes)
- [ ] Create affiliate invites table
- [ ] Create affiliate invite redemptions table
- [ ] Add affiliate role + permissions in `database/seeders/AdminRoleSeeder.php`
- [ ] Seed affiliate settings in `database/seeders/SystemSettingSeeder.php`
- [ ] Create models: `AffiliateInvite`, `AffiliateInviteRedemption`
- [ ] Extend `app/Models/User.php` with affiliate-specific helpers:
  - `isAffiliate()`, `isPureAffiliate()`, `hasDualAccess()`
  - `canRequestAffiliateAccess()`, `hasPendingAffiliateRequest()`

### B) Services
- [ ] Create `app/Services/AffiliateService.php` with:
  - Invite creation/validation/redeem
  - Affiliate request/approve/reject
  - Promo popup eligibility + dismiss tracking
- [ ] Extend `app/Services/ReferralService.php` as needed for affiliate-only dashboard stats
- [ ] Confirm `app/Services/PaystackService.php` already supports subaccounts + split

### C) Middleware and Access Rules
- [ ] Create `app/Http/Middleware/EnsureUserIsAffiliate.php`
- [ ] Enforce affiliate-only access to affiliate dashboard routes
- [ ] Block pure affiliates from regular project routes (redirect to affiliate dashboard)

### D) Controllers and Routes
- [ ] Create `app/Http/Controllers/AffiliateController.php`
  - dashboard data
  - earnings list
  - referrals list
  - bank verify/setup
- [ ] Create `app/Http/Controllers/AffiliateRequestController.php`
  - request affiliate access
  - request status
  - dismiss promo
- [ ] Create `app/Http/Controllers/Auth/AffiliateAuthController.php`
  - invite validation
  - affiliate registration
- [ ] Create admin controllers:
  - `AdminAffiliateController` (stats, list, settings, custom rate)
  - `AdminAffiliateInviteController` (create/update/delete invites)
  - `AdminAffiliateRequestController` (approve/reject)
- [ ] Add `routes/affiliate.php`
- [ ] Wire affiliate routes in `bootstrap/app.php` or `routes/web.php`

### E) Payment Flow Alignment
- [ ] Ensure affiliate payments use split with affiliate subaccount
- [ ] Record ReferralEarning with affiliate referrer
- [ ] Handle refunds → mark referral earnings as refunded

### F) Frontend (Affiliate)
- [ ] `resources/js/pages/Affiliate/Dashboard.vue`
  - bank setup required view
  - stats, referral link, earnings, referrals
- [ ] `resources/js/pages/Affiliate/Earnings.vue`
  - table + filters + pagination
- [ ] `resources/js/pages/Affiliate/Register.vue`
  - affiliate-specific registration UI

### G) Frontend (User + Admin)
- [ ] `resources/js/components/AffiliatePromoPopup.vue`
- [ ] `resources/js/components/DashboardSwitcher.vue`
- [ ] Update user dashboard to show affiliate CTA + status
- [ ] `resources/js/pages/Admin/Affiliates/Index.vue`
- [ ] `resources/js/pages/Admin/Affiliates/List.vue`
- [ ] `resources/js/pages/Admin/Affiliates/Invites.vue`
- [ ] `resources/js/pages/Admin/Affiliates/Requests.vue`

### H) Validation, Edge Cases, and Notifications
- [ ] Prevent self-referral and duplicate applications
- [ ] Block referral codes without bank setup
- [ ] Enforce registration closed + invite-only mode
- [ ] Add admin/audit logs for approvals and commission changes
- [ ] Optional: email/notification on approval/rejection

### I) Tests
- [ ] `tests/Feature/AffiliateSystemTest.php`
- [ ] Coverage for:
  - affiliate registration (open + invite)
  - request/approve/reject flow
  - split payment + earning creation
  - access control (pure vs dual)
## User Types & Access Model

### 1. Pure Affiliates
- Register specifically as affiliates (via affiliate registration when open, or via admin invite)
- **Only see** the affiliate dashboard - no access to project features
- Focus: share referral link, track earnings, manage bank account

### 2. Regular Users with Affiliate Access (Dual-Access)
- Start as regular project users
- Can **request** to become an affiliate
- Admin approves → user gets `affiliate` role in addition to their regular access
- Can **switch between** user dashboard and affiliate dashboard
- Promotional popups encourage them to apply

### 3. Admin Controls
- **Affiliate registration open/closed** (separate from regular user registration)
- **Generate affiliate invite links** (single-use or reusable) when registration is closed
- Approve/reject affiliate applications from regular users
- Set custom commission rates per affiliate

---

## Key Features
- Unique referral codes per affiliate (format: 2-letter name prefix + 6 random chars)
- **Bank account setup required** before affiliate can receive commissions
- Configurable commission percentage via admin dashboard (default: 10%)
- Automatic split payments to affiliate's bank account via Paystack
- Admin dashboard for managing settings, invites, and viewing analytics

## Design Decisions
- **No referee bonus**: New users signing up with referral code don't receive bonus words
- **Bank setup required**: Affiliates must set up bank account to receive earnings
- **Default commission**: 10% of payment amount goes to affiliate
- **Per-affiliate commission override**: Admin can set custom commission rate for specific affiliates

---

## Database Changes

### 1. Migration: Add affiliate fields to users table
**File:** `database/migrations/2026_01_XX_add_affiliate_fields_to_users_table.php`

| Column | Type | Description |
|--------|------|-------------|
| `referral_code` | string(10), unique, nullable | Affiliate's unique referral code |
| `referred_by` | foreignId, nullable | ID of referring affiliate |
| `referral_commission_rate` | decimal(5,2), nullable | Custom commission % (null = use default) |
| `paystack_subaccount_code` | string, nullable | Paystack subaccount for payouts |
| `referral_bank_setup_complete` | boolean, default false | Bank setup status |
| `referred_at` | timestamp, nullable | When user was referred |
| `affiliate_status` | enum, nullable | null, pending, approved, rejected |
| `affiliate_requested_at` | timestamp, nullable | When user requested affiliate access |
| `affiliate_approved_at` | timestamp, nullable | When admin approved affiliate access |
| `affiliate_notes` | text, nullable | Admin notes on application |

### 2. Migration: Create referral_earnings table
Tracks each commission earned from referrals.

| Column | Type | Description |
|--------|------|-------------|
| `referrer_id` | foreignId | Affiliate who earns the commission |
| `referee_id` | foreignId | Who made the payment |
| `payment_id` | foreignId | The triggering payment |
| `payment_amount` | unsignedInteger | Original payment (kobo) |
| `commission_amount` | unsignedInteger | Commission earned (kobo) |
| `commission_rate` | decimal(5,2) | Rate at time of earning |
| `status` | enum | pending, paid, failed, refunded |
| `paystack_split_code` | string, nullable | Paystack split reference |
| `paystack_split_response` | json, nullable | API response data |

### 3. Migration: Create referral_bank_accounts table
Stores affiliate bank details for Paystack subaccounts.

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | foreignId | Account owner |
| `bank_code` | string | Paystack bank code |
| `bank_name` | string | Bank display name |
| `account_number` | string | 10-digit account |
| `account_name` | string | Verified name from Paystack |
| `subaccount_code` | string, unique | Paystack subaccount code |
| `is_verified` | boolean | Verification status |
| `is_active` | boolean | Active for payouts |

### 4. Migration: Create affiliate_invites table
Admin-generated invite codes for affiliate registration.

| Column | Type | Description |
|--------|------|-------------|
| `code` | string, unique | Invite code |
| `created_by` | foreignId | Admin who created it |
| `type` | enum | single_use, reusable |
| `max_uses` | unsignedInteger, nullable | Max uses (null = unlimited for reusable) |
| `uses` | unsignedInteger, default 0 | Current use count |
| `expires_at` | timestamp, nullable | Expiration date |
| `is_active` | boolean, default true | Active status |
| `note` | string, nullable | Admin note (e.g., "For John Doe") |

### 5. Migration: Create affiliate_invite_redemptions table
Tracks who used each affiliate invite.

| Column | Type | Description |
|--------|------|-------------|
| `invite_id` | foreignId | The invite used |
| `user_id` | foreignId | User who redeemed |
| `ip` | string, nullable | IP address |
| `user_agent` | string, nullable | Browser info |
| `redeemed_at` | timestamp | When redeemed |

### 6. Seeder: Add affiliate settings to SystemSetting
- `affiliate.enabled` - Enable/disable entire affiliate system (boolean)
- `affiliate.registration_open` - Allow public affiliate registration (boolean)
- `affiliate.commission_percentage` - Default commission rate (integer, default 10 = 10%)
- `affiliate.minimum_payment_amount` - Min payment for commission (kobo)
- `affiliate.fee_bearer` - Who pays Paystack fees (account/subaccount/all)
- `affiliate.promo_popup_enabled` - Show popup to regular users (boolean)
- `affiliate.promo_popup_delay_days` - Days after signup before showing popup (integer)

### 7. Seeder: Add affiliate role
Add `affiliate` role to `AdminRoleSeeder` with permissions:
- `affiliate.dashboard` - Access affiliate dashboard
- `affiliate.earnings` - View earnings
- `affiliate.bank` - Manage bank account

---

## Models

### 1. Update User model
**File:** [app/Models/User.php](app/Models/User.php)
- Add fillable fields: `affiliate_status`, `affiliate_requested_at`, `affiliate_approved_at`, `affiliate_notes`
- Add casts for new fields
- Add methods:
  - `isAffiliate()` - Check if user has affiliate role
  - `isPureAffiliate()` - Has affiliate role but NOT regular user features
  - `hasDualAccess()` - Has both affiliate role and regular access
  - `canRequestAffiliateAccess()` - Regular user who hasn't applied yet
  - `hasPendingAffiliateRequest()` - Application is pending
- Existing referral methods remain

### 2. Create ReferralEarning model
**File:** `app/Models/ReferralEarning.php`
- Status constants: PENDING, PAID, FAILED, REFUNDED
- Relationships to affiliate, referee, payment
- Scopes: paid(), forAffiliate()

### 3. Create ReferralBankAccount model
**File:** `app/Models/ReferralBankAccount.php`
- Relationship to user
- Masked account number accessor for display

### 4. Create AffiliateInvite model
**File:** `app/Models/AffiliateInvite.php`
- Type constants: SINGLE_USE, REUSABLE
- Relationships: createdBy (admin), redemptions
- Methods: `isValid()`, `canBeUsed()`, `markUsed()`

### 5. Create AffiliateInviteRedemption model
**File:** `app/Models/AffiliateInviteRedemption.php`
- Relationships: invite, user

### 6. Update Payment model
**File:** [app/Models/Payment.php](app/Models/Payment.php)
- Add `referralEarning()` hasOne relationship

---

## Services

### 1. Create ReferralService
**File:** `app/Services/ReferralService.php`

Core methods:
- `isEnabled()` - Check if affiliate system is active
- `isRegistrationOpen()` - Check if public affiliate registration is allowed
- `getDefaultCommissionRate()` - Get default percentage from SystemSetting
- `getCommissionRateForUser(User $affiliate)` - Get affiliate's rate (custom or default)
- `calculateCommission(int $paymentAmount, User $affiliate)` - Calculate commission
- `validateReferralCode(string $code)` - Validate code exists and affiliate has bank setup
- `linkReferral(User $newUser, string $code)` - Link new user to affiliate
- `paymentQualifiesForCommission(Payment $payment)` - Check eligibility
- `createEarningForPayment(Payment $payment)` - Create earning record
- `getDashboardData(User $user)` - Get affiliate stats for dashboard

### 2. Create AffiliateService
**File:** `app/Services/AffiliateService.php`

Methods:
- `createInvite(User $admin, string $type, ?int $maxUses, ?Carbon $expiresAt, ?string $note)` - Create invite
- `validateInvite(string $code)` - Check if invite is valid
- `redeemInvite(AffiliateInvite $invite, User $user)` - Mark invite as used
- `requestAffiliateAccess(User $user)` - Regular user requests to become affiliate
- `approveAffiliateRequest(User $user, User $admin)` - Admin approves request
- `rejectAffiliateRequest(User $user, User $admin, ?string $reason)` - Admin rejects
- `shouldShowPromoPopup(User $user)` - Check if promo popup should show
- `dismissPromoPopup(User $user)` - User dismisses popup

### 3. Update PaystackService
**File:** [app/Services/PaystackService.php](app/Services/PaystackService.php)

Add methods:
- `createSubaccount(businessName, bankCode, accountNumber)` - Create Paystack subaccount
- `resolveAccountNumber(accountNumber, bankCode)` - Verify bank account details
- `initializeTransactionWithSplit(...)` - Initialize split payment

---

## Controllers

### 1. Create AffiliateController (Affiliate-facing)
**File:** `app/Http/Controllers/AffiliateController.php`

Endpoints (for users with affiliate role):
- `GET /affiliate` - Affiliate dashboard (isolated)
- `GET /affiliate/earnings` - Paginated earnings history
- `POST /affiliate/verify-bank` - Verify bank account
- `POST /affiliate/setup-bank` - Complete bank setup, create subaccount
- `GET /affiliate/referrals` - List of referred users

### 2. Create AffiliateRequestController (Regular user requesting affiliate access)
**File:** `app/Http/Controllers/AffiliateRequestController.php`

Endpoints:
- `POST /affiliate/request` - Submit affiliate application
- `GET /affiliate/request/status` - Check application status
- `POST /affiliate/promo-dismiss` - Dismiss promo popup

### 3. Create AffiliateAuthController (Affiliate registration)
**File:** `app/Http/Controllers/Auth/AffiliateAuthController.php`

Endpoints:
- `GET /affiliate/register` - Show affiliate registration form (if open or has valid invite)
- `POST /affiliate/register` - Handle affiliate registration
- `GET /affiliate/invite/{code}` - Validate invite and show registration

### 4. Create AdminAffiliateController
**File:** `app/Http/Controllers/Admin/AdminAffiliateController.php`

Endpoints:
- `GET /admin/affiliates` - Dashboard with stats
- `PUT /admin/affiliates/settings` - Update global settings
- `GET /admin/affiliates/list` - List all affiliates with stats
- `PUT /admin/affiliates/{user}` - Update affiliate (custom rate, notes)
- `DELETE /admin/affiliates/{user}/rate` - Reset to default rate

### 5. Create AdminAffiliateInviteController
**File:** `app/Http/Controllers/Admin/AdminAffiliateInviteController.php`

Endpoints:
- `GET /admin/affiliates/invites` - List all invites
- `POST /admin/affiliates/invites` - Create new invite (single-use or reusable)
- `PUT /admin/affiliates/invites/{invite}` - Update invite (deactivate, extend expiry)
- `DELETE /admin/affiliates/invites/{invite}` - Delete invite

### 6. Create AdminAffiliateRequestController
**File:** `app/Http/Controllers/Admin/AdminAffiliateRequestController.php`

Endpoints:
- `GET /admin/affiliates/requests` - List pending affiliate requests
- `POST /admin/affiliates/requests/{user}/approve` - Approve request
- `POST /admin/affiliates/requests/{user}/reject` - Reject request

### 7. Update PaymentController
**File:** [app/Http/Controllers/PaymentController.php](app/Http/Controllers/PaymentController.php)

Modify `initialize()`:
- Check if user was referred by an affiliate
- If yes, use split payment with affiliate's subaccount
- Create ReferralEarning record

Modify `processSuccessfulPayment()`:
- Mark associated ReferralEarning as PAID

### 8. Update RegisteredUserController
**File:** [app/Http/Controllers/Auth/RegisteredUserController.php](app/Http/Controllers/Auth/RegisteredUserController.php)

Modify to handle referral code linking (already done).

---

## Routes

### Affiliate Routes (routes/affiliate.php - new file)
```php
// Public affiliate registration (when open or with invite)
Route::middleware('guest')->group(function () {
    Route::get('/affiliate/register', [AffiliateAuthController::class, 'showRegistration']);
    Route::post('/affiliate/register', [AffiliateAuthController::class, 'register']);
    Route::get('/affiliate/invite/{code}', [AffiliateAuthController::class, 'validateInvite']);
});

// Affiliate dashboard (requires affiliate role)
Route::middleware(['auth', 'verified', 'role:affiliate'])->prefix('affiliate')->group(function () {
    Route::get('/', [AffiliateController::class, 'index'])->name('affiliate.dashboard');
    Route::get('/earnings', [AffiliateController::class, 'earnings']);
    Route::post('/verify-bank', [AffiliateController::class, 'verifyBankAccount']);
    Route::post('/setup-bank', [AffiliateController::class, 'setupBankAccount']);
    Route::get('/referrals', [AffiliateController::class, 'referrals']);
});

// Regular user requesting affiliate access
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/affiliate/request', [AffiliateRequestController::class, 'store']);
    Route::get('/affiliate/request/status', [AffiliateRequestController::class, 'status']);
    Route::post('/affiliate/promo-dismiss', [AffiliateRequestController::class, 'dismissPromo']);
});
```

### Admin Routes (add to routes/web.php admin group)
```php
Route::prefix('affiliates')->group(function () {
    // Dashboard & settings
    Route::get('/', [AdminAffiliateController::class, 'index']);
    Route::put('/settings', [AdminAffiliateController::class, 'updateSettings']);
    Route::get('/list', [AdminAffiliateController::class, 'list']);
    Route::put('/{user}', [AdminAffiliateController::class, 'update']);
    Route::delete('/{user}/rate', [AdminAffiliateController::class, 'resetRate']);

    // Invites
    Route::get('/invites', [AdminAffiliateInviteController::class, 'index']);
    Route::post('/invites', [AdminAffiliateInviteController::class, 'store']);
    Route::put('/invites/{invite}', [AdminAffiliateInviteController::class, 'update']);
    Route::delete('/invites/{invite}', [AdminAffiliateInviteController::class, 'destroy']);

    // Affiliate requests from regular users
    Route::get('/requests', [AdminAffiliateRequestController::class, 'index']);
    Route::post('/requests/{user}/approve', [AdminAffiliateRequestController::class, 'approve']);
    Route::post('/requests/{user}/reject', [AdminAffiliateRequestController::class, 'reject']);
});
```

---

## Frontend Components

### 1. Affiliate Dashboard (Isolated)
**File:** `resources/js/pages/Affiliate/Dashboard.vue`

**If bank NOT set up:**
- Bank setup form (select bank, enter account number, verify, submit)
- Explanation that bank setup is required to start earning

**If bank IS set up:**
- Stats cards: total referrals, active referrals, total earned, this month, pending
- Referral link with copy button
- Commission rate display (e.g., "Earn 10% on every purchase")
- Badge if affiliate has custom rate (e.g., "VIP Partner - 15%")
- Recent earnings table
- List of referred users

### 2. Affiliate Earnings Page
**File:** `resources/js/pages/Affiliate/Earnings.vue`
- Paginated earnings table with filters
- Export functionality (CSV)

### 3. Affiliate Registration Page
**File:** `resources/js/pages/Affiliate/Register.vue`
- Registration form for pure affiliates
- Shows only when registration is open OR user has valid invite
- Different layout from regular user registration

### 4. Affiliate Promo Popup (for regular users)
**File:** `resources/js/components/AffiliatePromoPopup.vue`
- Modal/popup encouraging users to become affiliates
- "Earn 10% commission on every referral!"
- "Apply Now" and "Maybe Later" buttons
- Only shows based on settings (enabled, delay days, not already affiliate)

### 5. User Dashboard Affiliate CTA
**File:** Update existing dashboard
- Small banner/card promoting affiliate program
- "Become an Affiliate" button
- Shows application status if pending

### 6. Dashboard Switcher (for dual-access users)
**File:** `resources/js/components/DashboardSwitcher.vue`
- Toggle/dropdown to switch between User Dashboard and Affiliate Dashboard
- Shows in header/nav for users with both roles

### 7. Admin Affiliate Management
**File:** `resources/js/pages/Admin/Affiliates/Index.vue`
- Overview statistics (total affiliates, commissions paid, pending, this month)
- Global settings form:
  - Enable/disable affiliate system
  - Open/close affiliate registration
  - Default commission percentage
  - Minimum payment amount
  - Promo popup settings
- Top affiliates leaderboard
- Recent earnings table

**File:** `resources/js/pages/Admin/Affiliates/List.vue`
- Table of all affiliates: name, email, code, type (pure/dual), rate, total earned
- Inline edit for custom commission rate
- Badge showing "Custom" vs "Default" rate
- Actions: view details, reset rate

**File:** `resources/js/pages/Admin/Affiliates/Invites.vue`
- List of all invites with status
- Create new invite form (type: single-use/reusable, expiry, max uses, note)
- Copy invite link button
- Deactivate/delete invite

**File:** `resources/js/pages/Admin/Affiliates/Requests.vue`
- List of pending affiliate applications from regular users
- User info, signup date, project count
- Approve/Reject buttons with optional note

### 8. Update Register Page
**File:** [resources/js/pages/auth/Register.vue](resources/js/pages/auth/Register.vue)
- Show subtle indication if valid referral code is present
- No changes needed for affiliate system (affiliates register separately)

---

## Middleware

### 1. Create AffiliateMiddleware
**File:** `app/Http/Middleware/EnsureUserIsAffiliate.php`
- Checks user has `affiliate` role
- Redirects to appropriate page if not

### 2. Update navigation/layout
- Detect if user has affiliate role
- Show dashboard switcher for dual-access users
- Show affiliate nav for pure affiliates

---

## Edge Cases Handled

| Scenario | Solution |
|----------|----------|
| Affiliate hasn't set up bank | Can share referral link, but earnings marked pending until bank setup |
| Invalid referral code at signup | Validate code exists AND affiliate has bank setup before accepting |
| Self-referral attempt | Blocked with logging |
| Pure affiliate tries to access projects | Middleware blocks, redirect to affiliate dashboard |
| Regular user with pending application | Show status, prevent duplicate applications |
| Affiliate registration closed + no invite | Show "Registration closed" message |
| Expired affiliate invite | Reject with appropriate message |
| User already has affiliate role | Prevent duplicate application |
| Payment refunded | ReferralEarning marked as `refunded` |
| Payment below minimum | No commission created |

---

## Implementation Order

1. **Database & Models** - Migrations, models, seeders (including affiliate role)
2. **Services** - ReferralService, AffiliateService, PaystackService updates
3. **Middleware** - Affiliate middleware
4. **Controllers & Routes** - All endpoints
5. **Frontend - Admin** - Admin affiliate management pages
6. **Frontend - Affiliate** - Affiliate dashboard and registration
7. **Frontend - User** - Promo popup, dashboard switcher, affiliate CTA
8. **Testing** - Unit and feature tests
9. **Polish** - Notifications, logging, edge cases

---

## Files to Create
- `database/migrations/2026_01_XX_add_affiliate_fields_to_users_table.php`
- `database/migrations/2026_01_XX_create_referral_earnings_table.php`
- `database/migrations/2026_01_XX_create_referral_bank_accounts_table.php`
- `database/migrations/2026_01_XX_create_affiliate_invites_table.php`
- `database/migrations/2026_01_XX_create_affiliate_invite_redemptions_table.php`
- `app/Models/ReferralEarning.php`
- `app/Models/ReferralBankAccount.php`
- `app/Models/AffiliateInvite.php`
- `app/Models/AffiliateInviteRedemption.php`
- `app/Services/ReferralService.php` (update existing)
- `app/Services/AffiliateService.php`
- `app/Http/Middleware/EnsureUserIsAffiliate.php`
- `app/Http/Controllers/AffiliateController.php`
- `app/Http/Controllers/AffiliateRequestController.php`
- `app/Http/Controllers/Auth/AffiliateAuthController.php`
- `app/Http/Controllers/Admin/AdminAffiliateController.php`
- `app/Http/Controllers/Admin/AdminAffiliateInviteController.php`
- `app/Http/Controllers/Admin/AdminAffiliateRequestController.php`
- `routes/affiliate.php`
- `resources/js/pages/Affiliate/Dashboard.vue`
- `resources/js/pages/Affiliate/Earnings.vue`
- `resources/js/pages/Affiliate/Register.vue`
- `resources/js/pages/Admin/Affiliates/Index.vue`
- `resources/js/pages/Admin/Affiliates/List.vue`
- `resources/js/pages/Admin/Affiliates/Invites.vue`
- `resources/js/pages/Admin/Affiliates/Requests.vue`
- `resources/js/components/AffiliatePromoPopup.vue`
- `resources/js/components/DashboardSwitcher.vue`
- `tests/Feature/AffiliateSystemTest.php`

## Files to Modify
- `app/Models/User.php` - Add affiliate methods and fields
- `app/Models/Payment.php` - Add referralEarning relationship
- `app/Services/PaystackService.php` - Add subaccount and split payment methods
- `app/Http/Controllers/PaymentController.php` - Integrate split payments
- `database/seeders/AdminRoleSeeder.php` - Add affiliate role and permissions
- `database/seeders/SystemSettingSeeder.php` - Add affiliate settings
- `routes/web.php` - Register affiliate routes
- `bootstrap/app.php` - Include routes/affiliate.php
- Navigation components - Add affiliate links and switcher

---

## Verification Steps

1. **Pure Affiliate Registration (when open):**
   - Enable affiliate registration in admin
   - Visit `/affiliate/register`
   - Complete registration
   - Verify user has ONLY affiliate role
   - Verify redirected to affiliate dashboard
   - Verify cannot access regular project pages

2. **Pure Affiliate Registration (via invite):**
   - Close affiliate registration
   - Admin creates single-use invite code
   - Visit `/affiliate/invite/CODE`
   - Complete registration
   - Verify invite marked as used
   - Verify user has affiliate role

3. **Regular User Requests Affiliate Access:**
   - Login as regular user
   - See promo popup after X days (if enabled)
   - Click "Apply Now" or use dashboard CTA
   - Application submitted, status = pending
   - Verify cannot apply again

4. **Admin Approves Affiliate Request:**
   - Admin views pending requests
   - Approves user's request
   - User now has both roles (dual-access)
   - User sees dashboard switcher
   - User can access both dashboards

5. **Affiliate Bank Setup:**
   - Login as affiliate (pure or dual)
   - Visit affiliate dashboard
   - See bank setup required message
   - Complete bank verification
   - Verify Paystack subaccount created
   - Referral code now active

6. **Split Payment Flow:**
   - New user registers with affiliate's referral code
   - User makes purchase
   - Payment initializes with split parameters
   - Complete payment
   - ReferralEarning created with status PAID
   - Affiliate sees earning in dashboard

7. **Admin Invite Management:**
   - Admin creates reusable invite with max 5 uses
   - Share link, 3 people register
   - Verify uses count = 3
   - Deactivate invite
   - New registration attempts fail

8. **Admin Settings:**
   - Change default commission to 15%
   - Verify new affiliate signups/existing affiliates use new rate
   - Set custom rate for specific affiliate (20%)
   - Verify that affiliate's earnings use custom rate
