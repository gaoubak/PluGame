# Dual Payout System - Stripe Connect vs Bank Transfer

## Overview

Creators can choose between **two payout methods**:

1. **Stripe Connect** - Automatic instant transfer to Stripe account
2. **Bank Transfer (SEPA)** - Automated transfer directly to IBAN via Stripe Payouts API

---

## How It Works

### Payment Flow

```
Athlete pays for booking
        ↓
Payment succeeds (remainingPaidAt set)
        ↓
Athlete downloads deliverable
        ↓
System checks creator's payout preference
        ↓
    ┌─────────┴─────────┐
    ↓                   ↓
Stripe Connect    Bank Transfer
    ↓                   ↓
Instant transfer   SEPA to IBAN
to Stripe account  (1-3 days)
```

---

## API Endpoints

### 1. Get Payout Preference
**GET** `/api/payouts/preference`

**Response:**
```json
{
  "currentMethod": "bank_transfer",
  "availableMethods": {
    "stripe_connect": {
      "available": false,
      "label": "Stripe Connect (Automatic)",
      "description": "Instant transfer to your Stripe account"
    },
    "bank_transfer": {
      "available": true,
      "label": "Bank Transfer (SEPA)",
      "description": "Direct transfer to your bank account"
    }
  }
}
```

### 2. Set Payout Preference
**POST** `/api/payouts/preference`

**Request:**
```json
{
  "payoutMethod": "bank_transfer"
}
```

**Validation:**
- `stripe_connect` → Requires Stripe Connect account (`stripeAccountId`)
- `bank_transfer` → Requires at least one IBAN saved

**Response:**
```json
{
  "message": "Payout preference updated successfully",
  "payoutMethod": "bank_transfer"
}
```

### 3. Add Bank Account
**POST** `/api/payouts/methods`

**Request:**
```json
{
  "bankName": "BNP Paribas",
  "iban": "FR76 1234 5678 9012 3456 7890 123",
  "bic": "BNPAFRPP"
}
```

**Features:**
- ✅ Automatic IBAN validation (mod-97 check)
- ✅ Extracts last 4 digits for display
- ✅ Auto-sets as default if first account
- ✅ Supports BIC/SWIFT (optional)

**Response:**
```json
{
  "message": "Payout method added",
  "method": {
    "id": "uuid",
    "displayName": "BNP Paribas •••• 0123",
    "isDefault": true
  }
}
```

### 4. Process Pending Payouts (Admin)
**POST** `/api/payouts/process-pending`

**Requires:** `ROLE_ADMIN`

**Response:**
```json
{
  "message": "Batch payout processing completed",
  "processed": 5,
  "failed": 0,
  "success": ["booking-uuid-1", "booking-uuid-2"],
  "failures": []
}
```

---

## Payout Methods Comparison

| Feature | Stripe Connect | Bank Transfer (SEPA) |
|---------|---------------|---------------------|
| **Setup** | Complete Stripe onboarding | Add IBAN (simple form) |
| **Transfer Speed** | Instant to Stripe account | 1-3 business days |
| **Bank Deposit** | 2-7 days after transfer | 1-3 days total |
| **Fees** | ~0.25% transfer fee | No transfer fee (just Stripe processing) |
| **Automation** | Fully automatic | Fully automatic via Stripe Payouts API |
| **Min Amount** | $1 | €1 |
| **Countries** | Many (via Stripe Connect) | SEPA zone (EU + more) |
| **Creator Control** | Limited (via Stripe dashboard) | Full (direct to their bank) |

---

## Technical Implementation

### Database Schema

**User Table:**
```sql
ALTER TABLE users ADD COLUMN payout_method VARCHAR(20) DEFAULT 'stripe_connect';
```

**Payout_Method Table:**
```sql
ALTER TABLE payout_method ADD COLUMN iban VARCHAR(34) NULL;
ALTER TABLE payout_method ADD COLUMN bic VARCHAR(11) NULL;
```

### Services

**BankPayoutService** ([src/Service/BankPayoutService.php](src/Service/BankPayoutService.php))
- Handles SEPA transfers via Stripe Payouts API
- Creates/retrieves Stripe external accounts (bank accounts)
- Validates IBAN format (mod-97 check)
- Processes batch payouts

**StripePayoutService** ([src/Service/StripePayoutService.php](src/Service/StripePayoutService.php))
- Routes to appropriate service based on creator preference
- Handles Stripe Connect transfers
- Delegates bank transfers to BankPayoutService

---

## Frontend Integration

### Setup Flow

