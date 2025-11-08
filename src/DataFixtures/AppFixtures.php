<?php

// src/DataFixtures/AppFixtures.php - COMPLETE WITH CONVERSATIONS & MESSAGES

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\ServiceOffering;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\WalletCredit;
use App\Entity\PayoutMethod;
use App\Entity\AvailabilitySlot;
use App\Entity\Like;
use App\Entity\Bookmark;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\CreatorProfile;
use App\Entity\AthleteProfile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\MediaAsset;

class AppFixtures extends Fixture
{
    private const PASSWORD = 'password123';

    // Sports list
    private const SPORTS = [
        'Football', 'Basketball', 'Tennis', 'Swimming', 'Running',
        'Cycling', 'Yoga', 'CrossFit', 'Boxing', 'MMA',
        'Volleyball', 'Rugby', 'Golf', 'Skiing', 'Surfing',
    ];

    // French cities
    private const LOCATIONS = [
        'Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice',
        'Nantes', 'Montpellier', 'Strasbourg', 'Bordeaux', 'Lille',
    ];

    // Service types
    private const SERVICE_TYPES = [
        'Photo Session' => 'Professional sports photography session',
        'Video Session' => 'Complete video recording and editing',
        'Training Session' => 'Personal training and coaching',
        'Consultation' => 'Expert advice and consultation',
        'Event Coverage' => 'Complete event photography/videography',
    ];

    // Bank names
    private const BANKS = [
        'BNP Paribas', 'Société Générale', 'Crédit Agricole',
        'LCL', 'Caisse d\'Épargne', 'Banque Populaire',
    ];

    // Sample messages
    private const MESSAGE_TEMPLATES = [
        'Hi! I\'m interested in booking your services.',
        'Hello! Could you tell me more about your availability?',
        'I saw your profile and I\'d love to work with you.',
        'What are your rates for a 2-hour session?',
        'Do you have availability next week?',
        'Thanks for the quick response!',
        'That sounds perfect! Let\'s book it.',
        'Great! Looking forward to working with you.',
        'Can you send me some examples of your work?',
        'I need this for an event next month.',
        'Perfect! I\'ll confirm the booking shortly.',
        'Thank you for the information!',
        'Let me check my schedule and get back to you.',
        'Yes, that works for me!',
        'Sounds good! See you then.',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        echo "🚀 Starting database seeding...\n\n";

        // 1. Create Users
        echo "👤 Creating users...\n";
        $athletes = $this->createAthletes($manager, 20);
        $creators = $this->createCreators($manager, 15);
        $allUsers = array_merge($athletes, $creators);
        echo "   ✅ Created " . count($athletes) . " athletes\n";
        echo "   ✅ Created " . count($creators) . " creators\n\n";

        // 2. Create Services
        echo "💼 Creating services...\n";
        $services = $this->createServices($manager, $creators);
        echo "   ✅ Created " . count($services) . " services\n\n";

        // 3. Create Availability Slots
        echo "📅 Creating availability slots...\n";
        $this->createAvailabilitySlots($manager, $creators);
        echo "   ✅ Created availability slots for creators\n\n";

        // 4. Create Payout Methods
        echo "💳 Creating payout methods...\n";
        $this->createPayoutMethods($manager, $creators);
        echo "   ✅ Created payout methods for creators\n\n";

        // 5. Create Wallet Credits
        echo "💰 Creating wallet credits...\n";
        $this->createWalletCredits($manager, $athletes);
        echo "   ✅ Created wallet credits for athletes\n\n";

        // 6. Create Bookings
        echo "📖 Creating bookings...\n";
        $bookings = $this->createBookings($manager, $athletes, $services);
        echo "   ✅ Created " . count($bookings) . " bookings\n\n";

        // 7. Create Payments
        echo "💵 Creating payments...\n";
        $this->createPayments($manager, $bookings);
        echo "   ✅ Created payments for bookings\n\n";

        // 8. Create Conversations & Messages
        echo "💬 Creating conversations and messages...\n";
        $conversations = $this->createConversations($manager, $athletes, $creators, $bookings);
        echo "   ✅ Created " . count($conversations) . " conversations\n\n";

        // 9. Create Likes
        echo "❤️ Creating likes...\n";
        $this->createLikes($manager, $allUsers, $creators);
        echo "   ✅ Created likes\n\n";

        // 10. Create Bookmarks
        echo "🔖 Creating bookmarks...\n";
        $this->createBookmarks($manager, $athletes, $creators);
        echo "   ✅ Created bookmarks\n\n";

        $manager->flush();

        echo "🎉 Seeding completed successfully!\n\n";
        $this->printSummary($allUsers, $services, $bookings, $conversations);
    }

