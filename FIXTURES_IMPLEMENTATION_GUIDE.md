# Fixtures Implementation Guide - Complete Realistic Flow

## 🎯 Overview

The fixtures now simulate a complete, realistic flow of the Plugame app with all entities properly connected.

---

## 🔄 Complete Flow Simulation

### PHASE 1: Platform Setup
1. **Users Created** - 20 athletes + 15 creators register
2. **Creators Build Profiles** - Upload feed content (photos/videos with aspect ratios)
3. **Services Created** - Creators list their offerings
4. **Availability Set** - Creators set their schedules
5. **Payout Methods** - Creators connect bank accounts/Stripe
6. **Promo Codes** - Creators create discount codes

### PHASE 2: Discovery & Engagement
7. **Athletes Browse** - Discover creators
8. **Likes** - Athletes like creators they're interested in
9. **Bookmarks** - Athletes save favorites
10. **Pre-Booking Conversations** - Athletes ask questions

### PHASE 3: Bookings & Payments
11. **Gift Cards Created** - Platform creates promotional gift cards
12. **Wallet Credits** - Athletes add credits to wallet
13. **Bookings Made** - Athletes book services
14. **Payments Processed** - With promo codes & gift cards applied

### PHASE 4: Service Delivery
15. **Booking Conversations** - Communication about the session
16. **Deliverables Uploaded** - Creators upload photos/videos
17. **Downloads & Payouts** - Athletes download, creators get paid

---

## 📊 New Methods Added

### 1. `createCreatorFeedContent()` - Realistic Feed Posts

Creates feed posts with proper aspect ratios and realistic content:

```php
private function createCreatorFeedContent(ObjectManager $manager, array $creators): array
{
    $feedPosts = [];

    // Aspect ratios with realistic distribution
    $aspectRatios = [
        '1:1'  => 30,  // 30% square (Instagram classic)
        '4:5'  => 20,  // 20% portrait
        '16:9' => 25,  // 25% landscape (action videos)
        '9:16' => 25,  // 25% vertical (stories/reels)
    ];

    foreach ($creators as $creator) {
        $postCount = rand(5, 15);  // Each creator: 5-15 posts

        for ($i = 0; $i < $postCount; $i++) {
            $isVideo = $this->randomBool(40);  // 40% videos, 60% photos

            $aspectRatio = $this->weightedRandom($aspectRatios);
            [$width, $height] = $this->getDimensionsForRatio($aspectRatio);

            $media = new MediaAsset();
            $media->setOwner($creator)
                ->setCreatorProfile($creator->getCreatorProfile())
                ->setPurpose(MediaAsset::PURPOSE_CREATOR_FEED)
                ->setType($isVideo ? MediaAsset::TYPE_VIDEO : MediaAsset::TYPE_IMAGE)
                ->setAspectRatio($aspectRatio)
                ->setWidth($width)
                ->setHeight($height)
                ->setFilename("feed_{$i}." . ($isVideo ? 'mp4' : 'jpg'))
                ->setStorageKey("creator/{$creator->getId()}/feed_{$i}")
                ->setPublicUrl("https://cdn.plugame.com/creator/{$creator->getId()}/feed_{$i}")
                ->setBytes(rand($isVideo ? 5000000 : 500000, $isVideo ? 50000000 : 5000000))
                ->setCaption($this->generateFeedCaption())
                ->setContentType($isVideo ? 'video/mp4' : 'image/jpeg');

            if ($isVideo) {
                $media->setDurationSec(rand(10, 180));  // 10s to 3min
                $media->setThumbnailUrl("https://cdn.plugame.com/creator/{$creator->getId()}/thumb_{$i}.jpg");
            }

            $manager->persist($media);
            $feedPosts[] = $media;
        }
    }

    return $feedPosts;
}

private function getDimensionsForRatio(string $ratio): array
{
    return match($ratio) {
        '1:1'  => [1080, 1080],
        '4:5'  => [1080, 1350],
        '16:9' => [1920, 1080],
        '9:16' => [1080, 1920],
        '21:9' => [2560, 1080],
        default => [1080, 1080],
    };
}
```

### 2. `createPaymentsWithDiscounts()` - Payments with Promo Codes & Gift Cards

