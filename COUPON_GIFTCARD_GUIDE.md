# Coupon & Gift Card System - Complete Guide

## Overview

Plugame now supports **Promo Codes** (percentage or fixed discounts) and **Gift Cards** (prepaid balances) to enhance the payment experience.

---

## 🎫 Promo Codes

### Features
- **Two discount types**: Percentage (e.g., 20% off) or Fixed Amount (e.g., 10€ off)
- Creator-specific codes
- Usage limits (total and per-user)
- Minimum booking amount requirements
- Expiration dates
- Stripe integration

### API Endpoints

#### 1. Validate Promo Code
**POST** `/api/promo-codes/validate`

Validate a promo code before payment.

**Request Body:**
```json
{
  "code": "SUMMER2025",
  "creator_id": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "amount": 10000
}
```

**Success Response (200):**
```json
{
  "valid": true,
  "discount_amount": 2000,
  "final_amount": 8000,
  "discount_display": "20%",
  "code": "SUMMER2025"
}
```

**Error Response (404):**
```json
{
  "valid": false,
  "message": "Code promo invalide ou expiré"
}
```

#### 2. Create Promo Code (Creator Only)
**POST** `/api/promo-codes/create`

**Request Body:**
```json
{
  "code": "SUMMER2025",
  "discount_type": "percentage",
  "discount_value": 20,
  "description": "Summer sale discount",
  "max_uses": 100,
  "max_uses_per_user": 1,
  "expires_at": "2025-12-31T23:59:59Z",
  "min_amount": 5000
}
```

**Response (201):**
```json
{
  "id": "uuid",
  "code": "SUMMER2025",
  "discount_type": "percentage",
  "discount_value": 20,
  "discount_display": "20%",
  "stripe_coupon_id": "stripe_coupon_xyz",
  "is_active": true
}
```

#### 3. List My Promo Codes (Creator Only)
**GET** `/api/promo-codes/mine`

**Response (200):**
```json
[
  {
    "id": "uuid",
    "code": "SUMMER2025",
    "discount_display": "20%",
    "used_count": 45,
    "max_uses": 100,
    "is_active": true,
    "is_valid": true
  }
]
```

#### 4. Deactivate Promo Code
**POST** `/api/promo-codes/{id}/deactivate`

**Response (200):**
```json
{
  "message": "Promo code deactivated successfully",
  "code": "SUMMER2025"
}
```

---

## 🎁 Gift Cards

### Features
- Unique codes (format: `GIFT-XXXXXXXX`)
- Balance tracking (initial and current)
- Partial usage support
- User redemption tracking
- Expiration dates
- Currency support

### API Endpoints

#### 1. Validate Gift Card
**POST** `/api/gift-cards/validate`

Check gift card validity and balance.

**Request Body:**
```json
{
  "code": "GIFT-ABC12345"
}
```

**Success Response (200):**
```json
{
  "valid": true,
  "code": "GIFT-ABC12345",
  "balance": 5000,
  "balanceFormatted": "50.00",
  "currency": "EUR",
  "expiresAt": "2026-12-31T23:59:59Z",
  "message": "Carte cadeau valide"
}
```

**Error Response (404):**
```json
{
  "valid": false,
  "message": "Carte cadeau invalide ou expirée"
}
```

#### 2. List My Gift Cards
**GET** `/api/gift-cards/mine`

**Response (200):**
```json
[
  {
    "id": "uuid",
    "code": "GIFT-ABC12345",
    "initialBalance": 10000,
    "currentBalance": 5000,
    "balanceDisplay": "50.00 EUR",
    "currency": "EUR",
    "isActive": true,
    "isValid": true,
    "expiresAt": "2026-12-31T23:59:59Z",
    "redeemedAt": "2025-01-15T10:30:00Z"
  }
]
```

#### 3. Create Gift Card (Admin Only)
**POST** `/api/gift-cards/create`