    // ============================================
    // CREATE USERS
    // ============================================

    private function createAthletes(ObjectManager $manager, int $count): array
    {
        $athletes = [];

        for ($i = 1; $i <= $count; $i++) {
            $user = new User();
            $user->setEmail("athlete{$i}@example.com");
            $user->setUsername("athlete{$i}");
            $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
            $user->setRoles(['ROLE_USER', 'ROLE_ATHLETE']);
            $user->setFullName($this->generateName());
            $user->setBio($this->generateBio('athlete'));
            $user->setSport($this->randomElement(self::SPORTS));
            $user->setLocation($this->randomElement(self::LOCATIONS));
            $user->setAvatarUrl("https://i.pravatar.cc/300?img={$i}");
            $user->setIsPlugPlus($this->randomBool(20)); // 20% are Plug+
            $user->setIsVerified($this->randomBool(60));
            $user->setPhoneNumber('+33' . rand(600000000, 699999999));
            $user->setLocale('fr');
            $user->setTimezone('Europe/Paris');

            // Persist user first to satisfy possible constraints (not mandatory with Doctrine but clearer)
            $manager->persist($user);

            // Create AthleteProfile linked to this user
            $profile = new AthleteProfile($user);
            $profile->setDisplayName($user->getFullName() ?: $user->getUsername());
            $profile->setSport($user->getSport());
            $profile->setLevel($this->randomElement(['Beginner','Intermediate','Advanced','Pro']));
            $profile->setBio($user->getBio());
            $profile->setHomeCity($user->getLocation());
            $profile->setCoverPhoto("https://picsum.photos/1200/300?random=athlete_cover_{$i}");
            $profile->setAchievements([
                'Best performance ' . rand(2018, 2024),
                'Local champ ' . rand(2015, 2023)
            ]);
            $profile->setStats([
                'sessions' => rand(10, 500),
                'wins' => rand(0, 100)
            ]);

            // Link profile to user entity and persist
            $user->setAthleteProfile($profile);
            $manager->persist($profile);

            $athletes[] = $user;
        }

        return $athletes;
    }

