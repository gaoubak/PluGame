# Payment Flow Guide - PluGame

## 💳 Complete Payment System Overview

This guide explains all possible payment flows in PluGame and how to implement them in your frontend.

---

## 🎯 Payment Flow Options

PluGame supports **5 different payment scenarios**:

1. **Full Card Payment** - User pays entire amount with credit card
2. **Wallet + Card Payment** - User uses wallet balance + card for remaining
3. **Wallet Only Payment** - User pays entirely with wallet (no card needed)
4. **Payment with Promo Code** - Discount applied before payment
5. **Payment with Gift Card** - Gift card balance applied + card/wallet

---

## 📊 Payment Flow Diagram

```
User wants to pay €100 for a booking
    ↓
┌─────────────────────────────────────┐
│ 1. Apply Promo Code (optional)     │
│    - "SAVE20" = 20% off             │
│    - New amount: €80                │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ 2. Apply Gift Card (optional)      │
│    - Gift card: €15 balance         │
│    - New amount: €65                │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ 3. Use Wallet (optional)            │
│    - Wallet: €10 balance            │
│    - Remaining: €55                 │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ 4. Final Payment                    │
│    - Amount: €55                    │
│    - Method: Credit Card            │
└─────────────────────────────────────┘
```

---

## 🔄 Flow 1: Full Card Payment

**Scenario:** User pays €100 entirely with credit card.

### Frontend Implementation

```typescript
// Step 1: Create Payment Intent
const createPaymentIntent = async (bookingId: string, amount: number) => {
  const response = await fetch('https://api.plugame.com/api/payments/intent', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amountCents: amount * 100, // €100 = 10000 cents
      bookingId: bookingId,
      useWallet: false,          // Don't use wallet
      isDeposit: false,          // Full payment
    })
  });

  const data = await response.json();
  return data;
};

// Step 2: Confirm Payment with Stripe
const confirmPayment = async (clientSecret: string) => {
  const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
    payment_method: {
      card: cardElement,
      billing_details: {
        name: 'Customer Name',
        email: 'customer@email.com',
      },
    },
  });

  if (error) {
    console.error('Payment failed:', error.message);
  } else if (paymentIntent.status === 'succeeded') {
    console.log('Payment succeeded!');
  }
};

// Complete Flow
const payWithCard = async () => {
  // 1. Create intent
  const { clientSecret, paymentIntentId } = await createPaymentIntent(bookingId, 100);

  // 2. Show Stripe payment form
  // 3. User enters card details
  // 4. Confirm payment
  await confirmPayment(clientSecret);

  // 5. Payment webhook will update booking status automatically
  navigation.navigate('PaymentSuccess');
};
```

**API Response:**
```json
{
  "paymentIntentId": "pi_xxxxx",
  "clientSecret": "pi_xxxxx_secret_yyyy",
  "paymentId": "payment-uuid",
  "originalAmount": 100.00,
  "finalAmount": 100.00,
  "cardCharge": 100.00,
  "walletUsed": 0,
  "isDeposit": false
}
```

---

## 🔄 Flow 2: Wallet + Card Payment

**Scenario:** User has €30 in wallet, pays €70 with card.

### Frontend Implementation

```typescript
// Step 1: Check wallet balance
const getWalletBalance = async () => {
  const response = await fetch('https://api.plugame.com/api/wallet/balance', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const { balanceCents } = await response.json();
  return balanceCents / 100; // Convert to euros
};

// Step 2: Create payment with wallet enabled
const payWithWalletAndCard = async (bookingId: string, totalAmount: number) => {
  const walletBalance = await getWalletBalance(); // €30

  // Create payment intent with wallet enabled
  const response = await fetch('https://api.plugame.com/api/payments/intent', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amountCents: totalAmount * 100, // €100 = 10000 cents
      bookingId: bookingId,
      useWallet: true,              // ← Enable wallet usage
      isDeposit: false,
    })
  });

  const data = await response.json();

  // Show user the breakdown
  console.log(`Total: €${data.originalAmount}`);
  console.log(`Wallet: -€${data.walletUsed / 100}`);
  console.log(`Card charge: €${data.cardCharge}`);

  // If card charge > 0, show Stripe form
  if (data.cardCharge > 0) {
    await confirmPayment(data.clientSecret);
  }

  return data;
};
```

**API Response:**
```json
{
  "paymentIntentId": "pi_xxxxx",
  "clientSecret": "pi_xxxxx_secret_yyyy",
  "paymentId": "payment-uuid",
  "originalAmount": 100.00,
  "finalAmount": 100.00,
  "walletUsed": 3000,        // €30 in cents
  "cardCharge": 70.00,       // €70 remaining
  "isDeposit": false
}
```

