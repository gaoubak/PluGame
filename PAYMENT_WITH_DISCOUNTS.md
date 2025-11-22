# Payment with Promo Codes & Gift Cards

## ✅ Implementation Complete!

The PaymentController now supports **promo codes** and **gift cards** for discounts.

---

## 🎯 How It Works

### Payment Flow with Discounts

```
1. Original Amount: €100.00
   ↓
2. Apply Promo Code: -€15.00 (15% off)
   Subtotal: €85.00
   ↓
3. Apply Gift Card: -€20.00
   Subtotal: €65.00
   ↓
4. Use Wallet: -€10.00
   Remaining: €55.00
   ↓
5. Charge Card: €55.00
```

---

## 📝 API Usage

### Create Payment Intent with Discounts

**POST** `/api/payments/intent`

```json
{
  "amountCents": 10000,
  "bookingId": "booking-uuid",
  "promoCode": "SUMMER2024",
  "giftCardCode": "GIFT-ABC123XYZ",
  "useWallet": true,
  "isDeposit": false
}
```

### Response - Card Payment Required

```json
{
  "paymentIntentId": "pi_xxxxx",
  "clientSecret": "pi_xxxxx_secret_yyyy",
  "paymentId": "payment-uuid",
  "originalAmount": 100.00,
  "promoCodeDiscount": 15.00,
  "giftCardAmount": 20.00,
  "totalDiscount": 35.00,
  "finalAmount": 65.00,
  "walletUsed": 1000,
  "cardCharge": 55.00,
  "isDeposit": false
}
```

### Response - Fully Paid (Wallet + Discounts)

If discounts + wallet cover the full amount:

```json
{
  "paymentId": "payment-uuid",
  "originalAmount": 100.00,
  "promoCodeDiscount": 50.00,
  "giftCardAmount": 30.00,
  "totalDiscount": 80.00,
  "finalAmount": 20.00,
  "walletUsed": 20.00,
  "cardCharge": 0,
  "message": "Paid with wallet",
  "isDeposit": false
}
```

### Response - Fully Covered by Discounts

If discounts cover 100%:

```json
{
  "paymentId": "payment-uuid",
  "originalAmount": 100.00,
  "promoCodeDiscount": 60.00,
  "giftCardAmount": 40.00,
  "totalDiscount": 100.00,
  "finalAmount": 0.00,
  "walletUsed": 0,
  "cardCharge": 0,
  "message": "Fully covered by discounts",
  "isDeposit": false
}
```

---

## 🎫 Promo Code Validation

### Automatic Checks

The system validates:
- ✅ Code exists and is active
- ✅ Code belongs to the booking's creator
- ✅ Code has not reached max uses
- ✅ User has not exceeded max uses per user
- ✅ Order amount meets minimum requirement
- ✅ Code has not expired

### Error Responses

**Invalid Code:**
```json
{
  "error": "Invalid or expired promo code"
}
```

**Wrong Creator:**
```json
{
  "error": "This promo code is not valid for this creator"
}
```

**Max Uses Reached:**
```json
{
  "error": "This promo code has reached its maximum number of uses"
}
```

---

## 🎁 Gift Card Validation

### Automatic Checks

The system validates:
- ✅ Card exists and is active
- ✅ Card has available balance
- ✅ Card has not expired

### Partial Usage

Gift cards can be used partially:

**Example:**
```
Gift Card Balance: €25.00
Order Amount: €100.00
Gift Card Deduction: €25.00 (full balance used)
Remaining to Pay: €75.00

Gift Card New Balance: €0.00
```

### Multiple Uses

Gift cards can be reused until balance is depleted:

**First Use:**
```
Order: €30.00
Gift Card Balance: €50.00
Deducted: €30.00
New Balance: €20.00
```

**Second Use:**
```
Order: €25.00
Gift Card Balance: €20.00
Deducted: €20.00 (all remaining)
New Balance: €0.00
Status: Inactive
```

---

## 💾 Database Storage

### Payment Entity Fields

After a discounted payment:

```php
$payment->getOriginalAmountCents();  // 10000 (€100.00)
$payment->getDiscountAmountCents();   // 1500 (€15.00 promo)
$payment->getGiftCardAmountCents();   // 2000 (€20.00 gift card)
$payment->getAmountCents();           // 6500 (€65.00 final)

$payment->getPromoCode();  // PromoCode entity
$payment->getGiftCard();   // GiftCard entity

$payment->getTotalDiscountCents();  // 3500 (€35.00 total)
$payment->getDiscountPercentage();  // 35.0
```

### Promo Code Tracking

```php
$promoCode->getUsedCount();  // Incremented on each use
```

### Gift Card Balance

```php
$giftCard->getInitialBalance();   // 5000 (€50.00 originally)
$giftCard->getCurrentBalance();   // 3000 (€30.00 remaining)
$giftCard->isValid();             // true/false
```

---

## 🔄 Complete Payment Example

### Scenario: €100 Booking with Multiple Discounts

**Initial State:**
- Order Amount: €100.00
- Promo Code: SAVE20 (20% off)
- Gift Card: €15.00 balance
- Wallet: €10.00

**Step-by-Step Calculation:**