    private function createCreators(ObjectManager $manager, int $count): array
    {
        $creators = [];

        for ($i = 1; $i <= $count; $i++) {
            $user = new User();
            $user->setEmail("creator{$i}@example.com");
            $user->setUsername("creator{$i}");
            $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
            $user->setRoles(['ROLE_USER', 'ROLE_CREATOR']);
            $user->setFullName($this->generateName());
            $user->setBio($this->generateBio('creator'));
            $user->setSport($this->randomElement(self::SPORTS));
            $user->setLocation($this->randomElement(self::LOCATIONS));
            $user->setAvatarUrl("https://i.pravatar.cc/300?img=" . ($count + $i));
            $user->setCoverUrl("https://picsum.photos/1200/400?random={$i}");
            $user->setIsVerified($this->randomBool(70));
            $user->setPhoneNumber('+33' . rand(600000000, 699999999));
            $user->setLocale('fr');
            $user->setTimezone('Europe/Paris');

            $manager->persist($user);

            $avatar = new MediaAsset();
            $avatar->setOwner($user)
                   ->setPurpose(MediaAsset::PURPOSE_AVATAR)
                   ->setFilename('avatar_' . $user->getId() . '.jpg')
                   ->setPublicUrl('https://picsum.photos/200?random=' . $user->getId())
                   ->setType(MediaAsset::TYPE_IMAGE)
                   ->setBytes(rand(10000, 50000))
                   ->setStorageKey('avatar_' . $user->getId() . '.jpg');

            $manager->persist($avatar);

            // Create CreatorProfile linked to this user
            $profile = new CreatorProfile($user);
            $profile->setDisplayName($user->getFullName() ?: $user->getUsername());
            $profile->setBio($user->getBio());
            $profile->setBaseCity($user->getLocation());
            $profile->setTravelRadiusKm($this->randomElement([10, 25, 50, 100]));
            $profile->setHourlyRateCents($this->randomElement([3000, 5000, 8000, 12000])); // e.g. 30€,50€
            $profile->setGear([
                'camera' => 'Sony A7' . rand(2, 4),
                'lens' => '24-70mm'
            ]);
            $profile->setSpecialties([$user->getSport(), 'action', 'editorial']);
            $profile->setCoverPhoto("https://picsum.photos/1200/300?random=creator_cover_{$i}");
            $profile->setResponseTime($this->randomElement([60, 120, 240])); // minutes
            $profile->setAcceptanceRate(round(rand(70, 99) / 100, 2));
            $profile->setCompletionRate(round(rand(80, 100) / 100, 2));
            $profile->setVerified($this->randomBool(50));
            $profile->setFeaturedWork([
                "https://picsum.photos/800/600?random=featured_{$i}_1",
                "https://picsum.photos/800/600?random=featured_{$i}_2"
            ]);
            $profile->setAvgRating((string)round(rand(30, 50) / 10, 1)); // e.g. "4.6"
            $profile->setRatingsCount(rand(0, 400));

            $numMedia = rand(1, 4);
            for ($j = 0; $j < $numMedia; $j++) {
                $media = new MediaAsset();
                $media->setOwner($user)
                    ->setCreatorProfile($profile)
                    ->setPurpose(MediaAsset::PURPOSE_CREATOR_FEED)
                    ->setFilename('feed_' . $user->getId() . '_' . $j . '.jpg')
                    ->setPublicUrl('https://picsum.photos/400/300?random=' . ($user->getId() + $j))
                    ->setType(MediaAsset::TYPE_IMAGE)
                    ->setStorageKey('feed_' . $user->getId() . '_' . $i . '.jpg')
                    ->setBytes(rand(50000, 150000))
                    ->setCaption('test');

                $manager->persist($media);
            }


            // Link profile to user and persist
            $user->setCreatorProfile($profile);
            $manager->persist($profile);

            $creators[] = $user;
        }

        return $creators;
    }


    // ============================================
    // CREATE SERVICES
    // ============================================

    private function createServices(ObjectManager $manager, array $creators): array
    {
        $services = [];

        foreach ($creators as $creator) {
            $serviceCount = rand(2, 5);

            for ($i = 0; $i < $serviceCount; $i++) {
                [$title, $description] = $this->randomServiceType();

                // Pick a kind randomly for demo purposes
                $kinds = ['HOURLY', 'PER_ASSET', 'PACKAGE'];
                $kind = $kinds[array_rand($kinds)];

                $service = new ServiceOffering($creator);
                $service->setCreator($creator);
                $service->setTitle($title . ' - ' . $creator->getSport());
                $service->setDescription($description);
                $service->setKind($kind);
                $service->setCurrency('EUR');
                $service->setIsActive($this->randomBool(90));
                $service->setFeatured($this->randomBool(50));

                switch ($kind) {
                    case 'HOURLY':
                        $service->setDurationMin($this->randomDuration());
                        $service->setPriceCents($this->randomPrice());
                        $service->setDeliverables($this->generateDeliverables());
                        break;

                    case 'PER_ASSET':
                        $assets = ['PHOTO', 'VIDEO'];
                        $asset = $assets[array_rand($assets)];
                        $service->setAssetType($asset);
                        $service->setPricePerAssetCents($this->randomPrice());
                        $service->setDeliverables($this->generateDeliverables());
                        break;

                    case 'PACKAGE':
                        $service->setPriceTotalCents($this->randomPrice());
                        $service->setIncludes([
                            'hours' => rand(1, 4),
                            'photos' => rand(10, 30),
                            'videos' => rand(0, 2)
                        ]);
                        $service->setDeliverables($this->generateDeliverables());
                        break;
                }

                $manager->persist($service);
                $services[] = $service;
            }
        }

        return $services;
    }