```typescript
// 1. Creator chooses payout method
const setupPayout = async (method: 'stripe_connect' | 'bank_transfer') => {
  if (method === 'bank_transfer') {
    // Show IBAN form
    await addBankAccount({
      bankName: 'BNP Paribas',
      iban: 'FR76 1234 5678 9012 3456 7890 123',
      bic: 'BNPAFRPP' // optional
    });
  } else {
    // Redirect to Stripe Connect onboarding
    const { url } = await fetch('/api/stripe/onboarding-link');
    window.location.href = url;
  }

  // Set preference
  await fetch('/api/payouts/preference', {
    method: 'POST',
    body: JSON.stringify({ payoutMethod: method })
  });
};

// 2. Add bank account
const addBankAccount = async (data) => {
  const response = await fetch('/api/payouts/methods', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  return await response.json();
};
```

### Settings UI Example

```tsx
<PayoutSettings>
  <RadioGroup value={currentMethod}>
    <Radio value="stripe_connect">
      <Icon name="stripe" />
      <Label>Stripe Connect</Label>
      <Description>Instant to Stripe • 2-7 days to bank</Description>
      {!hasStripeConnect && <Badge>Setup Required</Badge>}
    </Radio>

    <Radio value="bank_transfer">
      <Icon name="bank" />
      <Label>Bank Transfer (SEPA)</Label>
      <Description>Direct to bank • 1-3 business days</Description>
      {!hasBankAccount && <Badge>Add IBAN</Badge>}
    </Radio>
  </RadioGroup>
</PayoutSettings>
```

---

## IBAN Validation

The system validates IBANs using the mod-97 algorithm:

```php
public function validateIban(string $iban): bool
{
    $iban = strtoupper(str_replace(' ', '', $iban));

    // Check length (15-34 chars)
    if (strlen($iban) < 15 || strlen($iban) > 34) return false;

    // Check country code (2 letters)
    if (!preg_match('/^[A-Z]{2}/', $iban)) return false;

    // Move first 4 chars to end, convert letters to numbers, check mod 97 = 1
    $moved = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = preg_replace_callback('/[A-Z]/', fn($m) => ord($m[0]) - 55, $moved);

    return bcmod($numeric, '97') === '1';
}
```

**Supported countries:** All SEPA zone countries (36 countries)

---

## Cron Job Setup (Optional)

For batch processing of pending payouts (if not using instant processing):

```bash
# Process pending bank transfers daily at 2 AM
0 2 * * * curl -X POST https://api.plugame.com/api/payouts/process-pending \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

Or via Symfony Command:

```php
// src/Command/ProcessPayoutsCommand.php
php bin/console app:process-payouts
```

---

## Migration Steps

### 1. Run Migrations

```bash
docker compose exec alpine php bin/console doctrine:migrations:diff
docker compose exec alpine php bin/console doctrine:migrations:migrate
```

### 2. Update Existing Creators

All creators default to `stripe_connect`. Those with Stripe Connect already set up will continue working automatically.

For creators who want bank transfer:
1. They add their IBAN via `/api/payouts/methods`
2. They switch preference via `/api/payouts/preference`

---

## Security Notes

⚠️ **IBAN Storage:**
IBANs are stored in plaintext in this implementation. For production, consider:
- Encrypting IBAN field in database
- Using Stripe's tokenization for sensitive data
- Implementing PCI-DSS compliant storage

⚠️ **Admin Endpoint:**
The `/api/payouts/process-pending` endpoint requires `ROLE_ADMIN`. Ensure proper role management.

---

## Testing

### Test Bank Accounts (Stripe Test Mode)

```
IBAN: DE89370400440532013000 (Germany)
IBAN: FR1420041010050500013M02606 (France)
```

### Test Flow

1. Create booking as athlete
2. Pay for booking
3. Creator uploads deliverables
4. Athlete downloads → triggers payout
5. Check creator's payout preference:
   - Stripe Connect → Instant transfer to Stripe
   - Bank Transfer → SEPA to IBAN (1-3 days in production, instant in test mode)

---

## Troubleshooting

### "Creator does not have a Stripe Connect account"
- Creator selected Stripe Connect but hasn't completed onboarding
- Solution: Guide them through Stripe onboarding or switch to bank transfer

### "No default bank account found for creator"
- Creator selected bank transfer but hasn't added IBAN
- Solution: Add IBAN via `/api/payouts/methods`

### "Invalid IBAN format"
- IBAN failed mod-97 validation
- Solution: Verify IBAN is correct, check for typos

### Payout shows as pending
- Bank transfers take 1-3 business days in production
- Check Stripe dashboard for payout status
- Verify IBAN is correct in Stripe

---

## Next Steps

1. ✅ Run migrations
2. ✅ Test with test IBANs in Stripe test mode
3. 📱 Update frontend to show payout preference selector
4. 📱 Add IBAN input form to settings
5. 📊 Add payout status tracking in creator dashboard
6. 🔐 Consider encrypting IBANs for production
7. 📧 Customize payout notification emails
