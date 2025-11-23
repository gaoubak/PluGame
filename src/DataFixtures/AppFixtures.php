<?php

// src/DataFixtures/AppFixtures.php - ENHANCED WITH PROPER RELATIONSHIPS & MORE DATA

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
use App\Entity\PromoCode;
use App\Entity\GiftCard;
use App\Entity\Review;
use App\Entity\OAuthProvider;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\MediaAsset;

class AppFixtures extends Fixture
{
    private const PASSWORD = 'password123';

    // Expanded sports list
    private const SPORTS = [
        'Football', 'Basketball', 'Tennis', 'Swimming', 'Running',
        'Cycling', 'Yoga', 'CrossFit', 'Boxing', 'MMA',
        'Volleyball', 'Rugby', 'Golf', 'Skiing', 'Surfing',
        'Climbing', 'Hiking', 'Karate', 'Judo', 'Taekwondo',
        'Baseball', 'Softball', 'Hockey', 'Cricket', 'Badminton',
        'Table Tennis', 'Squash', 'Handball', 'Water Polo', 'Rowing',
        'Gymnastics', 'Pilates', 'Zumba', 'Dance', 'Parkour',
        'Skateboarding', 'Rollerblading', 'BMX', 'Motocross', 'Triathlon',
        'Marathon', 'Ultra Running', 'Trail Running', 'Powerlifting', 'Bodybuilding',
        'Crossfit', 'Calisthenics', 'Kettlebell', 'Spinning', 'Aerobics'
    ];

    // Expanded French cities
    private const LOCATIONS = [
        'Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice',
        'Nantes', 'Montpellier', 'Strasbourg', 'Bordeaux', 'Lille',
        'Rennes', 'Reims', 'Saint-Étienne', 'Toulon', 'Le Havre',
        'Grenoble', 'Dijon', 'Angers', 'Nîmes', 'Villeurbanne',
        'Saint-Denis', 'Le Mans', 'Aix-en-Provence', 'Clermont-Ferrand', 'Brest',
        'Tours', 'Amiens', 'Limoges', 'Annecy', 'Perpignan',
        'Boulogne-Billancourt', 'Orléans', 'Mulhouse', 'Caen', 'Rouen',
        'Nancy', 'Saint-Paul', 'Argenteuil', 'Montreuil', 'Roubaix'
    ];

    // Expanded service types
    private const SERVICE_TYPES = [
        'Photo Session' => 'Professional sports photography session with high-quality equipment',
        'Video Session' => 'Complete video recording and editing with cinematic quality',
        'Training Session' => 'Personal training and coaching tailored to your goals',
        'Consultation' => 'Expert advice and consultation for performance improvement',
        'Event Coverage' => 'Complete event photography and videography coverage',
        'Action Shots' => 'Dynamic action photography capturing peak moments',
        'Portrait Session' => 'Professional athlete portrait photography',
        'Team Coverage' => 'Full team photo and video coverage for tournaments',
        'Drone Footage' => 'Aerial drone videography for sports events',
        'Slow Motion' => 'High-speed camera slow motion video capture',
        'Live Streaming' => 'Professional live streaming setup and coverage',
        'Highlight Reel' => 'Custom highlight reel creation from your footage',
        'Social Media Pack' => 'Complete social media content package',
        'Behind the Scenes' => 'Behind the scenes documentary-style coverage',
        'Commercial Shoot' => 'Commercial-grade photography for sponsorships',
        'Nutrition Coaching' => 'Personalized nutrition planning and coaching',
        'Mental Coaching' => 'Sports psychology and mental performance coaching',
        'Injury Prevention' => 'Injury prevention assessment and training',
        'Recovery Session' => 'Post-workout recovery and mobility session',
        'Technique Analysis' => 'Video analysis and technique improvement',
    ];

    // Expanded bank names with IBAN/BIC data
    private const BANKS = [
        'BNP Paribas' => ['bic' => 'BNPAFRPP', 'iban_prefix' => 'FR76'],
        'Société Générale' => ['bic' => 'SOGEFRPP', 'iban_prefix' => 'FR76'],
        'Crédit Agricole' => ['bic' => 'AGRIFRPP', 'iban_prefix' => 'FR76'],
        'LCL' => ['bic' => 'CRLYFRPP', 'iban_prefix' => 'FR76'],
        'Caisse d\'Épargne' => ['bic' => 'CEPAFRPP', 'iban_prefix' => 'FR76'],
        'Banque Populaire' => ['bic' => 'CCBPFRPP', 'iban_prefix' => 'FR76'],
        'Crédit Mutuel' => ['bic' => 'CMCIFRPP', 'iban_prefix' => 'FR76'],
        'La Banque Postale' => ['bic' => 'PSSTFRPP', 'iban_prefix' => 'FR76'],
        'Boursorama' => ['bic' => 'BOUSFRPP', 'iban_prefix' => 'FR76'],
        'Hello Bank' => ['bic' => 'BNPAFRPP', 'iban_prefix' => 'FR76'],
        'Fortuneo' => ['bic' => 'AGRIFRPP', 'iban_prefix' => 'FR76'],
        'N26' => ['bic' => 'NTSBDEB1', 'iban_prefix' => 'FR76'],
    ];