    // ============================================
    // CREATE AVAILABILITY SLOTS
    // ============================================

    private function createAvailabilitySlots(ObjectManager $manager, array $creators): void
    {
        foreach ($creators as $creator) {
            // Create slots for next 7 days
            for ($day = 0; $day < 7; $day++) {
                $baseDate = new \DateTimeImmutable("+{$day} days");
                $baseDate = $baseDate->setTime(0, 0, 0);

                // Morning slot (9h-12h)
                if ($this->randomBool(80)) {
                    $startTime = $baseDate->setTime(9, 0);
                    $endTime   = $baseDate->setTime(12, 0);

                    $slot = new AvailabilitySlot($creator, $startTime, $endTime);
                    $manager->persist($slot);
                }

                // Afternoon slot (14h-18h)
                if ($this->randomBool(70)) {
                    $startTime = $baseDate->setTime(14, 0);
                    $endTime   = $baseDate->setTime(18, 0);

                    $slot = new AvailabilitySlot($creator, $startTime, $endTime);
                    $manager->persist($slot);
                }
            }
        }
    }

    // ============================================
    // CREATE PAYOUT METHODS
    // ============================================

    private function createPayoutMethods(ObjectManager $manager, array $creators): void
    {
        foreach ($creators as $creator) {
            // each creator: 1-2 payout methods (bank + maybe stripe)
            // 80% get a bank account, 60% get a stripe express
            if ($this->randomBool(80)) {
                $bank = new PayoutMethod();
                $bank->setUser($creator);
                $bank->setType(PayoutMethod::TYPE_BANK_ACCOUNT);
                $bank->setBankName($this->randomElement(self::BANKS));
                $bank->setAccountLast4($this->generateLast4());
                $bank->setIsDefault(true);
                $bank->setIsVerified($this->randomBool(85));
                $bank->setMetadata(['note' => 'Seed bank account']);
                $manager->persist($bank);
            }

            if ($this->randomBool(60)) {
                // simulate a Stripe Express connected account id
                $stripeAccountId = 'acct_' . bin2hex(random_bytes(6));
                $stripe = new PayoutMethod();
                $stripe->setUser($creator);
                $stripe->setType(PayoutMethod::TYPE_STRIPE_EXPRESS);
                $stripe->setStripeAccountId($stripeAccountId);
                $stripe->setIsDefault(!$creator->getPayoutMethods() || count($creator->getPayoutMethods()) === 0);
                $stripe->setIsVerified($this->randomBool(90));
                $stripe->setMetadata(['seed' => true]);
                $manager->persist($stripe);

                // Optionally keep a copy on the user (if your User has a stripeAccountId field)
                if (method_exists($creator, 'setStripeAccountId')) {
                    $creator->setStripeAccountId($stripeAccountId);
                    $manager->persist($creator);
                }
            }
        }
    }


    // ============================================
    // CREATE WALLET CREDITS
    // ============================================

    private function createWalletCredits(ObjectManager $manager, array $athletes): void
    {
        foreach ($athletes as $athlete) {
            if ($this->randomBool(70)) {
                // Initial purchase
                $purchase = new WalletCredit();
                $purchase->setUser($athlete);
                $purchase->setAmountCents(rand(2000, 10000));
                $purchase->setType(WalletCredit::TYPE_PURCHASE);
                $purchase->setDescription('Initial credit purchase');
                $purchase->setExpiresAt(new \DateTime('+1 year'));
                $manager->persist($purchase);

                // Some have bonus credits
                if ($this->randomBool(30)) {
                    $bonus = new WalletCredit();
                    $bonus->setUser($athlete);
                    $bonus->setAmountCents(rand(500, 2000));
                    $bonus->setType(WalletCredit::TYPE_BONUS);
                    $bonus->setDescription('Welcome bonus');
                    $bonus->setExpiresAt(new \DateTime('+6 months'));
                    $manager->persist($bonus);
                }
            }
        }
    }

    // ============================================
    // CREATE BOOKINGS
    // ============================================