```
1. Original: €100.00

2. Apply Promo Code (20%):
   Discount: €100.00 × 0.20 = €20.00
   Subtotal: €80.00

3. Apply Gift Card:
   Deduction: €15.00 (full balance)
   Subtotal: €65.00

4. Apply Wallet:
   Deduction: €10.00
   Subtotal: €55.00

5. Charge Card: €55.00
```

**API Request:**
```json
{
  "amountCents": 10000,
  "bookingId": "xxx",
  "promoCode": "SAVE20",
  "giftCardCode": "GIFT-ABC123",
  "useWallet": true
}
```

**API Response:**
```json
{
  "originalAmount": 100.00,
  "promoCodeDiscount": 20.00,
  "giftCardAmount": 15.00,
  "totalDiscount": 35.00,
  "finalAmount": 65.00,
  "walletUsed": 1000,
  "cardCharge": 55.00
}
```

---

## 🧪 Testing

### 1. Validate Promo Code First

**GET** `/api/promo-codes/validate?code=SUMMER2024`

```json
{
  "valid": true,
  "code": "SUMMER2024",
  "discountType": "percentage",
  "discountValue": 20,
  "minAmount": 50.00
}
```

### 2. Validate Gift Card

**POST** `/api/gift-cards/validate`

```json
{
  "code": "GIFT-ABC123XYZ"
}
```

Response:
```json
{
  "valid": true,
  "balance": 25.50,
  "balanceFormatted": "25.50",
  "currency": "EUR"
}
```

### 3. Create Payment with Discounts

**POST** `/api/payments/intent`

```json
{
  "amountCents": 10000,
  "bookingId": "booking-uuid",
  "promoCode": "SUMMER2024",
  "giftCardCode": "GIFT-ABC123XYZ"
}
```

---

## 📊 Payment Scenarios

### Scenario 1: Promo Code Only
```json
{
  "amountCents": 10000,
  "promoCode": "SAVE15"
}
```
Result: €100 → €85 (15% off)

### Scenario 2: Gift Card Only
```json
{
  "amountCents": 5000,
  "giftCardCode": "GIFT-XYZ789"
}
```
Result: €50 → €30 (if gift card has €20 balance)

### Scenario 3: Both Promo + Gift Card
```json
{
  "amountCents": 10000,
  "promoCode": "SAVE20",
  "giftCardCode": "GIFT-ABC123"
}
```
Result: €100 → €80 (promo) → €60 (gift card €20)

### Scenario 4: All Discounts + Wallet
```json
{
  "amountCents": 10000,
  "promoCode": "SAVE30",
  "giftCardCode": "GIFT-ABC123",
  "useWallet": true
}
```
Result: €100 → €70 (promo) → €50 (gift) → €40 (wallet €10) → Card: €40

---

## ✅ Implementation Details

### Files Modified

1. **[PaymentController.php](src/Controller/PaymentController.php)**
   - Added `PromoCodeRepository` and `GiftCardRepository`
   - Added promo code and gift card validation
   - Linked Payment entity to PromoCode and GiftCard
   - Updated responses to show discount breakdown

### Code Changes

**Imports Added:**
```php
use App\Entity\PromoCode;
use App\Entity\GiftCard;
use App\Repository\PromoCodeRepository;
use App\Repository\GiftCardRepository;
```

**Constructor Updated:**
```php
public function __construct(
    // ... existing params
    private readonly PromoCodeRepository $promoCodeRepository,
    private readonly GiftCardRepository $giftCardRepository,
) {}
```

**Request Parameters:**
```php
$promoCode = $data['promoCode'] ?? null;
$giftCardCode = $data['giftCardCode'] ?? null;
```

**Discount Application:**
```php
// 1. Apply promo code
if ($promoCode) {
    $promoCodeEntity = $this->promoCodeRepository->findActiveByCode($promoCode);
    $promoCodeDiscount = $promoCodeEntity->calculateDiscount($finalAmount);
    $finalAmount -= $promoCodeDiscount;
}

// 2. Apply gift card
if ($giftCardCode) {
    $giftCardEntity = $this->giftCardRepository->findActiveByCode($giftCardCode);
    $giftCardAmount = $giftCardEntity->deduct($finalAmount);
    $finalAmount -= $giftCardAmount;
}
```

**Payment Entity Updated:**
```php
$payment->setOriginalAmountCents($originalAmount);
$payment->setPromoCode($promoCodeEntity);
$payment->setDiscountAmountCents($promoCodeDiscount);
$payment->setGiftCard($giftCardEntity);
$payment->setGiftCardAmountCents($giftCardAmount);
```

---

## 🚀 Ready to Use!

You can now:

✅ Apply promo codes to payments
✅ Use gift cards for discounts
✅ Combine promo codes + gift cards + wallet
✅ Track discount usage in database
✅ Get detailed breakdown in API response

**Test it:**
```bash
curl -X POST https://your-api/api/payments/intent \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amountCents": 10000,
    "bookingId": "booking-id",
    "promoCode": "SUMMER2024",
    "giftCardCode": "GIFT-ABC123"
  }'
```

🎉 **The payment system now supports full discount functionality!**