---

## 🔄 Flow 3: Wallet Only Payment

**Scenario:** User has €150 in wallet, pays €100 entirely from wallet.

### Frontend Implementation

```typescript
const payWithWalletOnly = async (bookingId: string, amount: number) => {
  const walletBalance = await getWalletBalance(); // €150

  if (walletBalance < amount) {
    alert('Insufficient wallet balance');
    return;
  }

  // Create payment intent
  const response = await fetch('https://api.plugame.com/api/payments/intent', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amountCents: amount * 100,
      bookingId: bookingId,
      useWallet: true,           // Use wallet
      isDeposit: false,
    })
  });

  const data = await response.json();

  // Check if fully paid with wallet
  if (data.cardCharge === 0) {
    console.log('Paid entirely with wallet! No card needed.');
    navigation.navigate('PaymentSuccess');
  } else {
    // Still need card for remaining amount
    await confirmPayment(data.clientSecret);
  }

  return data;
};
```

**API Response (Fully paid with wallet):**
```json
{
  "paymentId": "payment-uuid",
  "originalAmount": 100.00,
  "finalAmount": 100.00,
  "walletUsed": 10000,       // €100 in cents
  "cardCharge": 0,           // No card needed!
  "message": "Paid with wallet",
  "isDeposit": false
}
```

**Note:** No `paymentIntentId` or `clientSecret` when fully paid with wallet!

---

## 🔄 Flow 4: Payment with Promo Code

**Scenario:** User applies "SAVE20" promo code (20% off) on €100 booking.

### Frontend Implementation

```typescript
// Step 1: Validate promo code (optional, for UX)
const validatePromoCode = async (code: string, creatorId: number) => {
  const response = await fetch(
    `https://api.plugame.com/api/promo-codes/validate?code=${code}&creatorId=${creatorId}`,
    {
      headers: { 'Authorization': `Bearer ${token}` }
    }
  );

  const data = await response.json();

  if (data.valid) {
    console.log(`Discount: ${data.discountValue}${data.discountType === 'percentage' ? '%' : '€'}`);
    return data;
  } else {
    alert(data.message);
    return null;
  }
};

// Step 2: Apply promo code in payment
const payWithPromoCode = async (bookingId: string, amount: number, promoCode: string) => {
  const response = await fetch('https://api.plugame.com/api/payments/intent', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amountCents: amount * 100,  // €100 = 10000 cents
      bookingId: bookingId,
      promoCode: promoCode,       // ← Apply promo code
      useWallet: false,
      isDeposit: false,
    })
  });

  const data = await response.json();

  // Show discount breakdown
  console.log(`Original: €${data.originalAmount}`);
  console.log(`Discount: -€${data.promoCodeDiscount}`);
  console.log(`Final: €${data.finalAmount}`);
  console.log(`Card charge: €${data.cardCharge}`);

  // Confirm payment if needed
  if (data.cardCharge > 0) {
    await confirmPayment(data.clientSecret);
  }

  return data;
};
```

**API Response:**
```json
{
  "paymentIntentId": "pi_xxxxx",
  "clientSecret": "pi_xxxxx_secret_yyyy",
  "paymentId": "payment-uuid",
  "originalAmount": 100.00,
  "promoCodeDiscount": 20.00,    // 20% off
  "totalDiscount": 20.00,
  "finalAmount": 80.00,
  "walletUsed": 0,
  "cardCharge": 80.00,
  "isDeposit": false
}
```

---

## 🔄 Flow 5: Payment with Gift Card

**Scenario:** User applies gift card with €25 balance on €100 booking.

### Frontend Implementation

```typescript
// Step 1: Validate gift card
const validateGiftCard = async (code: string) => {
  const response = await fetch('https://api.plugame.com/api/gift-cards/validate', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ code })
  });

  const data = await response.json();

  if (data.valid) {
    console.log(`Gift card balance: €${data.balance}`);
    return data;
  } else {
    alert('Invalid or expired gift card');
    return null;
  }
};