    private function createBookings(ObjectManager $manager, array $athletes, array $services): array
    {
        $bookings = [];
        $statuses = [
            Booking::STATUS_PENDING => 10,
            Booking::STATUS_ACCEPTED => 50,
            Booking::STATUS_COMPLETED => 30,
            Booking::STATUS_CANCELLED => 10,
        ];

        $bookingCount = rand(50, 100);

        for ($i = 0; $i < $bookingCount; $i++) {
            $athlete = $this->randomElement($athletes);
            $service = $this->randomElement($services);

            if ($service->getCreator() === $athlete) {
                continue;
            }

            $booking = new Booking();
            $booking->setAthlete($athlete);
            $booking->setCreator($service->getCreator());
            $booking->setService($service);

            $daysOffset = rand(-60, 30);
            $hour = rand(9, 17);

            $start = (new \DateTimeImmutable("{$daysOffset} days"))->setTime($hour, 0, 0);
            $durationMin = $service->getDurationMin() ?? 60;
            $end = $start->add(new \DateInterval("PT{$durationMin}M"));

            $booking->setStartTime($start);
            $booking->setEndTime($end);

            $servicePrice = $service->getPriceCents() ?? 0;
            $booking->setSubtotalCents($servicePrice);
            $fee = (int) round($servicePrice * 0.05);
            $tax = (int) round($servicePrice * 0.07);
            $booking->setFeeCents($fee);
            $booking->setTaxCents($tax);
            $booking->setTotalCents(max(0, $servicePrice + $fee + $tax));

            if ($daysOffset > 0) {
                $booking->setStatus(Booking::STATUS_ACCEPTED);
            } else {
                $booking->setStatus($this->weightedRandom($statuses));
            }

            $booking->setNotes($this->generateBookingNotes());

            $manager->persist($booking);
            $bookings[] = $booking;
        }

        return $bookings;
    }

    // ============================================
    // CREATE PAYMENTS
    // ============================================

    private function createPayments(ObjectManager $manager, array $bookings): void
    {
        foreach ($bookings as $booking) {
            // choose to create payment only for ACCEPTED / COMPLETED
            if (!in_array($booking->getStatus(), [Booking::STATUS_ACCEPTED, Booking::STATUS_COMPLETED])) {
                continue;
            }

            $service = $booking->getService();
            if (!$service) {
                continue;
            }

            // Determine price depending on service kind
            $amountCents = 0;
            $kind = strtoupper((string)$service->getKind());

            if ($kind === 'PER_ASSET') {
                $amountCents = $service->getPricePerAssetCents() ?? 0;
                // optionally multiply by assets requested if that info exists on booking
                // e.g. $amountCents *= max(1, $booking->getAssetsCount() ?? 1);
            } elseif ($kind === 'PACKAGE') {
                $amountCents = $service->getPriceTotalCents() ?? 0;
            } else { // HOURLY fallback
                $amountCents = $service->getPriceCents() ?? 0;
            }

            // Fallback: if still zero, use a random small price to avoid 0 totals in seed
            if ($amountCents <= 0) {
                $amountCents = $this->randomPrice();
            }

            $payment = new Payment();
            $payment->setUser($booking->getAthlete());
            $payment->setBooking($booking);
            $payment->setAmountCents($amountCents);
            $payment->setCurrency('EUR');

            // choose method: most are 'card', some 'wallet'
            $useWallet = $this->randomBool(20); // 20% pay from wallet for variety
            $payment->setPaymentMethod($useWallet ? 'wallet' : 'card');
            $payment->setPaymentGateway($useWallet ? 'wallet' : 'stripe');

            // status: completed if booking completed, pending otherwise
            if ($booking->getStatus() === Booking::STATUS_COMPLETED) {
                $payment->setStatus(Payment::STATUS_COMPLETED);
            } else {
                $payment->setStatus(Payment::STATUS_PENDING);
            }

            // fake stripe ids
            $payment->setStripePaymentIntentId('pi_' . bin2hex(random_bytes(12)));
            if ($payment->isCompleted()) {
                $payment->setStripeChargeId('ch_' . bin2hex(random_bytes(12)));
            }

            // metadata: platform fee example
            $platformFee = (int) round($amountCents * 0.15); // 15% platform fee
            $payment->setMetadata([
                'platform_fee' => $platformFee,
                'service_kind' => $kind,
                'seed' => true,
            ]);

            $manager->persist($payment);

            // If wallet used, persist a WalletCredit usage/debit row
            if ($useWallet) {
                $walletDebit = new WalletCredit();
                $walletDebit->setUser($booking->getAthlete());
                $walletDebit->setBooking($booking);
                $walletDebit->setPayment($payment);
                $walletDebit->setAmountCents($amountCents);
                $walletDebit->setType(WalletCredit::TYPE_USAGE);
                $walletDebit->setDescription('Seeded wallet usage for booking ' . substr($booking->getId(), 0, 8));
                $manager->persist($walletDebit);
            }

            // link payment to booking if you already have a setBooking relation or field
            $booking->setPayment($payment);
            $manager->persist($booking);
        }
    }