**Request Body:**
```json
{
  "amount": 10000,
  "currency": "EUR",
  "recipientEmail": "recipient@example.com",
  "recipientName": "John Doe",
  "message": "Happy Birthday!",
  "expiresAt": "2026-12-31T23:59:59Z"
}
```

**Response (201):**
```json
{
  "id": "uuid",
  "code": "GIFT-A1B2C3D4",
  "balance": 10000,
  "balanceDisplay": "100.00 EUR",
  "message": "Carte cadeau créée avec succès"
}
```

---

## 💳 Payment Flow Integration

### Combined Usage Example

When a user books a service for **100€**, they can apply:
1. **Promo Code** `SUMMER2025` (20% discount) → **-20€**
2. **Gift Card** `GIFT-ABC12345` (50€ balance) → **-50€**
3. **Final Amount to Pay**: **30€**

### Payment Request with Discounts

**POST** `/api/payments/create`

```json
{
  "booking_id": "booking-uuid",
  "amount": 3000,
  "original_amount": 10000,
  "promo_code": "SUMMER2025",
  "gift_card_code": "GIFT-ABC12345",
  "payment_method_id": "pm_xxxxx"
}
```

### Payment Entity Fields

The `Payment` entity tracks all discount information:

```php
$payment->getOriginalAmountCents();     // 10000 (100€)
$payment->getDiscountAmountCents();     // 2000 (20€ from promo)
$payment->getGiftCardAmountCents();     // 5000 (50€ from gift card)
$payment->getAmountCents();             // 3000 (30€ final charge)
$payment->getTotalDiscountCents();      // 7000 (70€ total saved)
```

---

## 📊 Database Schema

### Gift Cards Table
```sql
CREATE TABLE gift_cards (
    id CHAR(36) PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    initial_balance INT NOT NULL,
    current_balance INT NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    purchased_by_id INT,
    redeemed_by_id INT,
    redeemed_at DATETIME,
    expires_at DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    message TEXT,
    recipient_email VARCHAR(255),
    recipient_name VARCHAR(255),
    created_at DATETIME,
    updated_at DATETIME
);
```

### Payment Table Updates
```sql
ALTER TABLE payment ADD gift_card_id CHAR(36);
ALTER TABLE payment ADD gift_card_amount_cents INT;
```

---

## 🔒 Business Rules

### Promo Codes
1. **Creator-specific**: Only valid for services by the code's creator
2. **Single use per user**: If `max_uses_per_user` is set
3. **Minimum amount**: Code won't apply if booking is below minimum
4. **Expiration**: Automatically invalid after expiration date
5. **Usage limits**: Stops working when `used_count >= max_uses`

### Gift Cards
1. **Partial usage**: Remaining balance stays on card
2. **Auto-deactivation**: Card deactivates when balance reaches 0
3. **One-time redemption**: First user to use it becomes the redeemer
4. **Balance limits**: Can't deduct more than available balance
5. **Currency matching**: Should match booking currency

### Combining Discounts
1. **Promo code first**: Applied to original amount
2. **Gift card second**: Applied to discounted amount
3. **Maximum discount**: Cannot exceed 100% of booking cost
4. **Minimum charge**: Stripe requires minimum 50 cents (adjust by currency)

---

## 🧪 Testing

### Test Data Setup

Add to `src/DataFixtures/AppFixtures.php`:

```php
// Create promo code
$promoCode = new PromoCode();
$promoCode->setCode('TESTCODE20');
$promoCode->setCreator($creator1);
$promoCode->setDiscountType('percentage');
$promoCode->setDiscountValue(20);
$promoCode->setMaxUses(100);
$promoCode->setExpiresAt(new \DateTimeImmutable('+1 year'));
$manager->persist($promoCode);

// Create gift card
$giftCard = new GiftCard();
$giftCard->setCode('GIFT-TEST1234');
$giftCard->setInitialBalance(5000); // 50€
$giftCard->setCurrency('EUR');
$giftCard->setExpiresAt(new \DateTimeImmutable('+1 year'));
$manager->persist($giftCard);
```