// Step 2: Apply gift card in payment
const payWithGiftCard = async (bookingId: string, amount: number, giftCardCode: string) => {
  const response = await fetch('https://api.plugame.com/api/payments/intent', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amountCents: amount * 100,    // €100 = 10000 cents
      bookingId: bookingId,
      giftCardCode: giftCardCode,   // ← Apply gift card
      useWallet: false,
      isDeposit: false,
    })
  });

  const data = await response.json();

  // Show breakdown
  console.log(`Original: €${data.originalAmount}`);
  console.log(`Gift card: -€${data.giftCardAmount}`);
  console.log(`Final: €${data.finalAmount}`);
  console.log(`Card charge: €${data.cardCharge}`);

  // Confirm payment if needed
  if (data.cardCharge > 0) {
    await confirmPayment(data.clientSecret);
  }

  return data;
};
```

**API Response:**
```json
{
  "paymentIntentId": "pi_xxxxx",
  "clientSecret": "pi_xxxxx_secret_yyyy",
  "paymentId": "payment-uuid",
  "originalAmount": 100.00,
  "giftCardAmount": 25.00,       // Gift card applied
  "totalDiscount": 25.00,
  "finalAmount": 75.00,
  "walletUsed": 0,
  "cardCharge": 75.00,
  "isDeposit": false
}
```

---

## 🎁 Flow 6: Combined Discounts + Wallet + Card

**Scenario:** User combines everything:
- Original: €100
- Promo code: -€20 (20%)
- Gift card: -€15
- Wallet: -€10
- Card: €55

### Frontend Implementation

```typescript
const payWithEverything = async (
  bookingId: string,
  amount: number,
  promoCode?: string,
  giftCardCode?: string,
  useWallet: boolean = true
) => {
  const response = await fetch('https://api.plugame.com/api/payments/intent', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amountCents: amount * 100,
      bookingId: bookingId,
      promoCode: promoCode,         // Optional promo code
      giftCardCode: giftCardCode,   // Optional gift card
      useWallet: useWallet,         // Use wallet if available
      isDeposit: false,
    })
  });

  const data = await response.json();

  // Complete breakdown
  console.log(`Original amount: €${data.originalAmount}`);

  if (data.promoCodeDiscount > 0) {
    console.log(`Promo discount: -€${data.promoCodeDiscount}`);
  }

  if (data.giftCardAmount > 0) {
    console.log(`Gift card: -€${data.giftCardAmount}`);
  }

  if (data.walletUsed > 0) {
    console.log(`Wallet: -€${data.walletUsed / 100}`);
  }

  console.log(`Total discount: -€${data.totalDiscount}`);
  console.log(`Final amount: €${data.finalAmount}`);
  console.log(`Card charge: €${data.cardCharge}`);

  // Confirm payment if card needed
  if (data.cardCharge > 0) {
    await confirmPayment(data.clientSecret);
  } else {
    // Fully paid with discounts/wallet
    navigation.navigate('PaymentSuccess');
  }

  return data;
};
```

**API Response:**
```json
{
  "paymentIntentId": "pi_xxxxx",
  "clientSecret": "pi_xxxxx_secret_yyyy",
  "paymentId": "payment-uuid",
  "originalAmount": 100.00,
  "promoCodeDiscount": 20.00,
  "giftCardAmount": 15.00,
  "totalDiscount": 35.00,
  "finalAmount": 65.00,
  "walletUsed": 1000,           // €10 in cents
  "cardCharge": 55.00,          // Final card charge
  "isDeposit": false
}
```

---

## 💰 Deposit Payment Flow

**Scenario:** User pays 30% deposit (€30 on €100 booking).

### Frontend Implementation

```typescript
const payDeposit = async (bookingId: string, totalAmount: number, depositPercent: number = 30) => {
  const depositAmount = (totalAmount * depositPercent) / 100;

  const response = await fetch('https://api.plugame.com/api/payments/intent', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amountCents: depositAmount * 100,  // €30 = 3000 cents
      bookingId: bookingId,
      useWallet: true,                   // Can use wallet for deposit
      isDeposit: true,                   // ← Mark as deposit
    })
  });

  const data = await response.json();

  console.log(`Deposit: €${data.finalAmount} (${depositPercent}%)`);
  console.log(`Remaining: €${totalAmount - data.finalAmount} due later`);

  if (data.cardCharge > 0) {
    await confirmPayment(data.clientSecret);
  }

  return data;
};
```

---

## 🎨 UI/UX Recommendations

### Payment Screen Layout

```typescript
const PaymentScreen = ({ booking }) => {
  const [useWallet, setUseWallet] = useState(false);
  const [promoCode, setPromoCode] = useState('');
  const [giftCardCode, setGiftCardCode] = useState('');

  return (
    <View>
      {/* Booking Summary */}
      <BookingSummary booking={booking} />

      {/* Original Amount */}
      <PriceRow label="Original Amount" value={booking.price} />

      {/* Promo Code Input */}
      <PromoCodeInput
        value={promoCode}
        onChange={setPromoCode}
        onApply={validatePromoCode}
      />

      {/* Gift Card Input */}
      <GiftCardInput
        value={giftCardCode}
        onChange={setGiftCardCode}
        onApply={validateGiftCard}
      />

      {/* Wallet Toggle */}
      <WalletToggle
        enabled={useWallet}
        balance={walletBalance}
        onChange={setUseWallet}
      />

      {/* Discount Breakdown */}
      {promoDiscount > 0 && (
        <PriceRow label="Promo Discount" value={-promoDiscount} color="green" />
      )}
      {giftCardAmount > 0 && (
        <PriceRow label="Gift Card" value={-giftCardAmount} color="green" />
      )}
      {walletUsed > 0 && (
        <PriceRow label="Wallet" value={-walletUsed} color="green" />
      )}

      {/* Final Amount */}
      <PriceRow label="Final Amount" value={finalAmount} bold />

      {/* Payment Button */}
      <PaymentButton
        amount={finalAmount}
        onPress={handlePayment}
      />
    </View>
  );
};
```

---

## 🔔 Webhook Events

After payment, your backend sends Stripe webhooks. Frontend should listen for:

### Via Mercure (Real-time notifications)

```typescript
// Subscribe to payment updates
const eventSource = new EventSource(
  `https://api.plugame.com/.well-known/mercure?topic=https://plugame.app/users/${userId}/payments`
);