    // Expanded message templates
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
        'What equipment do you use?',
        'How long does post-production take?',
        'Can we reschedule to another date?',
        'Do you offer package deals?',
        'I have a specific vision for this shoot.',
        'Can you provide references from past clients?',
        'What\'s your cancellation policy?',
        'I need the files delivered by Friday.',
        'Do you travel for shoots?',
        'Can we do an outdoor session?',
        'I\'m looking for something unique and creative.',
        'What\'s included in the standard package?',
        'Can I see your portfolio?',
        'Do you have insurance coverage?',
        'I need both photos and videos.',
        'What file formats do you deliver?',
        'Can you edit out the background?',
        'I\'m preparing for a competition.',
        'This is for my professional profile.',
        'Can we include my team in the shoot?',
        'I need drone footage as well.',
        'What\'s your typical turnaround time?',
        'Can you work with my tight budget?',
        'I need this for social media promotion.',
        'Do you offer payment plans?',
    ];

    // Expanded first names
    private const FIRST_NAMES = [
        'Jean', 'Marie', 'Pierre', 'Sophie', 'Lucas', 'Emma', 'Thomas', 'Julie',
        'Antoine', 'Léa', 'Nicolas', 'Camille', 'Alexandre', 'Chloé', 'Maxime',
        'Sarah', 'Julien', 'Laura', 'Benjamin', 'Manon', 'Romain', 'Océane',
        'Mathieu', 'Pauline', 'Guillaume', 'Marine', 'Florian', 'Anaïs', 'Sébastien',
        'Margaux', 'Kevin', 'Justine', 'Anthony', 'Mélanie', 'Clément', 'Caroline',
        'Vincent', 'Audrey', 'Mickael', 'Émilie', 'David', 'Céline', 'Simon',
        'Marion', 'Hugo', 'Amélie', 'Adrien', 'Laurie', 'Thibault', 'Lucie'
    ];

    // Expanded last names
    private const LAST_NAMES = [
        'Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit',
        'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Michel',
        'Garcia', 'David', 'Bertrand', 'Roux', 'Vincent', 'Fournier', 'Morel',
        'Girard', 'André', 'Lefèvre', 'Mercier', 'Dupont', 'Lambert', 'Bonnet',
        'François', 'Martinez', 'Legrand', 'Garnier', 'Faure', 'Rousseau', 'Blanc',
        'Guerin', 'Muller', 'Henry', 'Roussel', 'Nicolas', 'Perrin', 'Morin',
        'Mathieu', 'Clement', 'Gauthier', 'Dumont', 'Lopez', 'Fontaine', 'Chevalier'
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        echo "🚀 Starting Plugame database seeding with REALISTIC FLOW...\n\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        // PHASE 1: Platform Setup
        echo "📋 PHASE 1: Platform Setup\n";
        echo "─────────────────────────────────────\n";

        // 1. Create Users (Athletes & Creators)
        echo "👤 Creating users...\n";
        $athletes = $this->createAthletes($manager, 30);
        $creators = $this->createCreators($manager, 25);
        $allUsers = array_merge($athletes, $creators);
        echo "   ✅ Created " . count($athletes) . " athletes\n";
        echo "   ✅ Created " . count($creators) . " creators\n";

        // Create OAuth test users
        echo "🔐 Creating OAuth test users...\n";
        $oauthUsers = $this->createOAuthUsers($manager);
        $allUsers = array_merge($allUsers, $oauthUsers);
        echo "   ✅ Created " . count($oauthUsers) . " OAuth users (Google + Apple)\n\n";

        // 2. Creators set up their profiles with feed content
        echo "📸 Creators populate their feeds...\n";
        $feedPosts = $this->createCreatorFeedContent($manager, $creators);
        echo "   ✅ Created " . count($feedPosts) . " feed posts (photos & videos)\n\n";

        // 3. Creators create service offerings
        echo "💼 Creators create services...\n";
        $services = $this->createServices($manager, $creators);
        echo "   ✅ Created " . count($services) . " services\n\n";

        // 4. Creators set availability
        echo "📅 Creators set availability...\n";
        $this->createAvailabilitySlots($manager, $creators);
        echo "   ✅ Created availability slots\n\n";

        // 5. Creators add payout methods
        echo "💳 Creators add payout methods...\n";
        $this->createPayoutMethods($manager, $creators);
        echo "   ✅ Created payout methods\n\n";

        // 6. Creators create promo codes
        echo "🎫 Creators create promo codes...\n";
        $promoCodes = $this->createPromoCodes($manager, $creators);
        echo "   ✅ Created " . count($promoCodes) . " promo codes\n\n";

        // PHASE 2: Athletes Discover & Engage
        echo "\n📋 PHASE 2: Athletes Discover & Engage\n";
        echo "─────────────────────────────────────\n";

        // 7. Athletes browse and like creators
        echo "❤️ Athletes discover and like creators...\n";
        $this->createLikes($manager, $allUsers, $creators);
        echo "   ✅ Created likes\n\n";

        // 8. Athletes bookmark favorite creators
        echo "🔖 Athletes bookmark creators...\n";
        $this->createBookmarks($manager, $athletes, $creators);
        echo "   ✅ Created bookmarks\n\n";

        // 9. Athletes start conversations
        echo "💬 Athletes reach out to creators...\n";
        $preBookingConversations = $this->createPreBookingConversations($manager, $athletes, $creators);
        echo "   ✅ Created " . count($preBookingConversations) . " conversations\n\n";

        // PHASE 3: Bookings & Payments
        echo "\n📋 PHASE 3: Bookings & Payments\n";
        echo "─────────────────────────────────────\n";

        // 10. Platform creates gift cards for promotions
        echo "🎁 Platform creates gift cards...\n";
        $giftCards = $this->createGiftCards($manager, $athletes);
        echo "   ✅ Created " . count($giftCards) . " gift cards\n\n";

        // 11. Athletes add wallet credits
        echo "💰 Athletes add wallet credits...\n";
        $this->createWalletCredits($manager, $athletes);
        echo "   ✅ Created wallet credits\n\n";

        // 12. Athletes make bookings (PROPERLY LINKED)
        echo "📖 Athletes book services...\n";
        $bookings = $this->createBookings($manager, $athletes, $services);
        echo "   ✅ Created " . count($bookings) . " bookings (all properly linked)\n\n";

        // 13. Athletes pay for bookings (with promo codes & gift cards)
        echo "💵 Athletes pay for bookings...\n";
        $payments = $this->createPaymentsWithDiscounts($manager, $bookings, $promoCodes, $giftCards);
        echo "   ✅ Created " . count($payments) . " payments (some with discounts)\n\n";

        // PHASE 4: Service Delivery
        echo "\n📋 PHASE 4: Service Delivery\n";
        echo "─────────────────────────────────────\n";

        // 14. More conversations about bookings
        echo "💬 Conversations about bookings...\n";
        $bookingConversations = $this->createBookingConversations($manager, $bookings);
        echo "   ✅ Created " . count($bookingConversations) . " booking conversations\n\n";

        // 15. Creators upload deliverables
        echo "📦 Creators upload deliverables...\n";
        $deliverables = $this->createDeliverables($manager, $bookings);
        echo "   ✅ Created " . count($deliverables) . " deliverables\n\n";

        // 16. Athletes download deliverables (triggers payout)
        echo "⬇️ Athletes download deliverables...\n";
        $this->simulateDeliverableDownloads($manager, $bookings);
        echo "   ✅ Simulated downloads and payouts\n\n";

        // 17. Athletes leave reviews for completed bookings
        echo "⭐ Athletes leave reviews...\n";
        $reviews = $this->createReviews($manager, $bookings);
        echo "   ✅ Created " . count($reviews) . " reviews\n\n";

        $manager->flush();

        // 18. Update creator ratings based on reviews
        echo "📊 Calculating creator ratings...\n";
        $this->updateCreatorRatings($manager, $creators);
        echo "   ✅ Updated ratings for all creators\n\n";

        echo "\n════════════════════════════════════════════════════════════\n";
        echo "🎉 Realistic Plugame flow seeding completed!\n\n";
        $this->printDetailedSummary($allUsers, $services, $bookings, $payments, $feedPosts, $promoCodes, $giftCards, $reviews);
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
            $user->setUserPhoto("https://i.pravatar.cc/300?img={$i}");
            $user->setIsPlugPlus($this->randomBool(20));
            $user->setIsVerified($this->randomBool(60));
            $user->setPhoneNumber('+33' . rand(600000000, 699999999));
            $user->setLocale('fr');
            $user->setTimezone('Europe/Paris');

            $manager->persist($user);

            $profile = new AthleteProfile($user);
            $profile->setDisplayName($user->getFullName() ?: $user->getUsername());
            $profile->setSport($user->getSport());
            $profile->setLevel($this->randomElement(['Beginner','Intermediate','Advanced','Pro']));
            $profile->setBio($user->getBio());
            $profile->setHomeCity($user->getLocation());
            $profile->setCoverPhoto("https://picsum.photos/1200/300?random=athlete_cover_{$i}");
            $profile->setAchievements([
                'Best performance ' . rand(2018, 2024),
                'Local champion ' . rand(2015, 2023)
            ]);
            $profile->setStats([
                'sessions' => rand(10, 500),
                'wins' => rand(0, 100)
            ]);

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
            $user->setUserPhoto("https://i.pravatar.cc/300?img=" . ($count + $i));
            $user->setIsVerified($this->randomBool(70));
            $user->setPhoneNumber('+33' . rand(600000000, 699999999));
            $user->setLocale('fr');
            $user->setTimezone('Europe/Paris');

            $manager->persist($user);

            $profile = new CreatorProfile($user);
            $profile->setDisplayName($user->getFullName() ?: $user->getUsername());
            $profile->setBio($user->getBio());
            $profile->setBaseCity($user->getLocation());
            $profile->setTravelRadiusKm($this->randomElement([10, 25, 50, 100]));
            $profile->setHourlyRateCents($this->randomElement([3000, 5000, 8000, 12000]));
            $profile->setGear([
                'camera' => 'Sony A7' . rand(2, 4),
                'lens' => '24-70mm'
            ]);
            $profile->setSpecialties([$user->getSport(), 'action', 'editorial']);
            $profile->setResponseTime($this->randomElement([60, 120, 240]));
            $profile->setAcceptanceRate(round(rand(70, 99) / 100, 2));
            $profile->setCompletionRate(round(rand(80, 100) / 100, 2));
            $profile->setVerified($this->randomBool(50));
            $profile->setFeaturedWork([
                "https://picsum.photos/800/600?random=featured_{$i}_1",
                "https://picsum.photos/800/600?random=featured_{$i}_2"
            ]);
            $profile->setAvgRating((string)round(rand(30, 50) / 10, 1));
            $profile->setRatingsCount(rand(0, 400));

            $user->setCreatorProfile($profile);
            $manager->persist($profile);

            $creators[] = $user;
        }

        return $creators;
    }

    // ============================================
    // CREATE SERVICES (PROPERLY LINKED TO CREATORS)
    // ============================================

    private function createServices(ObjectManager $manager, array $creators): array
    {
        $services = [];

        foreach ($creators as $creator) {
            $serviceCount = rand(3, 6);

            for ($i = 0; $i < $serviceCount; $i++) {
                [$title, $description] = $this->randomServiceType();

                $kinds = ['HOURLY', 'PER_ASSET', 'PACKAGE'];
                $kind = $kinds[array_rand($kinds)];

                // PROPERLY LINK SERVICE TO CREATOR
                $service = new ServiceOffering($creator);
                $service->setCreator($creator); // Ensure creator is set
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
            for ($day = 0; $day < 14; $day++) {
                $baseDate = new \DateTimeImmutable("+{$day} days");
                $baseDate = $baseDate->setTime(0, 0, 0);

                if ($this->randomBool(80)) {
                    $startTime = $baseDate->setTime(9, 0);
                    $endTime   = $baseDate->setTime(12, 0);
                    $slot = new AvailabilitySlot($creator, $startTime, $endTime);
                    $manager->persist($slot);
                }

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
    // CREATE PAYOUT METHODS (WITH IBAN/BIC)
    // ============================================

    private function createPayoutMethods(ObjectManager $manager, array $creators): void
    {
        foreach ($creators as $creator) {
            // 85% get a bank account
            if ($this->randomBool(85)) {
                $bankName = array_rand(self::BANKS);
                $bankData = self::BANKS[$bankName];
                
                $bank = new PayoutMethod();
                $bank->setUser($creator);
                $bank->setType(PayoutMethod::TYPE_BANK_ACCOUNT);
                $bank->setBankName($bankName);
                $bank->setAccountLast4($this->generateLast4());
                $bank->setIsDefault(true);
                $bank->setIsVerified($this->randomBool(90));
                
                // Generate realistic IBAN
                $ibanBody = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT) . ' ' .
                           str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT) . ' ' .
                           str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT) . ' ' .
                           str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT) . ' ' .
                           str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT) . ' ' .
                           str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
                
                $bank->setMetadata([
                    'iban' => $bankData['iban_prefix'] . ' ' . $ibanBody,
                    'bic' => $bankData['bic'],
                    'account_holder' => $creator->getFullName(),
                ]);
                
                $manager->persist($bank);
            }

            // 65% also get Stripe Express
            if ($this->randomBool(65)) {
                $stripeAccountId = 'acct_' . bin2hex(random_bytes(6));
                $stripe = new PayoutMethod();
                $stripe->setUser($creator);
                $stripe->setType(PayoutMethod::TYPE_STRIPE_EXPRESS);
                $stripe->setStripeAccountId($stripeAccountId);
                $stripe->setIsDefault(false);
                $stripe->setIsVerified($this->randomBool(95));
                $stripe->setMetadata(['express_dashboard' => true]);
                $manager->persist($stripe);

                if (method_exists($creator, 'setStripeAccountId')) {
                    $creator->setStripeAccountId($stripeAccountId);
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
            if ($this->randomBool(75)) {
                $purchase = new WalletCredit();
                $purchase->setUser($athlete);
                $purchase->setAmountCents(rand(3000, 15000));
                $purchase->setType(WalletCredit::TYPE_PURCHASE);
                $purchase->setDescription('Initial credit purchase');
                $purchase->setExpiresAt(new \DateTime('+1 year'));
                $manager->persist($purchase);

                if ($this->randomBool(35)) {
                    $bonus = new WalletCredit();
                    $bonus->setUser($athlete);
                    $bonus->setAmountCents(rand(500, 3000));
                    $bonus->setType(WalletCredit::TYPE_BONUS);
                    $bonus->setDescription('Welcome bonus');
                    $bonus->setExpiresAt(new \DateTime('+6 months'));
                    $manager->persist($bonus);
                }
            }
        }
    }

    // ============================================
    // CREATE BOOKINGS (PROPERLY LINKED)
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

        $bookingCount = rand(80, 120);

        for ($i = 0; $i < $bookingCount; $i++) {
            $athlete = $this->randomElement($athletes);
            $service = $this->randomElement($services);

            // CRITICAL: Ensure athlete is NOT booking their own service
            $creator = $service->getCreator();
            if (!$creator || $creator === $athlete) {
                continue; // Skip if service has no creator or athlete = creator
            }

            // VERIFY: Service belongs to a valid creator
            if (!in_array($creator, array_merge($athletes, []))) {
                // Creator exists in our dataset
            }

            $booking = new Booking();
            $booking->setAthlete($athlete);
            $booking->setCreator($creator); // Use the service's creator
            $booking->setService($service); // Properly linked service

            $daysOffset = rand(-60, 30);
            $hour = rand(9, 17);

            $start = (new \DateTimeImmutable("{$daysOffset} days"))->setTime($hour, 0, 0);
            $durationMin = $service->getDurationMin() ?? 60;
            $end = $start->add(new \DateInterval("PT{$durationMin}M"));

            $booking->setStartTime($start);
            $booking->setEndTime($end);

            // Calculate pricing based on service type
            $servicePrice = $this->calculateServicePrice($service);
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
    // CALCULATE SERVICE PRICE
    // ============================================

    private function calculateServicePrice(ServiceOffering $service): int
    {
        $kind = strtoupper((string)$service->getKind());

        return match($kind) {
            'PER_ASSET' => $service->getPricePerAssetCents() ?? $this->randomPrice(),
            'PACKAGE' => $service->getPriceTotalCents() ?? $this->randomPrice(),
            'HOURLY' => $service->getPriceCents() ?? $this->randomPrice(),
            default => $this->randomPrice(),
        };
    }

    // ============================================
    // CREATE PAYMENTS
    // ============================================

    private function createPayments(ObjectManager $manager, array $bookings): void
    {
        foreach ($bookings as $booking) {
            if (!in_array($booking->getStatus(), [Booking::STATUS_ACCEPTED, Booking::STATUS_COMPLETED])) {
                continue;
            }

            $service = $booking->getService();
            if (!$service) {
                continue;
            }

            $amountCents = $this->calculateServicePrice($service);

            $payment = new Payment();
            $payment->setUser($booking->getAthlete());
            $payment->setBooking($booking);
            $payment->setAmountCents($amountCents);
            $payment->setCurrency('EUR');

            $useWallet = $this->randomBool(25);
            $payment->setPaymentMethod($useWallet ? 'wallet' : 'card');
            $payment->setPaymentGateway($useWallet ? 'wallet' : 'stripe');

            if ($booking->getStatus() === Booking::STATUS_COMPLETED) {
                $payment->setStatus(Payment::STATUS_COMPLETED);
            } else {
                $payment->setStatus(Payment::STATUS_PENDING);
            }

            $payment->setStripePaymentIntentId('pi_' . bin2hex(random_bytes(12)));
            if ($payment->isCompleted()) {
                $payment->setStripeChargeId('ch_' . bin2hex(random_bytes(12)));
            }

            $platformFee = (int) round($amountCents * 0.15);
            $payment->setMetadata([
                'platform_fee' => $platformFee,
                'service_kind' => $service->getKind(),
                'seed' => true,
            ]);

            $manager->persist($payment);

            if ($useWallet) {
                $walletDebit = new WalletCredit();
                $walletDebit->setUser($booking->getAthlete());
                $walletDebit->setBooking($booking);
                $walletDebit->setPayment($payment);
                $walletDebit->setAmountCents($amountCents);
                $walletDebit->setType(WalletCredit::TYPE_USAGE);
                $walletDebit->setDescription('Wallet payment for booking');
                $manager->persist($walletDebit);
            }

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

        foreach ($bookings as $booking) {
            if ($this->randomBool(60)) {
                $conversation = new Conversation();
                $conversation->setAthlete($booking->getAthlete());
                $conversation->setCreator($booking->getCreator());
                $conversation->setBooking($booking);

                $messageCount = rand(5, 15);
                $this->createMessages($manager, $conversation, $messageCount);

                $manager->persist($conversation);
                $conversations[] = $conversation;
            }
        }

        $randomConvCount = rand(30, 50);
        for ($i = 0; $i < $randomConvCount; $i++) {
            $athlete = $this->randomElement($athletes);
            $creator = $this->randomElement($creators);

            $conversation = new Conversation();
            $conversation->setAthlete($athlete);
            $conversation->setCreator($creator);

            $messageCount = rand(3, 12);
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

            $sender = $users[$i % 2];
            $message->setSender($sender);

            $message->setContent($this->randomElement(self::MESSAGE_TEMPLATES));

            $minutesOffset = $i * rand(10, 180);
            $createdAt = $baseDate->modify("+{$minutesOffset} minutes");

            $reflection = new \ReflectionClass($message);
            $property = $reflection->getProperty('createdAt');
            $property->setAccessible(true);
            $property->setValue($message, $createdAt);

            if ($this->randomBool(70)) {
                $readAt = $createdAt->modify('+' . rand(1, 120) . ' minutes');
                $message->setReadAt($readAt);
            }

            $manager->persist($message);

            $conversation->setLastMessageAt($createdAt);
            $conversation->setLastMessagePreview($message->getContent());
        }

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
            $likeCount = rand(8, 20);
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
            $bookmarkCount = rand(5, 15);
            $bookmarkedCreators = $this->randomElements($creators, $bookmarkCount);

            foreach ($bookmarkedCreators as $creator) {
                $bookmark = new Bookmark($athlete, $creator);
                $bookmark->setCollection($this->randomCollection());
                $manager->persist($bookmark);
            }
        }
    }

    // ============================================
    // CREATE PROMO CODES
    // ============================================

    private function createPromoCodes(ObjectManager $manager, array $creators): array
    {
        $promoCodes = [];
        $usedCodes = [];

        foreach ($creators as $creator) {
            $codeCount = rand(2, 4);

            for ($i = 0; $i < $codeCount; $i++) {
                $promoCode = new PromoCode();

                $attempts = 0;
                do {
                    $codeSuffix = strtoupper(substr($creator->getUsername(), 0, 4)) . rand(10, 999);
                    $codeTypes = ['SUMMER', 'WINTER', 'SPRING', 'AUTUMN', 'PROMO', 'SAVE', 'VIP', 'SPORT', 'ACTION', 'DEAL', 'FIRST', 'NEW'];
                    $codePrefix = $this->randomElement($codeTypes);
                    $code = $codePrefix . $codeSuffix;
                    $attempts++;
                } while (in_array($code, $usedCodes) && $attempts < 10);

                $usedCodes[] = $code;

                $promoCode->setCode($code);
                $promoCode->setCreator($creator);

                if ($this->randomBool(70)) {
                    $promoCode->setDiscountType('percentage');
                    $promoCode->setDiscountValue(rand(10, 35));
                } else {
                    $promoCode->setDiscountType('fixed_amount');
                    $promoCode->setDiscountValue(rand(1000, 5000));
                }

                $promoCode->setDescription('Special discount for ' . $creator->getFullName());
                $promoCode->setMaxUses($this->randomBool(65) ? rand(50, 200) : null);
                $promoCode->setMaxUsesPerUser($this->randomBool(50) ? 1 : null);
                $promoCode->setMinAmount($this->randomBool(45) ? rand(3000, 10000) : null);

                if ($this->randomBool(85)) {
                    $daysToExpire = rand(30, 365);
                    $promoCode->setExpiresAt(new \DateTimeImmutable("+{$daysToExpire} days"));
                }

                $promoCode->setIsActive($this->randomBool(92));

                $manager->persist($promoCode);
                $promoCodes[] = $promoCode;
            }
        }

        return $promoCodes;
    }

    // ============================================
    // CREATE GIFT CARDS
    // ============================================

    private function createGiftCards(ObjectManager $manager, array $athletes): array
    {
        $giftCards = [];
        $cardCount = rand(15, 25);

        for ($i = 0; $i < $cardCount; $i++) {
            $giftCard = new GiftCard();

            $balance = rand(2000, 20000);
            $giftCard->setInitialBalance($balance);
            $giftCard->setCurrency('EUR');

            if ($this->randomBool(35) && count($athletes) > 0) {
                $purchaser = $this->randomElement($athletes);
                $giftCard->setPurchasedBy($purchaser);
            }

            if ($this->randomBool(40)) {
                $redeemer = $this->randomElement($athletes);
                $giftCard->setRedeemedBy($redeemer);
                $giftCard->setRedeemedAt(new \DateTimeImmutable('-' . rand(1, 60) . ' days'));

                $usedPercentage = rand(10, 90) / 100;
                $remainingBalance = (int)($balance * (1 - $usedPercentage));
                $giftCard->setCurrentBalance($remainingBalance);

                if ($remainingBalance <= 0) {
                    $giftCard->setIsActive(false);
                }
            }

            $daysToExpire = rand(365, 730);
            $giftCard->setExpiresAt(new \DateTimeImmutable("+{$daysToExpire} days"));

            if ($this->randomBool(35)) {
                $messages = [
                    'Joyeux anniversaire !',
                    'Bon Noël !',
                    'Profite bien de ce cadeau !',
                    'Pour tes futurs cours de sport !',
                    'Merci pour tout !',
                    'Bonne chance pour ta compétition !',
                    'Continue comme ça !',
                ];
                $giftCard->setMessage($this->randomElement($messages));
            }

            $manager->persist($giftCard);
            $giftCards[] = $giftCard;
        }

        return $giftCards;
    }

    // ============================================
    // CREATE CREATOR FEED CONTENT
    // ============================================

    private function createCreatorFeedContent(ObjectManager $manager, array $creators): array
    {
        $feedPosts = [];

        foreach ($creators as $creator) {
            $postCount = rand(8, 20);

            for ($i = 0; $i < $postCount; $i++) {
                $isVideo = $this->randomBool(40);
                $aspectRatio = $this->weightedRandom([
                    '1:1'  => 30,
                    '4:5'  => 20,
                    '16:9' => 25,
                    '9:16' => 25,
                ]);

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
                    ->setPublicUrl("https://picsum.photos/{$width}/{$height}?random={$i}")
                    ->setBytes(rand($isVideo ? 5000000 : 500000, $isVideo ? 50000000 : 5000000))
                    ->setCaption($this->generateFeedCaption())
                    ->setContentType($isVideo ? 'video/mp4' : 'image/jpeg');

                if ($isVideo) {
                    $media->setDurationSec(rand(10, 180));
                    $media->setThumbnailUrl("https://picsum.photos/{$width}/{$height}?random=thumb{$i}");
                }

                $manager->persist($media);
                $feedPosts[] = $media;
            }
        }

        return $feedPosts;
    }

    // ============================================
    // CREATE PRE-BOOKING CONVERSATIONS
    // ============================================

    private function createPreBookingConversations(ObjectManager $manager, array $athletes, array $creators): array
    {
        $conversations = [];
        $convCount = rand(50, 80);

        for ($i = 0; $i < $convCount; $i++) {
            $athlete = $this->randomElement($athletes);
            $creator = $this->randomElement($creators);

            $conversation = new Conversation();
            $conversation->setAthlete($athlete);
            $conversation->setCreator($creator);

            $messageCount = rand(3, 12);
            $this->createMessages($manager, $conversation, $messageCount);

            $manager->persist($conversation);
            $conversations[] = $conversation;
        }

        return $conversations;
    }

    // ============================================
    // CREATE PAYMENTS WITH DISCOUNTS
    // ============================================

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

            $service = $booking->getService();
            if (!$service) continue;

            $originalAmount = $booking->getTotalCents();
            $finalAmount = $originalAmount;
            $usedPromoCode = null;
            $usedGiftCard = null;
            $discountAmount = 0;
            $giftCardAmount = 0;

            if ($this->randomBool(35) && count($promoCodes) > 0) {
                $eligibleCodes = array_filter($promoCodes, fn($pc) =>
                    $pc->getCreator() === $booking->getCreator() && $pc->isValid()
                );

                if (count($eligibleCodes) > 0) {
                    $promoCode = $this->randomElement(array_values($eligibleCodes));
                    $discountAmount = $promoCode->calculateDiscount($originalAmount);
                    $finalAmount -= $discountAmount;
                    $usedPromoCode = $promoCode;
                    $promoCode->incrementUsedCount();
                }
            }

            if ($this->randomBool(25) && count($giftCards) > 0) {
                $validCards = array_filter($giftCards, fn($gc) => $gc->isValid());

                if (count($validCards) > 0) {
                    $giftCard = $this->randomElement(array_values($validCards));
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
                ->setAmountCents(max(50, $finalAmount))
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

    // ============================================
    // CREATE BOOKING CONVERSATIONS
    // ============================================

    private function createBookingConversations(ObjectManager $manager, array $bookings): array
    {
        $conversations = [];

        foreach ($bookings as $booking) {
            if (!$this->randomBool(65)) continue;

            $conversation = new Conversation();
            $conversation->setAthlete($booking->getAthlete());
            $conversation->setCreator($booking->getCreator());
            $conversation->setBooking($booking);

            $messageCount = rand(5, 18);
            $this->createMessages($manager, $conversation, $messageCount);

            $manager->persist($conversation);
            $conversations[] = $conversation;
        }

        return $conversations;
    }

    // ============================================
    // CREATE DELIVERABLES
    // ============================================

    private function createDeliverables(ObjectManager $manager, array $bookings): array
    {
        $deliverables = [];

        foreach ($bookings as $booking) {
            if (!in_array($booking->getStatus(), [Booking::STATUS_ACCEPTED, Booking::STATUS_COMPLETED])) {
                continue;
            }

            if (!$this->randomBool(75)) continue;

            $service = $booking->getService();
            $deliverableCount = match($service->getKind()) {
                'PER_ASSET' => rand(10, 50),
                'PACKAGE' => rand(30, 100),
                'HOURLY' => rand(20, 60),
                default => rand(10, 30),
            };

            for ($i = 0; $i < $deliverableCount; $i++) {
                $isVideo = $this->randomBool(20);
                $aspectRatio = $this->weightedRandom([
                    '16:9' => 60,
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
                    ->setPublicUrl("https://picsum.photos/{$width}/{$height}?random=del{$i}")
                    ->setBytes(rand($isVideo ? 10000000 : 2000000, $isVideo ? 100000000 : 10000000))
                    ->setContentType($isVideo ? 'video/mp4' : 'image/jpeg');

                if ($isVideo) {
                    $deliverable->setDurationSec(rand(5, 30));
                    $deliverable->setThumbnailUrl("https://picsum.photos/{$width}/{$height}?random=thumb{$i}");
                }

                $manager->persist($deliverable);
                $deliverables[] = $deliverable;
            }

            $booking->isDeliverablesUnlocked(false);
        }

        return $deliverables;
    }

    // ============================================
    // SIMULATE DELIVERABLE DOWNLOADS
    // ============================================

    private function simulateDeliverableDownloads(ObjectManager $manager, array $bookings): void
    {
        foreach ($bookings as $booking) {
            if ($booking->getStatus() !== Booking::STATUS_COMPLETED) continue;
            if ($booking->getDeliverables()->count() === 0) continue;

            if (!$this->randomBool(85)) continue;

            $booking->setDeliverableDownloadedAt(new \DateTimeImmutable('-' . rand(1, 7) . ' days'));
            $booking->setDeliverablesUnlocked(true);
        }
    }

    // ============================================
    // CREATE REVIEWS
    // ============================================

    private function createReviews(ObjectManager $manager, array $bookings): array
    {
        $reviews = [];

        foreach ($bookings as $booking) {
            if ($booking->getStatus() !== Booking::STATUS_COMPLETED) {
                continue;
            }

            if (!$this->randomBool(75)) {
                continue;
            }

            if ($booking->getReview()) {
                continue;
            }

            $review = new Review($booking);
            $review->setReviewer($booking->getAthlete());
            $review->setCreator($booking->getCreator());

            $rating = $this->weightedRandom([
                5 => 50,
                4 => 30,
                3 => 15,
                2 => 4,
                1 => 1,
            ]);

            $review->setRating($rating);

            if ($this->randomBool(65)) {
                $comments = $rating >= 4 ? [
                    'Excellent service! Very professional.',
                    'Great experience, highly recommend!',
                    'Amazing work, exceeded my expectations!',
                    'Very satisfied with the quality.',
                    'Good work, will book again.',
                    'Professional and on time.',
                    'Quality content, thank you!',
                    'Perfect! Exactly what I needed.',
                    'Great communication and results.',
                    'Happy with the final product.',
                    'Incredible talent and professionalism!',
                    'Couldn\'t be happier with the results.',
                    'Will definitely work together again.',
                    'Top-notch quality and service.',
                ] : [
                    'Good but could be better.',
                    'Average experience.',
                    'Not quite what I expected.',
                    'Decent work but had some issues.',
                    'OK but room for improvement.',
                    'Service was fine, nothing special.',
                ];

                $review->setComment($this->randomElement($comments));
            }

            $manager->persist($review);
            $reviews[] = $review;
        }

        return $reviews;
    }

    // ============================================
    // UPDATE CREATOR RATINGS
    // ============================================

    private function updateCreatorRatings(ObjectManager $manager, array $creators): void
    {
        foreach ($creators as $creator) {
            $creatorProfile = $creator->getCreatorProfile();
            if (!$creatorProfile) {
                continue;
            }

            $reviews = $manager->getRepository(Review::class)->findBy(['creator' => $creator]);

            if (empty($reviews)) {
                $creatorProfile->setAvgRating(null);
                $creatorProfile->setRatingsCount(0);
                continue;
            }

            $totalRating = 0;
            foreach ($reviews as $review) {
                $totalRating += $review->getRating();
            }

            $avgRating = $totalRating / count($reviews);
            $avgRatingFormatted = number_format($avgRating, 1);

            $creatorProfile->setAvgRating($avgRatingFormatted);
            $creatorProfile->setRatingsCount(count($reviews));
        }

        $manager->flush();
    }

    // ============================================
    // OAUTH USERS
    // ============================================

    private function createOAuthUsers(ObjectManager $manager): array
    {
        $oauthUsers = [];

        $googleUser = new User();
        $googleUser->setUsername('google_athlete');
        $googleUser->setEmail('googletest@example.com');
        $googleUser->setPassword($this->passwordHasher->hashPassword($googleUser, self::PASSWORD));
        $googleUser->setRoles([User::ROLE_ATHLETE]);
        $googleUser->setFullName('Google Test User');
        $googleUser->setUserPhoto('https://i.pravatar.cc/300?img=50');
        $googleUser->setPhoneNumber('+33612345678');
        $googleUser->setLocation('Paris, France');
        $googleUser->setSport('football');
        $googleUser->setIsVerified(true);
        $googleUser->setIsActive(true);

        $googleOAuth = new OAuthProvider($googleUser, 'google', '123456789012345678901');
        $googleOAuth->setProviderEmail('googletest@example.com');
        $googleOAuth->setProviderName('Google Test User');
        $googleOAuth->setProviderPhotoUrl('https://i.pravatar.cc/300?img=50');
        $googleOAuth->setProviderData([
            'sub' => '123456789012345678901',
            'email' => 'googletest@example.com',
            'email_verified' => true,
            'name' => 'Google Test User',
            'picture' => 'https://i.pravatar.cc/300?img=50',
        ]);

        $googleUser->addOauthProvider($googleOAuth);
        $manager->persist($googleUser);
        $manager->persist($googleOAuth);
        $oauthUsers[] = $googleUser;

        $athleteProfile = new AthleteProfile($googleUser);
        $athleteProfile->setDisplayName($googleUser->getUsername());
        $athleteProfile->setSport('football');
        $athleteProfile->setLevel('intermediate');
        $athleteProfile->setBio('Passionate athlete who loves football and basketball');
        $manager->persist($athleteProfile);

        $appleUser = new User();
        $appleUser->setUsername('apple_creator');
        $appleUser->setEmail('appletest@example.com');
        $appleUser->setPassword($this->passwordHasher->hashPassword($appleUser, self::PASSWORD));
        $appleUser->setRoles([User::ROLE_CREATOR]);
        $appleUser->setFullName('Apple Test Creator');
        $appleUser->setUserPhoto('https://i.pravatar.cc/300?img=51');
        $appleUser->setPhoneNumber('+33623456789');
        $appleUser->setLocation('Lyon, France');
        $appleUser->setSport('photography');
        $appleUser->setIsVerified(true);
        $appleUser->setIsActive(true);

        $appleOAuth = new OAuthProvider($appleUser, 'apple', '000123.abc123def456.7890');
        $appleOAuth->setProviderEmail('appletest@example.com');
        $appleOAuth->setProviderName('Apple Test Creator');
        $appleOAuth->setProviderData([
            'sub' => '000123.abc123def456.7890',
            'email' => 'appletest@example.com',
            'email_verified' => true,
        ]);

        $appleUser->addOauthProvider($appleOAuth);
        $manager->persist($appleUser);
        $manager->persist($appleOAuth);
        $oauthUsers[] = $appleUser;

        $creatorProfile = new CreatorProfile($appleUser);
        $creatorProfile->setDisplayName('Apple Test Creator');
        $creatorProfile->setBaseCity('Lyon');
        $creatorProfile->setBio('Professional photographer specializing in sports content. Testing OAuth integration.');
        $creatorProfile->setSpecialties(['photography', 'action', 'sports']);
        $creatorProfile->setGear(['Canon EOS R5', 'Sony A7IV', 'DJI Mavic 3']);
        $creatorProfile->setTravelRadiusKm(50);
        $manager->persist($creatorProfile);

        $mixedUser = new User();
        $mixedUser->setUsername('multi_oauth');
        $mixedUser->setEmail('multioauth@example.com');
        $mixedUser->setPassword($this->passwordHasher->hashPassword($mixedUser, self::PASSWORD));
        $mixedUser->setRoles([User::ROLE_ATHLETE]);
        $mixedUser->setFullName('Multi OAuth User');
        $mixedUser->setUserPhoto('https://i.pravatar.cc/300?img=52');
        $mixedUser->setPhoneNumber('+33634567890');
        $mixedUser->setLocation('Marseille, France');
        $mixedUser->setSport('tennis');
        $mixedUser->setIsVerified(true);
        $mixedUser->setIsActive(true);

        $mixedGoogleOAuth = new OAuthProvider($mixedUser, 'google', '999888777666555444333');
        $mixedGoogleOAuth->setProviderEmail('multioauth@example.com');
        $mixedGoogleOAuth->setProviderName('Multi OAuth User');
        $mixedGoogleOAuth->setProviderPhotoUrl('https://i.pravatar.cc/300?img=52');
        $mixedGoogleOAuth->setProviderData([
            'sub' => '999888777666555444333',
            'email' => 'multioauth@example.com',
            'email_verified' => true,
            'name' => 'Multi OAuth User',
        ]);

        $mixedAppleOAuth = new OAuthProvider($mixedUser, 'apple', '000999.xyz789abc456.1234');
        $mixedAppleOAuth->setProviderEmail('multioauth@example.com');
        $mixedAppleOAuth->setProviderName('Multi OAuth User');
        $mixedAppleOAuth->setProviderData([
            'sub' => '000999.xyz789abc456.1234',
            'email' => 'multioauth@example.com',
            'email_verified' => true,
        ]);

        $mixedUser->addOauthProvider($mixedGoogleOAuth);
        $mixedUser->addOauthProvider($mixedAppleOAuth);
        $manager->persist($mixedUser);
        $manager->persist($mixedGoogleOAuth);
        $manager->persist($mixedAppleOAuth);
        $oauthUsers[] = $mixedUser;

        $athleteProfile2 = new AthleteProfile($mixedUser);
        $athleteProfile2->setDisplayName($mixedUser->getUsername());
        $athleteProfile2->setSport('tennis');
        $athleteProfile2->setLevel('advanced');
        $athleteProfile2->setBio('Advanced tennis and volleyball player');
        $manager->persist($athleteProfile2);

        return $oauthUsers;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

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

    private function generateFeedCaption(): string
    {
        $captions = [
            'Training session today 💪',
            'Great game! #sports #action',
            'Behind the scenes 📸',
            'New content coming soon!',
            'Check out this amazing moment!',
            'Hard work pays off 🔥',
            'Game day vibes ⚽',
            'Action shot! 🏀',
            'Training hard for the next match',
            'Best moment of the game!',
            'Living my passion every day 🎯',
            'Another day, another challenge 💯',
            'Pushing my limits 🚀',
            'This is what I love doing ❤️',
            'Captured this incredible moment',
            'Sports photography at its finest',
            'Working on something special',
            'Can\'t wait to share more!',
            'Thank you for all the support 🙏',
            'Epic session yesterday!',
        ];

        return $this->randomElement($captions);
    }

    private function generateName(): string
    {
        return $this->randomElement(self::FIRST_NAMES) . ' ' . $this->randomElement(self::LAST_NAMES);
    }

    private function generateBio(string $type): string
    {
        $bios = [
            'athlete' => [
                'Passionate about sports and fitness. Always looking to improve!',
                'Dedicated athlete striving for excellence.',
                'Sports enthusiast. Let\'s train together!',
                'Fitness lover. Health is wealth!',
                'Competing at the highest level. Never give up!',
                'Training hard, playing harder.',
                'Living the athletic lifestyle 24/7.',
                'Chasing dreams and breaking records.',
                'Sports is not just a hobby, it\'s a lifestyle.',
                'Pushing boundaries every single day.',
            ],
            'creator' => [
                'Professional sports photographer with 10+ years experience.',
                'Creating amazing sports content. Book your session today!',
                'Capturing your best moments on the field.',
                'Expert videographer specializing in sports events.',
                'Telling stories through sports photography.',
                'Professional content creator for athletes.',
                'Bringing your sports moments to life.',
                'Award-winning sports photographer.',
                'Specialized in action and sports photography.',
                'Making athletes look their absolute best.',
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
        $prices = [3000, 5000, 7500, 10000, 12500, 15000, 20000, 25000, 30000, 40000, 50000, 75000, 100000];
        return $this->randomElement($prices);
    }

    private function randomDuration(): int
    {
        $durations = [30, 45, 60, 90, 120, 150, 180, 240, 300];
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
            '15 social media ready photos',
            '10 edited photos + 5 short video clips',
            'Full HD video with color grading',
            '30 photos with basic retouching',
            'Professional photo album with 40 images',
            '4K video footage with cinematic editing',
            'All raw files + 25 edited photos',
            'Highlight reel under 3 minutes',
            '60 photos delivered within 72 hours',
            'Drone footage + ground photography',
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
            'Indoor venue, please prepare accordingly.',
            'Need both photo and video coverage.',
            'This is for a competition, need high quality.',
            'Please arrive 15 minutes early.',
            'Weather dependent, may need to reschedule.',
            'Group session with 5 people.',
            'Need fast turnaround for this project.',
            'Specific poses and angles required.',
            'Client wants natural, candid shots.',
            'Professional headshots also needed.',
        ];

        return $this->randomBool(65) ? $this->randomElement($notes) : '';
    }

    private function generateLast4(): string
    {
        return str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function randomCollection(): string
    {
        $collections = [
            'Favorites', 
            'To Book', 
            'Top Photographers', 
            'My List',
            'Bookmarked',
            'Interested',
            'For Later',
            'Recommended',
            'Best Creators',
            'Must Try',
        ];
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

    private function weightedRandom(array $weights): mixed
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

    private function printDetailedSummary(
        array $users,
        array $services,
        array $bookings,
        array $payments,
        array $feedPosts,
        array $promoCodes,
        array $giftCards,
        array $reviews = []
    ): void {
        echo "📊 DETAILED SUMMARY:\n";
        echo "════════════════════════════════════════════════════════════\n";
        echo "   👥 Users: " . count($users) . "\n";
        echo "   📸 Feed Posts: " . count($feedPosts) . "\n";
        echo "   💼 Services: " . count($services) . "\n";
        echo "   📖 Bookings: " . count($bookings) . "\n";
        
        // Booking status breakdown
        $statusCounts = [
            Booking::STATUS_PENDING => 0,
            Booking::STATUS_ACCEPTED => 0,
            Booking::STATUS_COMPLETED => 0,
            Booking::STATUS_CANCELLED => 0,
        ];
        foreach ($bookings as $booking) {
            $status = $booking->getStatus();
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }
        
        echo "      - Pending: {$statusCounts[Booking::STATUS_PENDING]}\n";
        echo "      - Accepted: {$statusCounts[Booking::STATUS_ACCEPTED]}\n";
        echo "      - Completed: {$statusCounts[Booking::STATUS_COMPLETED]}\n";
        echo "      - Cancelled: {$statusCounts[Booking::STATUS_CANCELLED]}\n";
        
        echo "   💵 Payments: " . count($payments) . "\n";

        $paymentsWithPromo = count(array_filter($payments, fn($p) => $p->hasPromoCode()));
        $paymentsWithGift = count(array_filter($payments, fn($p) => $p->hasGiftCard()));

        echo "      - With promo code: {$paymentsWithPromo}\n";
        echo "      - With gift card: {$paymentsWithGift}\n";
        echo "   🎫 Promo Codes: " . count($promoCodes) . "\n";
        echo "   🎁 Gift Cards: " . count($giftCards) . "\n";

        $activeGiftCards = count(array_filter($giftCards, fn($gc) => $gc->isValid()));
        echo "      - Active: {$activeGiftCards}\n";
        echo "   ⭐ Reviews: " . count($reviews) . "\n";

        if (count($reviews) > 0) {
            $totalRating = 0;
            foreach ($reviews as $review) {
                $totalRating += $review->getRating();
            }
            $avgRating = round($totalRating / count($reviews), 2);
            echo "      - Average Rating: {$avgRating}/5\n";
        }

        echo "\n";
        echo "🔐 LOGIN CREDENTIALS:\n";
        echo "════════════════════════════════════════════════════════════\n";
        echo "   Regular Users:\n";
        echo "   - Athlete: athlete1@example.com\n";
        echo "   - Creator: creator1@example.com\n";
        echo "   - Password: " . self::PASSWORD . "\n\n";
        echo "   OAuth Test Users:\n";
        echo "   - Google: googletest@example.com (Athlete)\n";
        echo "   - Apple: appletest@example.com (Creator)\n";
        echo "   - Multi-OAuth: multioauth@example.com (Google + Apple)\n";
        echo "   - Password: " . self::PASSWORD . "\n";
        echo "════════════════════════════════════════════════════════════\n";
    }
}