    // ============================================
    // CREATE CONVERSATIONS & MESSAGES
    // ============================================

    private function createConversations(ObjectManager $manager, array $athletes, array $creators, array $bookings): array
    {
        $conversations = [];

        // Create conversations from bookings (50%)
        foreach ($bookings as $booking) {
            if ($this->randomBool(50)) {
                $conversation = new Conversation();
                $conversation->setAthlete($booking->getAthlete());
                $conversation->setCreator($booking->getCreator());
                $conversation->setBooking($booking);

                // Create 5-15 messages
                $messageCount = rand(5, 15);
                $this->createMessages($manager, $conversation, $messageCount);

                $manager->persist($conversation);
                $conversations[] = $conversation;
            }
        }

        // Create random conversations (no booking)
        $randomConvCount = rand(20, 40);
        for ($i = 0; $i < $randomConvCount; $i++) {
            $athlete = $this->randomElement($athletes);
            $creator = $this->randomElement($creators);

            $conversation = new Conversation();
            $conversation->setAthlete($athlete);
            $conversation->setCreator($creator);

            // Create 3-10 messages
            $messageCount = rand(3, 10);
            $this->createMessages($manager, $conversation, $messageCount);

            $manager->persist($conversation);
            $conversations[] = $conversation;
        }

        return $conversations;
    }

    private function createMessages(ObjectManager $manager, Conversation $conversation, int $count): void
    {
        $athlete = $conversation->getAthlete();
        $creator = $conversation->getCreator();
        $users = [$athlete, $creator];

        $baseDate = new \DateTimeImmutable('-' . rand(1, 30) . ' days');

        for ($i = 0; $i < $count; $i++) {
            $message = new Message();
            $message->setConversation($conversation);

            // Alternate between athlete and creator
            $sender = $users[$i % 2];
            $message->setSender($sender);

            $message->setContent($this->randomElement(self::MESSAGE_TEMPLATES));

            // Add some time between messages
            $minutesOffset = $i * rand(10, 180); // 10 minutes to 3 hours between messages
            $createdAt = $baseDate->modify("+{$minutesOffset} minutes");

            // Use reflection to set createdAt (since it's normally auto-set)
            $reflection = new \ReflectionClass($message);
            $property = $reflection->getProperty('createdAt');
            $property->setAccessible(true);
            $property->setValue($message, $createdAt);

            // 70% of messages are read
            if ($this->randomBool(70)) {
                $readAt = $createdAt->modify('+' . rand(1, 120) . ' minutes');
                $message->setReadAt($readAt);
            }

            $manager->persist($message);

            // Update conversation's last message info
            $conversation->setLastMessageAt($createdAt);
            $conversation->setLastMessagePreview($message->getContent());
        }

        // Set unread count (30% have unread messages)
        if ($this->randomBool(30)) {
            $conversation->setUnreadCount(rand(1, 5));
        } else {
            $conversation->setUnreadCount(0);
        }
    }

    // ============================================
    // CREATE LIKES
    // ============================================

    private function createLikes(ObjectManager $manager, array $allUsers, array $creators): void
    {
        foreach ($allUsers as $user) {
            $likeCount = rand(5, 15);
            $likedCreators = $this->randomElements($creators, $likeCount);

            foreach ($likedCreators as $creator) {
                if ($creator === $user) {
                    continue;
                }

                $like = new Like($user, $creator);
                $manager->persist($like);
            }
        }
    }

