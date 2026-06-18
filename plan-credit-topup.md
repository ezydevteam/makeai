# Credit Top-Up Implementation Plan

## Current State

### What exists
- **`CreditPack` model** with seeded packs (500/$4.99, 2000/$14.99, 5000/$29.99, 15000/$79.99) — **will be replaced by admin-configurable pricing**
- **`payments` table** already has `type` enum including `'credit_topup'` (unused)
- **`credit_transactions` table** supports `type = 'topup'` (unused)
- **Multi-gateway checkout** (Stripe, PayPal, Paddle, Razorpay, SSLCommerz, CoinGate, Paystack, Bank Transfer, 2Checkout) — all working for subscriptions
- **Webhook system** (`ProcessPaymentWebhookJob`) handles payment activation
- **Admin** can manually add credits via user management

### What's missing
- No admin settings for credit pricing
- No controller/routes for credit top-up
- No checkout integration for one-time credit purchases
- No webhook handling to credit user account after payment
- No UI for credit top-up (custom amount + quick-select)
- No bonus tier system

---

## Approach: Admin-Configurable Credit Pricing

Instead of fixed credit packs, admin controls:
- **Credit price per unit** (e.g., $0.01 = 1 credit)
- **Bonus tiers** (e.g., $50+ gets 10% bonus credits)
- **Quick-select amounts** (e.g., $5, $10, $25, $50, $100 buttons)
- **Minimum top-up amount** (e.g., $1)

Users enter custom amount OR click quick-select buttons.

---

## Implementation Plan

### Phase 1: Admin Settings

#### 1.1 Add Settings to `settings` table
```php
// New settings keys
'credit_price_per_unit' => '0.01'      // $0.01 = 1 credit
'credit_topup_minimum' => '1.00'       // Minimum $1
'credit_topup_quick_amounts' => '[5, 10, 25, 50, 100]'  // Quick-select buttons
'credit_topup_bonus_tiers' => '[
    {"min_amount": 50, "bonus_percent": 10},
    {"min_amount": 100, "bonus_percent": 20}
]'
'credit_topup_enabled' => true         // Master toggle
```

#### 1.2 Admin Settings Page
Add to existing billing/payment settings page:
- Credit price per unit input
- Minimum top-up amount
- Quick-select amounts (comma-separated or dynamic list)
- Bonus tiers table (min amount | bonus %)
- Enable/disable toggle

**Location:** `/admin/premium/credit-settings` or add to existing `/admin/premium/plans/settings`

---

### Phase 2: Backend — Controller & Routes

#### 2.1 Create `CreditTopupController`
```
app/Http/Controllers/CreditTopupController.php
```

**Methods:**
- `index()` — Show top-up page with:
  - Current credit price per unit
  - Quick-select amounts
  - Bonus tier info
  - Available gateways
- `checkout(Request $request)` — Create pending payment
  - Validates `amount` (custom or quick-select)
  - Calculates credits: `credits = amount / credit_price_per_unit`
  - Applies bonus if tier matched: `credits *= (1 + bonus_percent/100)`
  - Creates `Payment` record with `type = 'credit_topup'`
  - Stores `amount`, `credits`, `bonus_credits` in payment meta
  - Routes to gateway-specific handler

#### 2.2 Routes
```php
// In routes/web.php, inside auth+verified middleware group
Route::prefix('user/dashboard')->name('user.dashboard.')->group(function () {
    Route::get('/credit-topup', [CreditTopupController::class, 'index'])->name('credit-topup');
    Route::post('/credit-topup/checkout', [CreditTopupController::class, 'checkout'])->name('credit-topup.checkout');
});
```

#### 2.3 Extend `CheckoutController`
Extend existing `CheckoutController@createSession` to handle `type=credit_topup`:
- Add `purchasable_type` field: `'plan'` or `'credit_topup'`
- For credit_topup: use `amount` from request instead of plan price
- Reuse all gateway routing logic

**Recommended: Extend CheckoutController** — avoids duplicating gateway logic across 9+ gateways.

---

### Phase 3: Payment Activation

#### 3.1 Update `PaymentActivationService`
Add `activateCreditTopup(Payment $payment)` method:
```php
public function activateCreditTopup(Payment $payment): void
{
    $user = $payment->user;
    $meta = $payment->meta ?? [];
    $credits = $meta['credits'] ?? 0;
    $bonusCredits = $meta['bonus_credits'] ?? 0;
    $totalCredits = $credits + $bonusCredits;
    
    // Add credits to user
    $user->addCredits($totalCredits, 'topup', 'Credit top-up: ' . format_currency($meta['amount']), [
        'payment_id' => $payment->id,
        'base_credits' => $credits,
        'bonus_credits' => $bonusCredits,
    ]);
    
    // Mark payment as completed
    $payment->update(['status' => 'completed']);
    
    // Send notification
    app(NotificationEventService::class)->creditsAdded($user, $totalCredits, 'Credit top-up');
}
```