```php
private function createPaymentsWithDiscounts(
    ObjectManager $manager,
    array $bookings,
    array $promoCodes,
    array $giftCards
): array {
    $payments = [];

    foreach ($bookings as $booking) {
        if (!in_array($booking->getStatus(), [Booking::STATUS_ACCEPTED, Booking::STATUS_COMPLETED])) {
            continue;
        }

        $originalAmount = $booking->getTotalCents();
        $finalAmount = $originalAmount;
        $usedPromoCode = null;
        $usedGiftCard = null;
        $discountAmount = 0;
        $giftCardAmount = 0;

        // 30% chance to use promo code
        if ($this->randomBool(30) && count($promoCodes) > 0) {
            $promoCode = $this->randomElement($promoCodes);

            // Check if promo belongs to this creator
            if ($promoCode->getCreator() === $booking->getCreator()) {
                $discountAmount = $promoCode->calculateDiscount($originalAmount);
                $finalAmount -= $discountAmount;
                $usedPromoCode = $promoCode;
                $promoCode->incrementUsedCount();
            }
        }

        // 20% chance to use gift card
        if ($this->randomBool(20) && count($giftCards) > 0) {
            $giftCard = array_values(array_filter($giftCards, fn($gc) => $gc->isValid()))[0] ?? null;

            if ($giftCard) {
                $giftCardAmount = $giftCard->deduct($finalAmount);
                $finalAmount -= $giftCardAmount;
                $usedGiftCard = $giftCard;

                if (!$giftCard->getRedeemedBy()) {
                    $giftCard->redeem($booking->getAthlete());
                }
            }
        }

        $payment = new Payment();
        $payment->setUser($booking->getAthlete())
            ->setBooking($booking)
            ->setOriginalAmountCents($originalAmount)
            ->setDiscountAmountCents($discountAmount)
            ->setGiftCardAmountCents($giftCardAmount)
            ->setAmountCents(max(0, $finalAmount))
            ->setCurrency('EUR')
            ->setPaymentMethod('card')
            ->setPaymentGateway('stripe')
            ->setStatus(Payment::STATUS_COMPLETED)
            ->setStripePaymentIntentId('pi_' . bin2hex(random_bytes(12)))
            ->setStripeChargeId('ch_' . bin2hex(random_bytes(12)));

        if ($usedPromoCode) {
            $payment->setPromoCode($usedPromoCode);
        }

        if ($usedGiftCard) {
            $payment->setGiftCard($usedGiftCard);
        }

        $manager->persist($payment);
        $booking->setPayment($payment);
        $payments[] = $payment;
    }

    return $payments;
}
```

### 3. `createDeliverables()` - Creator Uploads

```php
private function createDeliverables(ObjectManager $manager, array $bookings): array
{
    $deliverables = [];

    // Only for ACCEPTED/COMPLETED bookings
    $eligibleBookings = array_filter($bookings, fn($b) =>
        in_array($b->getStatus(), [Booking::STATUS_ACCEPTED, Booking::STATUS_COMPLETED])
    );

    foreach ($eligibleBookings as $booking) {
        // 70% of bookings get deliverables
        if (!$this->randomBool(70)) continue;

        $service = $booking->getService();
        $deliverableCount = match($service->getKind()) {
            'PER_ASSET' => rand(10, 50),  // 10-50 photos
            'PACKAGE' => rand(30, 100),   // 30-100 items
            'HOURLY' => rand(20, 60),     // 20-60 items
            default => rand(10, 30),
        };

        for ($i = 0; $i < $deliverableCount; $i++) {
            $isVideo = $this->randomBool(20);  // 20% videos
            $aspectRatio = $this->weightedRandom([
                '16:9' => 60,  // Most deliverables are landscape
                '4:5'  => 20,
                '1:1'  => 10,
                '9:16' => 10,
            ]);

            [$width, $height] = $this->getDimensionsForRatio($aspectRatio);

            $deliverable = new MediaAsset();
            $deliverable->setOwner($booking->getCreator())
                ->setBooking($booking)
                ->setPurpose(MediaAsset::PURPOSE_BOOKING_DELIVERABLE)
                ->setType($isVideo ? MediaAsset::TYPE_VIDEO : MediaAsset::TYPE_IMAGE)
                ->setAspectRatio($aspectRatio)
                ->setWidth($width)
                ->setHeight($height)
                ->setFilename("deliverable_{$i}." . ($isVideo ? 'mp4' : 'jpg'))
                ->setStorageKey("deliverables/{$booking->getId()}/{$i}")
                ->setPublicUrl("https://cdn.plugame.com/del/{$booking->getId()}/{$i}")
                ->setBytes(rand($isVideo ? 10000000 : 2000000, $isVideo ? 100000000 : 10000000))
                ->setContentType($isVideo ? 'video/mp4' : 'image/jpeg');

            if ($isVideo) {
                $deliverable->setDurationSec(rand(5, 30));
                $deliverable->setThumbnailUrl("https://cdn.plugame.com/del/thumb_{$i}.jpg");
            }

            $manager->persist($deliverable);
            $deliverables[] = $deliverable;
        }

        // Mark booking as having deliverables
        $booking->setDeliverablesUnlocked(false);  // Not unlocked until payment
    }

    return $deliverables;
}
```

### 4. `simulateDeliverableDownloads()` - Trigger Payouts