    // ============================================
    // CREATE BOOKMARKS
    // ============================================

    private function createBookmarks(ObjectManager $manager, array $athletes, array $creators): void
    {
        foreach ($athletes as $athlete) {
            $bookmarkCount = rand(3, 10);
            $bookmarkedCreators = $this->randomElements($creators, $bookmarkCount);

            foreach ($bookmarkedCreators as $creator) {
                $bookmark = new Bookmark($athlete, $creator);
                $bookmark->setCollection($this->randomCollection());
                $manager->persist($bookmark);
            }
        }
    }




    // ============================================
    // HELPER METHODS
    // ============================================

    private function generateName(): string
    {
        $firstNames = ['Jean', 'Marie', 'Pierre', 'Sophie', 'Lucas', 'Emma', 'Thomas', 'Julie', 'Antoine', 'Léa'];
        $lastNames = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau'];

        return $this->randomElement($firstNames) . ' ' . $this->randomElement($lastNames);
    }

    private function generateBio(string $type): string
    {
        $bios = [
            'athlete' => [
                'Passionate about sports and fitness. Always looking to improve!',
                'Dedicated athlete striving for excellence.',
                'Sports enthusiast. Let\'s train together!',
                'Fitness lover. Health is wealth!',
            ],
            'creator' => [
                'Professional sports photographer with 10+ years experience.',
                'Creating amazing sports content. Book your session today!',
                'Capturing your best moments on the field.',
                'Expert videographer specializing in sports events.',
            ],
        ];

        return $this->randomElement($bios[$type]);
    }

    private function randomServiceType(): array
    {
        $types = self::SERVICE_TYPES;
        $title = array_rand($types);
        return [$title, $types[$title]];
    }

    private function randomPrice(): int
    {
        $prices = [5000, 7500, 10000, 15000, 20000, 25000, 30000, 40000, 50000];
        return $this->randomElement($prices);
    }

    private function randomDuration(): int
    {
        $durations = [30, 60, 90, 120, 180, 240];
        return $this->randomElement($durations);
    }

    private function generateDeliverables(): string
    {
        $options = [
            '20 edited photos in high resolution',
            '50 professional photos + online gallery',
            '2-hour video edited and delivered within 48h',
            '30-minute highlights video + raw footage',
            'Complete event coverage with 100+ photos',
        ];

        return $this->randomElement($options);
    }

    private function generateBookingNotes(): string
    {
        $notes = [
            'Looking forward to this session!',
            'Please bring extra equipment for outdoor shooting.',
            'Need photos for my social media.',
            'Event starts at 10 AM sharp.',
            'Special requirements: action shots needed.',
        ];

        return $this->randomBool(60) ? $this->randomElement($notes) : '';
    }

    private function generateLast4(): string
    {
        return str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function randomCollection(): string
    {
        $collections = ['Favorites', 'To Book', 'Top Photographers', 'My List'];
        return $this->randomElement($collections);
    }

    private function randomElement(array $array): mixed
    {
        return $array[array_rand($array)];
    }

    private function randomElements(array $array, int $count): array
    {
        shuffle($array);
        return array_slice($array, 0, min($count, count($array)));
    }

    private function randomBool(int $truePercentage = 50): bool
    {
        return rand(1, 100) <= $truePercentage;
    }

    private function weightedRandom(array $weights): string
    {
        $rand = rand(1, array_sum($weights));
        foreach ($weights as $option => $weight) {
            $rand -= $weight;
            if ($rand <= 0) {
                return $option;
            }
        }
        return array_key_first($weights);
    }

    private function printSummary(array $users, array $services, array $bookings, array $conversations): void
    {
        echo "📊 SUMMARY:\n";
        echo "   • Users: " . count($users) . "\n";
        echo "   • Services: " . count($services) . "\n";
        echo "   • Bookings: " . count($bookings) . "\n";
        echo "   • Conversations: " . count($conversations) . "\n";
        echo "\n";
        echo "🔐 LOGIN CREDENTIALS:\n";
        echo "   Email: athlete1@example.com\n";
        echo "   Email: creator1@example.com\n";
        echo "   Password: " . self::PASSWORD . "\n";
    }
}