### cURL Test Examples

**Test Promo Code:**
```bash
curl -X POST https://your-api.ngrok-free.dev/api/promo-codes/validate \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "TESTCODE20",
    "creator_id": "creator-uuid",
    "amount": 10000
  }'
```

**Test Gift Card:**
```bash
curl -X POST https://your-api.ngrok-free.dev/api/gift-cards/validate \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "GIFT-TEST1234"
  }'
```

---

## 🎨 Frontend Integration

### React Native Example

```typescript
import { useState } from 'react';

interface DiscountInfo {
  promoCode?: {
    code: string;
    discount: number;
  };
  giftCard?: {
    code: string;
    balance: number;
  };
}

function PaymentScreen() {
  const [originalAmount] = useState(10000); // 100€
  const [discounts, setDiscounts] = useState<DiscountInfo>({});
  const [promoInput, setPromoInput] = useState('');
  const [giftCardInput, setGiftCardInput] = useState('');

  const validatePromoCode = async () => {
    const response = await fetch('/api/promo-codes/validate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        code: promoInput,
        creator_id: creatorId,
        amount: originalAmount
      })
    });

    const data = await response.json();
    if (data.valid) {
      setDiscounts(prev => ({
        ...prev,
        promoCode: { code: data.code, discount: data.discount_amount }
      }));
    }
  };

  const validateGiftCard = async () => {
    const response = await fetch('/api/gift-cards/validate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: giftCardInput })
    });

    const data = await response.json();
    if (data.valid) {
      setDiscounts(prev => ({
        ...prev,
        giftCard: { code: data.code, balance: data.balance }
      }));
    }
  };

  const calculateFinalAmount = () => {
    let amount = originalAmount;
    if (discounts.promoCode) {
      amount -= discounts.promoCode.discount;
    }
    if (discounts.giftCard) {
      amount -= Math.min(discounts.giftCard.balance, amount);
    }
    return Math.max(0, amount);
  };

  const finalAmount = calculateFinalAmount();

  return (
    <View>
      <Text>Montant: {(originalAmount / 100).toFixed(2)}€</Text>

      {/* Promo Code Input */}
      <TextInput
        placeholder="Code promo"
        value={promoInput}
        onChangeText={setPromoInput}
      />
      <Button title="Appliquer" onPress={validatePromoCode} />

      {discounts.promoCode && (
        <Text>Réduction: -{(discounts.promoCode.discount / 100).toFixed(2)}€</Text>
      )}

      {/* Gift Card Input */}
      <TextInput
        placeholder="Carte cadeau"
        value={giftCardInput}
        onChangeText={setGiftCardInput}
      />
      <Button title="Appliquer" onPress={validateGiftCard} />

      {discounts.giftCard && (
        <Text>Carte cadeau: -{Math.min(discounts.giftCard.balance, finalAmount + (discounts.giftCard.balance || 0)) / 100).toFixed(2)}€</Text>
      )}

      <Text style={{fontSize: 24, fontWeight: 'bold'}}>
        Total à payer: {(finalAmount / 100).toFixed(2)}€
      </Text>

      <Button title="Payer" onPress={handlePayment} />
    </View>
  );
}
```

---

## 📝 Notes

- All amounts are in **cents** (e.g., 10000 = 100.00€)
- Promo codes are always **UPPERCASE**
- Gift card codes follow format `GIFT-XXXXXXXX`
- Both systems support **EUR** and other currencies
- Admin role required to create gift cards
- Creator role required to create promo codes

---

## 🚀 Next Steps

1. Run migrations: `php bin/console doctrine:migrations:migrate`
2. Add test data via fixtures
3. Test endpoints via Postman or cURL
4. Integrate frontend validation UI
5. Configure Stripe webhooks for promo code usage tracking