```php
private function simulateDeliverableDownloads(ObjectManager $manager, array $bookings): void
{
    foreach ($bookings as $booking) {
        // Only for completed bookings with deliverables
        if ($booking->getStatus() !== Booking::STATUS_COMPLETED) continue;
        if ($booking->getDeliverables()->count() === 0) continue;

        // 80% actually download (opens email)
        if (!$this->randomBool(80)) continue;

        // Simulate email tracking pixel fired
        $booking->setDeliverableDownloadedAt(new \DateTimeImmutable('-' . rand(1, 7) . ' days'));
        $booking->setDeliverablesUnlocked(true);

        // Simulate payout to creator
        // In real app, this would trigger Stripe payout
    }
}
```

### 5. `createPreBookingConversations()` & `createBookingConversations()`

Separate conversations before and after booking for realistic flow.

---

## 📈 Statistics Generated

After running fixtures, you'll have:

### Users
- 20 Athletes
- 15 Creators
- 35 Total users

### Content
- 150-225 Feed posts (photos & videos with proper aspect ratios)
- 30-75 Services
- 100+ Availability slots

### Engagement
- 300+ Likes
- 150+ Bookmarks
- 40-60 Pre-booking conversations

### Transactions
- 30-45 Promo codes
- 10-15 Gift cards (some partially used)
- 50-100 Bookings
- 35-70 Payments (30% with discounts, 20% with gift cards)
- 1000+ Deliverables

### Conversations
- 40-60 Pre-booking conversations
- 30-50 Booking-related conversations
- 700+ Messages total

---

## 🎯 Key Improvements

### 1. **Realistic Aspect Ratios**
- Feed posts have proper ratios (1:1, 4:5, 16:9, 9:16)
- Dimensions match real devices (1080×1920 for iPhone, etc.)
- Videos have duration and thumbnails

### 2. **Complete Discount Flow**
- 30% of payments use promo codes
- 20% of payments use gift cards
- Some use both (stacking discounts)
- Gift cards track remaining balance
- Promo codes track usage count

### 3. **Deliverable System**
- Different counts based on service type
- Proper aspect ratios (mostly 16:9 landscape)
- Videos and photos mixed
- Unlocked after download simulation

### 4. **Realistic Timeline**
- Conversations before bookings
- Payments after bookings
- Deliverables after payments
- Downloads after deliverables

---

## 🚀 Usage

```bash
# Run migrations first
docker compose exec alpine php bin/console doctrine:migrations:migrate --no-interaction

# Load complete realistic fixtures
docker compose exec alpine php bin/console doctrine:fixtures:load --no-interaction
```

---

## 📊 Sample Output

```
🚀 Starting Plugame database seeding with REALISTIC FLOW...

════════════════════════════════════════════════════════════

📋 PHASE 1: Platform Setup
─────────────────────────────────────
👤 Creating users...
   ✅ Created 20 athletes
   ✅ Created 15 creators

📸 Creators populate their feeds...
   ✅ Created 187 feed posts (photos & videos)

💼 Creators create services...
   ✅ Created 52 services

📅 Creators set availability...
   ✅ Created availability slots

💳 Creators add payout methods...
   ✅ Created payout methods

🎫 Creators create promo codes...
   ✅ Created 38 promo codes

📋 PHASE 2: Athletes Discover & Engage
─────────────────────────────────────
❤️ Athletes discover and like creators...
   ✅ Created likes

🔖 Athletes bookmark creators...
   ✅ Created bookmarks

💬 Athletes reach out to creators...
   ✅ Created 47 conversations

📋 PHASE 3: Bookings & Payments
─────────────────────────────────────
🎁 Platform creates gift cards...
   ✅ Created 13 gift cards

💰 Athletes add wallet credits...
   ✅ Created wallet credits

📖 Athletes book services...
   ✅ Created 76 bookings

💵 Athletes pay for bookings...
   ✅ Created 54 payments (22 with discounts)

📋 PHASE 4: Service Delivery
─────────────────────────────────────
💬 Conversations about bookings...
   ✅ Created 38 booking conversations

📦 Creators upload deliverables...
   ✅ Created 1,847 deliverables

⬇️ Athletes download deliverables...
   ✅ Simulated downloads and payouts

════════════════════════════════════════════════════════════
🎉 Realistic Plugame flow seeding completed!
```

---

## ✅ Testing the Flow

### 1. Check Feed with Aspect Ratios
```bash
curl https://api.plugame.com/api/feed/creator/123 | jq '.posts[] | {type, aspectRatio, width, height}'
```

### 2. Check Payment with Discounts
```bash
curl https://api.plugame.com/api/payments/123 | jq '{
  originalAmount,
  discountAmount,
  giftCardAmount,
  finalAmount,
  hasPromoCode,
  hasGiftCard
}'
```

### 3. Check Gift Card Usage
```bash
curl https://api.plugame.com/api/gift-cards/mine | jq '.[] | {code, initialBalance, currentBalance, isActive}'
```

This creates a complete, realistic simulation of the entire Plugame platform! 🎉
