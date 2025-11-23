<?php

// src/Entity/User.php - MERGED VERSION (old + new fields)

namespace App\Entity;

use App\Entity\Follower;
use App\Entity\AvailabilitySlot;
use App\Entity\Booking;
use App\Entity\Conversation;
use App\Entity\CreatorProfile;
use App\Entity\AthleteProfile;
use App\Entity\Review;
use App\Entity\ServiceOffering;
use App\Entity\Payment;
use App\Entity\MediaAsset;
use App\Entity\WalletCredit;
use App\Entity\PayoutMethod;
use App\Entity\Bookmark;
use App\Entity\Like;
use App\Entity\Comment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'This email is already in use.')]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken.')]
#[Vich\Uploadable]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_USER    = 'ROLE_USER';
    public const ROLE_ATHLETE = 'ROLE_ATHLETE';
    public const ROLE_CREATOR = 'ROLE_CREATOR';
    public const ROLE_ADMIN   = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['user:read','conversation:read','booking:read','creator:feed', 'conversation:write','get_message', 'message:read','service:read','comment:read','review:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 150, unique: true)]
    #[Groups(['user:read','conversation:read','booking:read','creator:feed', 'conversation:write','get_message','message:read','comment:read','review:read'])]
    private ?string $username = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(['user:read', ])]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    // Vich: not persisted file handle
    #[Vich\UploadableField(mapping: 'users', fileNameProperty: 'userPhoto')]
    private ?File $imageFile = null;

    // existing fields
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read','creator:feed','conversation:write', 'conversation:read','get_message', 'message:read','comment:read','booking:read','review:read'])]
    private ?string $userPhoto = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'offline'])]
    #[Groups(['user:read'])]
    private string $onlineStatus = 'offline';

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $lastSeenAt = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read','creator:feed','get_message', 'message:read'])]
    private ?string $coverPhoto = null;

    #[ORM\Column(type: 'string', length: 8, nullable: true, options: ['default' => 'en'])]
    #[Groups(['user:read'])]
    private string $locale = 'en';

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $timezone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['user:read'])]
    private ?string $description = null;

    // ===== ADDED FIELDS FROM "new" =====
    // fullName (nullable) - additional, optional
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read', 'get_message', 'message:read'])]
    private ?string $fullName = null;

    // bio - similar to description but kept as extra field if needed
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['user:read'])]
    private ?string $bio = null;

    // avatarUrl - optional separate URL field (kept in addition to userPhoto)
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $avatarUrl = null;

    // coverUrl - optional separate cover field (kept in addition to coverPhoto)
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $coverUrl = null;

    // sport
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $sport = null;

    // location
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $location = null;

    // isActive flag
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['user:read'])]
    private bool $isActive = true;

    // ===== RELATIONS =====

    // Profiles
    #[ORM\OneToOne(mappedBy: 'user', targetEntity: CreatorProfile::class)]
    #[Groups(['user:read', 'service:read'])]
    private ?CreatorProfile $creatorProfile = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: AthleteProfile::class)]
    #[Groups(['user:read'])]
    private ?AthleteProfile $athleteProfile = null;

    // Followers
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Follower::class, cascade: ['persist', 'remove'])]
    #[Groups(['get_follower','get_association','get_current_user_follower'])]
    private Collection $followers;

    // Services (creator offerings)
    #[ORM\OneToMany(mappedBy: 'creator', targetEntity: ServiceOffering::class, cascade: ['persist', 'remove'])]
    #[Groups(['user:read'])]
    private Collection $services;

    // Availability (creator slots) - keep existing name 'availability'
    #[ORM\OneToMany(mappedBy: 'creator', targetEntity: AvailabilitySlot::class, cascade: ['persist', 'remove'])]
    #[Groups(['user:read'])]
    private Collection $availability;

    // Bookings
    #[ORM\OneToMany(mappedBy: 'athlete', targetEntity: Booking::class)]
    private Collection $bookingsAsAthlete;

    #[ORM\OneToMany(mappedBy: 'creator', targetEntity: Booking::class)]
    private Collection $bookingsAsCreator;

    // Conversations
    #[ORM\OneToMany(mappedBy: 'athlete', targetEntity: Conversation::class)]
    #[Groups(['user:read'])]
    private Collection $conversationsAsAthlete;

    #[ORM\OneToMany(mappedBy: 'creator', targetEntity: Conversation::class)]
    #[Groups(['user:read'])]
    private Collection $conversationsAsCreator;

    // Reviews
    #[ORM\OneToMany(mappedBy: 'reviewer', targetEntity: Review::class)]
    private Collection $reviews;

    #[ORM\OneToMany(mappedBy: 'creator', targetEntity: Review::class)]
    #[Groups(['creator:feed'])]
    private Collection $receivedReviews;

    // Media
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: MediaAsset::class, cascade: ['persist','remove'])]
    #[Groups(['user:read'])]
    private Collection $mediaAssets;

    // Payments
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Payment::class, cascade: ['persist'])]
    #[Groups(['user:read'])]
    private Collection $payments;

    // Wallet credits & payout methods (from new)
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: WalletCredit::class, cascade: ['persist', 'remove'])]
    #[Groups(['user:read'])]
    private Collection $walletCredits;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: PayoutMethod::class, cascade: ['persist', 'remove'])]
    #[Groups(['user:read'])]
    private Collection $payoutMethods;

    // Stripe fields
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['user:read'])]
    private ?string $stripePaymentMethods = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripeAccountId = null;

    // Payout preference: 'stripe_connect' or 'bank_transfer'
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'stripe_connect'])]
    #[Groups(['user:read'])]
    private string $payoutMethod = 'stripe_connect';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPlugPlus = false;

    // Bookmarks & likes (from old + new)
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Bookmark::class, cascade: ['persist', 'remove'])]
    private Collection $bookmarks;

    #[ORM\OneToMany(mappedBy: 'targetUser', targetEntity: Bookmark::class)]
    private Collection $bookmarkedBy;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Like::class, cascade: ['remove'])]
    #[Groups(['user:read'])]
    private Collection $likes;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Comment::class, cascade: ['remove'])]
    #[Groups(['comment:read'])]
    private Collection $comment;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CommentLike::class, cascade: ['remove'])]
    private Collection $commentLikes;

    // OAuth providers
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: OAuthProvider::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $oauthProviders;

    // updatedAt for Vich
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // transient
    private ?string $plainPassword = null;

    public function __construct()
    {
        $this->followers               = new ArrayCollection();
        $this->services                = new ArrayCollection();
        $this->availability            = new ArrayCollection();
        $this->bookingsAsAthlete       = new ArrayCollection();
        $this->bookingsAsCreator       = new ArrayCollection();
        $this->conversationsAsAthlete  = new ArrayCollection();
        $this->conversationsAsCreator  = new ArrayCollection();
        $this->reviews                 = new ArrayCollection();
        $this->receivedReviews         = new ArrayCollection();
        $this->mediaAssets             = new ArrayCollection();
        $this->payments                = new ArrayCollection();
        $this->walletCredits           = new ArrayCollection();
        $this->payoutMethods           = new ArrayCollection();
        $this->bookmarks               = new ArrayCollection();
        $this->bookmarkedBy            = new ArrayCollection();
        $this->likes                   = new ArrayCollection();
        $this->commentLikes            = new ArrayCollection();
        $this->comment                 = new ArrayCollection();
        $this->oauthProviders          = new ArrayCollection();
    }

    // ===== Security interface =====
    public function getUserIdentifier(): string
    {
        return (string) ($this->email ?? '');
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    // Role helpers
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    public function grantRole(string $role): self
    {
        $r = $this->getRoles();
        if (!in_array($role, $r, true)) {
            $r[] = $role;
            $this->setRoles($r);
        }
        return $this;
    }

    public function revokeRole(string $role): self
    {
        $this->setRoles(
            array_values(array_filter($this->getRoles(), fn($r) => $r !== $role && $r !== 'ROLE_USER'))
        );
        return $this;
    }

    public function isCreator(): bool
    {
        return $this->hasRole(self::ROLE_CREATOR) || $this->getCreatorProfile() !== null;
    }

    public function isAthlete(): bool
    {
        return $this->hasRole(self::ROLE_ATHLETE) || $this->getAthleteProfile() !== null;
    }

    // ===== Basic fields =====
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function isPlugPlus(): bool
    {
        return $this->isPlugPlus;
    }
    public function setIsPlugPlus(bool $isPlugPlus): self
    {
        $this->isPlugPlus = $isPlugPlus;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    // existing photo/cover getters
    public function getUserPhoto(): ?string
    {
        return $this->userPhoto;
    }

    public function setUserPhoto(?string $userPhoto): self
    {
        $this->userPhoto = $userPhoto;
        return $this;
    }

    public function getCoverPhoto(): ?string
    {
        return $this->coverPhoto;
    }

    public function setCoverPhoto(?string $coverPhoto): self
    {
        $this->coverPhoto = $coverPhoto;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    // Vich file
    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;
        if ($imageFile !== null) {
            $this->updatedAt = new \DateTime();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $d): self
    {
        $this->updatedAt = $d;
        return $this;
    }

    // ===== New fields getters/setters (added) =====

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): self
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        // Priority: userPhoto first, then fallback to avatarUrl
        return $this->userPhoto ?? $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): self
    {
        $this->avatarUrl = $avatarUrl;
        return $this;
    }

    public function getCoverUrl(): ?string
    {
        // Priority: coverPhoto first, then fallback to coverUrl
        return $this->coverPhoto ?? $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): self
    {
        $this->coverUrl = $coverUrl;
        return $this;
    }

    public function getSport(): ?string
    {
        return $this->sport;
    }

    public function setSport(?string $sport): self
    {
        $this->sport = $sport;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    // ===== New/Existing helper & status methods =====
    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getOnlineStatus(): string
    {
        return $this->onlineStatus;
    }

    public function setOnlineStatus(string $onlineStatus): self
    {
        $this->onlineStatus = $onlineStatus;
        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeInterface
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeInterface $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;
        return $this;
    }

    public function updateLastSeen(): self
    {
        $this->lastSeenAt = new \DateTime();
        return $this;
    }

    public function isOnline(): bool
    {
        if ($this->onlineStatus === 'online') {
            return true;
        }

        if ($this->lastSeenAt) {
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $this->lastSeenAt->getTimestamp();
            return $diff < 300; // 5 minutes
        }

        return false;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    // ===== Profiles =====
    public function getCreatorProfile(): ?CreatorProfile
    {
        return $this->creatorProfile;
    }

    public function setCreatorProfile(?CreatorProfile $p): self
    {
        $this->creatorProfile = $p;
        return $this;
    }

    public function getAthleteProfile(): ?AthleteProfile
    {
        return $this->athleteProfile;
    }

    public function setAthleteProfile(?AthleteProfile $p): self
    {
        $this->athleteProfile = $p;
        return $this;
    }

    // ===== Followers =====
    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    // ===== Services =====
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(ServiceOffering $service): self
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setCreator($this);
        }
        return $this;
    }

    // ===== Availability =====
    public function getAvailability(): Collection
    {
        return $this->availability;
    }

    // ===== Bookings =====
    public function getBookingsAsAthlete(): Collection
    {
        return $this->bookingsAsAthlete;
    }

    public function getBookingsAsCreator(): Collection
    {
        return $this->bookingsAsCreator;
    }

    public function getBookings(): array
    {
        return array_merge(
            $this->bookingsAsAthlete->toArray(),
            $this->bookingsAsCreator->toArray()
        );
    }

    // ===== Conversations =====
    public function getConversationsAsAthlete(): Collection
    {
        return $this->conversationsAsAthlete;
    }

    public function getConversationsAsCreator(): Collection
    {
        return $this->conversationsAsCreator;
    }

    public function getConversations(): array
    {
        return array_merge(
            $this->conversationsAsAthlete->toArray(),
            $this->conversationsAsCreator->toArray()
        );
    }

    // ===== Reviews =====
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function getReceivedReviews(): Collection
    {
        return $this->receivedReviews;
    }

    // ===== MediaAssets =====
    public function getMediaAssets(): Collection
    {
        return $this->mediaAssets;
    }

    // ===== Stripe =====
    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): self
    {
        $this->stripeCustomerId = $stripeCustomerId;
        return $this;
    }

    public function getStripePaymentMethods(): ?string
    {
        return $this->stripePaymentMethods;
    }

    public function setStripePaymentMethods(?string $stripePaymentMethods): self
    {
        $this->stripePaymentMethods = $stripePaymentMethods;
        return $this;
    }

    public function getStripeAccountId(): ?string
    {
        return $this->stripeAccountId;
    }

    public function setStripeAccountId(?string $stripeAccountId): self
    {
        $this->stripeAccountId = $stripeAccountId;
        return $this;
    }

    public function hasStripeAccount(): bool
    {
        return $this->stripeCustomerId !== null;
    }

    public function getPayoutMethod(): string
    {
        return $this->payoutMethod;
    }

    public function setPayoutMethod(string $payoutMethod): self
    {
        if (!in_array($payoutMethod, ['stripe_connect', 'bank_transfer'])) {
            throw new \InvalidArgumentException('Invalid payout method. Must be "stripe_connect" or "bank_transfer".');
        }
        $this->payoutMethod = $payoutMethod;
        return $this;
    }

    public function usesStripeConnect(): bool
    {
        return $this->payoutMethod === 'stripe_connect';
    }

    public function usesBankTransfer(): bool
    {
        return $this->payoutMethod === 'bank_transfer';
    }

    // ===== Payments =====
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    // Wallet & payout getters
    public function getWalletCredits(): Collection
    {
        return $this->walletCredits;
    }

    public function getPayoutMethods(): Collection
    {
        return $this->payoutMethods;
    }

    // Bookmarks & likes
    public function getBookmarks(): Collection
    {
        return $this->bookmarks;
    }

    public function addBookmark(Bookmark $bookmark): self
    {
        if (!$this->bookmarks->contains($bookmark)) {
            $this->bookmarks->add($bookmark);
            $bookmark->setUser($this);
        }
        return $this;
    }

    public function removeBookmark(Bookmark $bookmark): self
    {
        if ($this->bookmarks->removeElement($bookmark)) {
            if ($bookmark->getUser() === $this) {
                $bookmark->setUser(null);
            }
        }
        return $this;
    }

    public function getBookmarkedBy(): Collection
    {
        return $this->bookmarkedBy;
    }

    public function isBookmarkedBy(User $user): bool
    {
        foreach ($this->bookmarkedBy as $bookmark) {
            if ($bookmark->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

    public function getTotalBookmarks(): int
    {
        return $this->bookmarks->count();
    }

    public function getTotalBookmarkedBy(): int
    {
        return $this->bookmarkedBy->count();
    }

    // Helpers
    #[Groups(['user:read', 'booking:read', 'creator:feed'])]
    public function getDisplayName(): string
    {
        // prefer fullName if present, fallback to username
        return trim((string)($this->fullName ?? $this->username ?? ''));
    }

    public function getTotalBookings(): int
    {
        return $this->bookingsAsAthlete->count() + $this->bookingsAsCreator->count();
    }

    public function getTotalReviews(): int
    {
        return $this->reviews->count();
    }

    public function getTotalPayments(): int
    {
        return $this->payments->count();
    }

    /**
     * @return Collection<int, CommentLike>
     */
    public function getCommentLikes(): Collection
    {
        return $this->commentLikes;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comment;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comment->contains($comment)) {
            $this->comment[] = $comment;
            $comment->setPost($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comment->removeElement($comment)) {
            if ($comment->getPost() === $this) {
                $comment->setPost(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CommentLike>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    /**
     * @return Collection<int, OAuthProvider>
     */
    public function getOauthProviders(): Collection
    {
        return $this->oauthProviders;
    }

    public function addOauthProvider(OAuthProvider $oauthProvider): self
    {
        if (!$this->oauthProviders->contains($oauthProvider)) {
            $this->oauthProviders[] = $oauthProvider;
            $oauthProvider->setUser($this);
        }

        return $this;
    }

    public function removeOauthProvider(OAuthProvider $oauthProvider): self
    {
        if ($this->oauthProviders->removeElement($oauthProvider)) {
            // set the owning side to null (unless already changed)
            if ($oauthProvider->getUser() === $this) {
                $oauthProvider->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Check if user has a specific OAuth provider linked
     */
    public function hasOAuthProvider(string $provider): bool
    {
        foreach ($this->oauthProviders as $oauthProvider) {
            if ($oauthProvider->getProvider() === $provider) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get OAuth provider by name (google, apple, etc.)
     */
    public function getOAuthProvider(string $provider): ?OAuthProvider
    {
        foreach ($this->oauthProviders as $oauthProvider) {
            if ($oauthProvider->getProvider() === $provider) {
                return $oauthProvider;
            }
        }
        return null;
    }
}