eventSource.onmessage = (event) => {
  const data = JSON.parse(event.data);

  if (data.type === 'payment.succeeded') {
    navigation.navigate('PaymentSuccess', { bookingId: data.bookingId });
  } else if (data.type === 'payment.failed') {
    alert('Payment failed: ' + data.reason);
  }
};
```

---

## 📋 API Endpoints Summary

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/payments/intent` | POST | Create payment intent |
| `/api/wallet/balance` | GET | Get wallet balance |
| `/api/promo-codes/validate` | GET | Validate promo code |
| `/api/gift-cards/validate` | POST | Validate gift card |
| `/api/payment-methods` | GET | Get saved cards |
| `/api/payment-methods` | POST | Add payment method |

---

## ✅ Complete Payment Flow Checklist

**Before Payment:**
- [ ] Get wallet balance
- [ ] Validate promo code (if any)
- [ ] Validate gift card (if any)
- [ ] Calculate final amount

**Create Payment:**
- [ ] Call `/api/payments/intent` with all parameters
- [ ] Check `cardCharge` in response
- [ ] If `cardCharge > 0`, show Stripe form
- [ ] If `cardCharge === 0`, skip to success

**Stripe Payment:**
- [ ] Use `clientSecret` to confirm payment
- [ ] Handle card errors
- [ ] Wait for Stripe confirmation

**After Payment:**
- [ ] Listen for webhook via Mercure
- [ ] Update booking status
- [ ] Navigate to success screen

---

## 🚀 Quick Integration Example

```typescript
// Complete payment flow in one function
const completePayment = async (params: {
  bookingId: string;
  amount: number;
  promoCode?: string;
  giftCardCode?: string;
  useWallet?: boolean;
  isDeposit?: boolean;
}) => {
  try {
    // 1. Create payment intent
    const response = await fetch('https://api.plugame.com/api/payments/intent', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        amountCents: params.amount * 100,
        bookingId: params.bookingId,
        promoCode: params.promoCode,
        giftCardCode: params.giftCardCode,
        useWallet: params.useWallet ?? false,
        isDeposit: params.isDeposit ?? false,
      })
    });

    const data = await response.json();

    // 2. Check if card payment needed
    if (data.cardCharge === 0) {
      // Fully paid with wallet/discounts
      return { success: true, message: 'Paid successfully!' };
    }

    // 3. Confirm with Stripe
    const { error, paymentIntent } = await stripe.confirmCardPayment(
      data.clientSecret,
      {
        payment_method: {
          card: cardElement,
          billing_details: { name, email },
        },
      }
    );

    if (error) {
      throw new Error(error.message);
    }

    return { success: true, paymentIntent };

  } catch (error) {
    console.error('Payment error:', error);
    return { success: false, error: error.message };
  }
};
```

---

## 🎉 You're Ready!

Your payment system supports:
- ✅ Full card payments
- ✅ Wallet payments
- ✅ Combined wallet + card
- ✅ Promo code discounts
- ✅ Gift card redemption
- ✅ Deposit payments
- ✅ Real-time webhook notifications

Start implementing these flows in your React Native app! 🚀