#### 3.2 Update `ProcessPaymentWebhookJob`
Add credit topup detection in webhook handlers:
- Check `payment.type === 'credit_topup'`
- Call `activateCreditTopup()` instead of `activateFromPayment()`
- This applies to all gateways (Stripe, PayPal, etc.)

#### 3.3 Stripe One-Time Checkout
For Stripe, credit topups use **one-time payment mode** (not subscription):
```php
$checkout = $user->checkout(
    lineItems: [['price_data' => [
        'unit_amount' => $amount * 100, // cents
        'currency' => $currency,
        'product_data' => ['name' => 'Credit Top-Up'],
    ]]],
    metadata: ['payment_id' => $payment->id],
    mode: 'payment', // one-time, not subscription
);
```

---

### Phase 4: Frontend — Credit Top-Up Page

#### 4.1 Create `CreditTopup.vue`
```
resources/js/Pages/User/CreditTopup.vue
```

**UI Layout:**
```
┌─────────────────────────────────────────────────────────────┐
│  Buy Credits                                                │
│  Current balance: 1,234 credits                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Enter amount (USD)                                  │   │
│  │  ┌─────────────────────────────────────────────┐    │   │
│  │  │  $ [___________]                            │    │   │
│  │  └─────────────────────────────────────────────┘    │   │
│  │                                                     │   │
│  │  Quick select:                                       │   │
│  │  [$5]  [$10]  [$25]  [$50]  [$100]                 │   │
│  │                                                     │   │
│  │  You will receive: 500 credits                       │   │
│  │  (Rate: $0.01 = 1 credit)                           │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  🎁 Bonus Credits                                    │   │
│  │  Spend $50+ → Get 10% bonus                          │   │
│  │  Spend $100+ → Get 20% bonus                         │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Payment Method                                      │   │
│  │  ○ Stripe (Visa, Mastercard)                         │   │
│  │  ○ PayPal                                            │   │
│  │  ○ Bank Transfer                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Coupon Code                                         │   │
│  │  [___________] [Apply]                               │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Summary                                             │   │
│  │  Amount:        $50.00                               │   │
│  │  Base credits:  5,000                                │   │
│  │  Bonus (10%):   +500                                 │   │
│  │  ─────────────────────                               │   │
│  │  Total credits: 5,500                                │   │
│  │  Processing fee: $0.00                               │   │
│  │  ─────────────────────                               │   │
│  │  Total:         $50.00                               │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  [Proceed to Payment]                                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Features:**
- Custom amount input with validation (minimum check)
- Quick-select buttons (populated from admin settings)
- Live credit calculation as user types
- Bonus tier display (shows which tier they qualify for)
- Gateway selection
- Coupon code input (reuse existing coupon system)
- Processing fee display (from gateway settings)
- Summary breakdown (base credits + bonus)
- Disabled button if amount < minimum

#### 4.2 Add to Sidebar
Add "Buy Credits" link in `UserDashboardLayout.vue` sidebar:
- Position: Near Billing or as standalone item
- Icon: `ti ti-coin` or `ti ti-credit-card`
- Route: `user.dashboard.credit-topup`

#### 4.3 Credit Balance Widget
Add "Buy More Credits" button next to credit balance in:
- Dashboard header (credit display)
- Credit alert banner (when low)
- Usage page

---

### Phase 5: Pricing Page Integration

#### 5.1 Update `Pricing.vue`
- Replace fixed credit pack section with "Buy Credits" CTA
- Link to `/user/dashboard/credit-topup`
- Show current credit price per unit
- Show bonus tier info

---

### Phase 6: Bank Transfer Support

For bank transfer gateway (manual approval):
- Create pending payment with `type = 'credit_topup'`
- Show bank instructions page
- Admin approves → manual activation → credits added
- Same flow as subscription bank transfer, just different activation method

---

### Phase 7: Admin Enhancements

#### 7.1 Credit Settings Page
Create `/admin/premium/credit-settings` with:
- Credit price per unit input
- Minimum top-up amount
- Quick-select amounts (dynamic list with add/remove)
- Bonus tiers table (add/remove rows)
- Enable/disable toggle
- Preview: "At current rate, $10 = X credits"

#### 7.2 Top-Up History
Show credit top-up payments in:
- Admin user detail page (payment history tab)
- User billing page (top-up history section)

---

## Files to Create/Modify

### New Files
| File | Purpose |
|------|---------|
| `app/Http/Controllers/CreditTopupController.php` | Handle credit top-up flow |
| `resources/js/Pages/User/CreditTopup.vue` | Credit top-up page |
| `resources/js/Pages/Admin/CreditSettings.vue` | Admin credit settings page |

### Modified Files
| File | Changes |
|------|---------|
| `routes/web.php` | Add credit topup routes |
| `routes/admin.php` | Add credit settings route |
| `app/Http/Controllers/CheckoutController.php` | Extend `createSession` to handle `credit_topup` type |
| `app/Services/Payment/PaymentActivationService.php` | Add `activateCreditTopup()` method |
| `app/Jobs/ProcessPaymentWebhookJob.php` | Detect credit topup payments and route to correct activation |
| `app/Http/Controllers/Billing/StripeController.php` | Handle one-time payment mode for credit topup |
| `resources/js/Pages/Pricing.vue` | Replace fixed packs with "Buy Credits" CTA |
| `resources/js/Layouts/UserDashboardLayout.vue` | Add sidebar link |
| `resources/js/Pages/User/Billing.vue` | Add top-up history section |
| `resources/js/Components/CreditAlertBanner.vue` | Add "Buy Credits" button |

---

## Key Design Decisions

1. **Admin-configurable pricing** — No hardcoded credit packs. Admin sets `credit_price_per_unit` and all calculations derive from it.

2. **Hybrid UI** — Custom amount input + quick-select buttons. Best of both worlds.

3. **Bonus tiers** — Admin can incentivize larger purchases with bonus credits. Displayed clearly on top-up page.

4. **Extend existing checkout** — Reuse `CheckoutController` gateway logic. Add `purchasable_type = 'credit_topup'` discriminator.

5. **One-time payments only** — Credit topups are not subscriptions. Use `mode: 'payment'` for Stripe, one-time capture for PayPal, etc.

6. **Credits added on payment confirmation** — Not on checkout creation. Webhooks confirm payment → credits added.

7. **Coupon support** — Credit topups support coupons (same system as plans). Can discount the amount.

8. **No credit expiration** — Purchased credits don't expire (simpler, matches user expectations).

---

## Database Changes

### Settings (no migration needed — uses existing `settings` table)
```sql
INSERT INTO settings (key, value, type, `group`) VALUES
('credit_price_per_unit', '0.01', 'string', 'billing'),
('credit_topup_minimum', '1.00', 'string', 'billing'),
('credit_topup_quick_amounts', '[5, 10, 25, 50, 100]', 'json', 'billing'),
('credit_topup_bonus_tiers', '[{"min_amount": 50, "bonus_percent": 10}, {"min_amount": 100, "bonus_percent": 20}]', 'json', 'billing'),
('credit_topup_enabled', '1', 'boolean', 'billing');
```

### Payment Meta (no schema change — uses existing JSON `meta` column)
```json
{
  "amount": 50.00,
  "currency": "USD",
  "credits": 5000,
  "bonus_credits": 500,
  "credit_price_per_unit": 0.01,
  "bonus_tier": "10%"
}
```

---

## Implementation Order

1. Add admin settings for credit pricing (settings table + admin UI)
2. Create `CreditTopupController` with `index()` and `checkout()` methods
3. Extend `CheckoutController` to handle `credit_topup` type
4. Add `activateCreditTopup()` to `PaymentActivationService`
5. Update `ProcessPaymentWebhookJob` to handle credit topup activation
6. Update Stripe controller for one-time payment mode
7. Add routes for credit topup
8. Create `CreditTopup.vue` page with hybrid UI
9. Add sidebar link
10. Update Pricing.vue with "Buy Credits" CTA
11. Add top-up history to Billing.vue
12. Create admin credit settings page
13. Test all gateways (at minimum: Stripe, PayPal, Bank Transfer)

---

## Bonus Tier Logic

```php
// In CreditTopupController@checkout
function calculateCredits(float $amount, float $pricePerUnit, array $bonusTiers): array
{
    $baseCredits = $amount / $pricePerUnit;
    $bonusPercent = 0;
    
    // Find matching bonus tier (highest tier where amount >= min_amount)
    foreach ($bonusTiers as $tier) {
        if ($amount >= $tier['min_amount'] && $tier['bonus_percent'] > $bonusPercent) {
            $bonusPercent = $tier['bonus_percent'];
        }
    }
    
    $bonusCredits = $baseCredits * ($bonusPercent / 100);
    $totalCredits = $baseCredits + $bonusCredits;
    
    return [
        'base_credits' => floor($baseCredits),
        'bonus_credits' => floor($bonusCredits),
        'total_credits' => floor($totalCredits),
        'bonus_percent' => $bonusPercent,
    ];
}
```

---

## Future Enhancements (Out of Scope)

- Recurring credit top-ups (monthly auto-top-up)
- Credit expiration dates (optional per-purchase)
- Credit gifting (buy credits for another user)
- Bulk discounts for enterprise customers
- Credit refund to payment method
