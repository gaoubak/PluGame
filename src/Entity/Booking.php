<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use App\Entity\Traits\SoftDeletable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    use UuidId;
    use Timestamps;
    use SoftDeletable;

    public const STATUS_PENDING     = 'PENDING';
    public const STATUS_ACCEPTED    = 'ACCEPTED';
    public const STATUS_DECLINED    = 'DECLINED';
    public const STATUS_CANCELLED   = 'CANCELLED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED   = 'COMPLETED';
    public const STATUS_REFUNDED    = 'REFUNDED';

    #[ORM\ManyToOne(inversedBy: 'bookingsAsAthlete', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'athlete_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private ?User $athlete = null;

    #[ORM\ManyToOne(inversedBy: 'bookingsAsCreator', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'creator_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private ?User $creator = null;

    #[ORM\ManyToOne(targetEntity: ServiceOffering::class)]
    #[ORM\JoinColumn(name: 'service_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['booking:read','user:read'])]
    private ?ServiceOffering $service = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['booking:read','user:read'])]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['booking:read','user:read'])]
    private \DateTimeImmutable $endTime;

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['booking:read','user:read'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    // Notes from athlete
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    // Location text
    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['booking:read', 'booking:write','user:read'])]
    private ?string $locationText = null;

    // Short canonical location
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['booking:read','booking:write','user:read'])]
    private ?string $location = null;

    // Creator notes
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['booking:read','booking:write','user:read'])]
    private ?string $creatorNotes = null;

    // Cancellation info
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'cancelled_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['booking:read'])]
    private ?User $cancelledBy = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['booking:read'])]
    private ?string $cancelReason = null;

    // Completion timestamp
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $completedAt = null;

    // Geo coords
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $lat = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $lng = null;

    // Pricing
    #[ORM\Column(type: 'integer')]
    #[Groups(['booking:read', 'booking:write','user:read'])]
    private int $subtotalCents = 0;

    #[ORM\Column(type: 'integer')]
    private int $feeCents = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $taxCents = 0;

    #[ORM\Column(type: 'integer')]
    private int $totalCents = 0;

    // ✅ NEW: Deposit system fields
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['booking:read'])]
    private ?int $depositAmountCents = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['booking:read'])]
    private ?int $remainingAmountCents = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $depositPaidAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $remainingPaidAt = null;

    // ✅ NEW: Payout tracking
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $payoutCompletedAt = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $stripeTransferId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['booking:read'])]
    private ?int $creatorAmountCents = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['booking:read'])]
    private ?int $platformFeeCents = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: BookingSegment::class, cascade: ['persist','remove'], orphanRemoval: true)]
    #[Groups(['booking:read'])]
    private Collection $segments;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Review::class, cascade: ['persist','remove'])]
    #[Groups(['booking:read'])]
    private ?Review $review = null;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Payment::class, cascade: ['persist'])]
    #[Groups(['booking:read'])]
    private ?Payment $payment = null;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Conversation::class, cascade: ['persist'])]
    private ?Conversation $conversation = null;

    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: MediaAsset::class)]
    #[Groups(['booking:read'])]
    private Collection $deliverables;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    public function __construct()
    {
        $this->segments = new ArrayCollection();
        $this->deliverables = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->startTime = $now;
        $this->endTime   = $now;
    }

    // Basic getters/setters
    public function getAthlete(): ?User
    {
        return $this->athlete;
    }
    public function setAthlete(?User $u): self
    {
        $this->athlete = $u;
        return $this;
    }
    public function getCreator(): ?User
    {
        return $this->creator;
    }
    public function setCreator(?User $u): self
    {
        $this->creator = $u;
        return $this;
    }
    public function getService(): ?ServiceOffering
    {
        return $this->service;
    }
    public function setService(?ServiceOffering $s): self
    {
        $this->service = $s;
        return $this;
    }
    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }
    public function setStartTime(\DateTimeImmutable $d): self
    {
        $this->startTime = $d;
        return $this;
    }
    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }
    public function setEndTime(\DateTimeImmutable $d): self
    {
        $this->endTime = $d;
        return $this;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $s): self
    {
        $this->status = $s;
        return $this;
    }
    public function getCurrency(): string
    {
        return $this->currency;
    }
    public function setCurrency(string $c): self
    {
        $this->currency = $c;
        return $this;
    }

    // Notes/location
    public function getNotes(): ?string
    {
        return $this->notes;
    }
    public function setNotes(?string $n): self
    {
        $this->notes = $n;
        return $this;
    }
    public function getLocationText(): ?string
    {
        return $this->locationText;
    }
    public function setLocationText(?string $t): self
    {
        $this->locationText = $t;
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
    public function getCreatorNotes(): ?string
    {
        return $this->creatorNotes;
    }
    public function setCreatorNotes(?string $notes): self
    {
        $this->creatorNotes = $notes;
        return $this;
    }

    // Cancellation
    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }
    public function setCancelledBy(?User $u): self
    {
        $this->cancelledBy = $u;
        return $this;
    }
    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }
    public function setCancelReason(?string $reason): self
    {
        $this->cancelReason = $reason;
        return $this;
    }

    // Completion
    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }
    public function setCompletedAt(?\DateTimeImmutable $d): self
    {
        $this->completedAt = $d;
        return $this;
    }

    // Geo
    public function getLat(): ?float
    {
        return $this->lat;
    }
    public function setLat(?float $v): self
    {
        $this->lat = $v;
        return $this;
    }
    public function getLng(): ?float
    {
        return $this->lng;
    }
    public function setLng(?float $v): self
    {
        $this->lng = $v;
        return $this;
    }

    // Pricing
    public function getSubtotalCents(): int
    {
        return $this->subtotalCents;
    }
    public function setSubtotalCents(int $c): self
    {
        $this->subtotalCents = max(0, $c);
        return $this;
    }
    public function getFeeCents(): int
    {
        return $this->feeCents;
    }
    public function setFeeCents(int $c): self
    {
        $this->feeCents = max(0, $c);
        return $this;
    }
    public function getTaxCents(): int
    {
        return $this->taxCents;
    }
    public function setTaxCents(int $c): self
    {
        $this->taxCents = $c;
        return $this;
    }
    public function getTotalCents(): int
    {
        return $this->totalCents;
    }
    public function setTotalCents(int $c): self
    {
        $this->totalCents = max(0, $c);
        return $this;
    }

    // ✅ NEW: Deposit system
    public function getDepositAmountCents(): ?int
    {
        return $this->depositAmountCents;
    }
    public function setDepositAmountCents(?int $c): self
    {
        $this->depositAmountCents = $c;
        return $this;
    }
    public function getRemainingAmountCents(): ?int
    {
        return $this->remainingAmountCents;
    }
    public function setRemainingAmountCents(?int $c): self
    {
        $this->remainingAmountCents = $c;
        return $this;
    }
    public function getDepositPaidAt(): ?\DateTimeImmutable
    {
        return $this->depositPaidAt;
    }
    public function setDepositPaidAt(?\DateTimeImmutable $d): self
    {
        $this->depositPaidAt = $d;
        return $this;
    }
    public function getRemainingPaidAt(): ?\DateTimeImmutable
    {
        return $this->remainingPaidAt;
    }
    public function setRemainingPaidAt(?\DateTimeImmutable $d): self
    {
        $this->remainingPaidAt = $d;
        return $this;
    }

    // ✅ NEW: Payout tracking
    public function getPayoutCompletedAt(): ?\DateTimeImmutable
    {
        return $this->payoutCompletedAt;
    }
    public function setPayoutCompletedAt(?\DateTimeImmutable $d): self
    {
        $this->payoutCompletedAt = $d;
        return $this;
    }
    public function getStripeTransferId(): ?string
    {
        return $this->stripeTransferId;
    }
    public function setStripeTransferId(?string $id): self
    {
        $this->stripeTransferId = $id;
        return $this;
    }
    public function getCreatorAmountCents(): ?int
    {
        return $this->creatorAmountCents;
    }
    public function setCreatorAmountCents(?int $c): self
    {
        $this->creatorAmountCents = $c;
        return $this;
    }
    public function getPlatformFeeCents(): ?int
    {
        return $this->platformFeeCents;
    }
    public function setPlatformFeeCents(?int $c): self
    {
        $this->platformFeeCents = $c;
        return $this;
    }

    // Segments
    public function getSegments(): Collection
    {
        return $this->segments;
    }
    public function addSegment(BookingSegment $segment): self
    {
        if (!$this->segments->contains($segment)) {
            $this->segments->add($segment);
            $segment->setBooking($this);
            $this->recomputeEnvelope();
        }
        return $this;
    }
    public function removeSegment(BookingSegment $segment): self
    {
        if ($this->segments->removeElement($segment)) {
            if ($segment->getBooking() === $this) {
                $segment->setBooking(null);
            }
            $this->recomputeEnvelope();
        }
        return $this;
    }
    public function getBookedMinutes(): int
    {
        $mins = 0;
        foreach ($this->segments as $s) {
            $mins += (int) round(($s->getEndTime()->getTimestamp() - $s->getStartTime()->getTimestamp()) / 60);
        }
        return max(0, $mins);
    }
    private function recomputeEnvelope(): void
    {
        if ($this->segments->isEmpty()) {
            $now = new \DateTimeImmutable();
            $this->startTime = $now;
            $this->endTime   = $now;
            return;
        }
        $first = $this->segments->first();
        $min = $first->getStartTime();
        $max = $first->getEndTime();
        foreach ($this->segments as $s) {
            if ($s->getStartTime() < $min) {
                $min = $s->getStartTime();
            }
            if ($s->getEndTime()   > $max) {
                $max = $s->getEndTime();
            }
        }
        $this->startTime = $min;
        $this->endTime   = $max;
    }

    // Relations
    public function getReview(): ?Review
    {
        return $this->review;
    }
    public function setReview(?Review $r): self
    {
        if ($r === null && $this->review) {
            $this->review->setBooking(null);
        }
        if ($r && $r->getBooking() !== $this) {
            $r->setBooking($this);
        }
        $this->review = $r;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }
    public function setPayment(?Payment $p): self
    {
        $this->payment = $p;
        return $this;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }
    public function setConversation(?Conversation $c): self
    {
        $this->conversation = $c;
        return $this;
    }

    public function getDeliverables(): Collection
    {
        return $this->deliverables;
    }
    public function addDeliverable(MediaAsset $media): self
    {
        if (!$this->deliverables->contains($media)) {
            $this->deliverables->add($media);
            $media->setBooking($this);
        }
        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }
    public function setStripePaymentIntentId(?string $id): self
    {
        $this->stripePaymentIntentId = $id;
        return $this;
    }
    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }
    public function setStripeSubscriptionId(?string $id): self
    {
        $this->stripeSubscriptionId = $id;
        return $this;
    }

    // Helpers
    public function isPaid(): bool
    {
        return $this->payment !== null && $this->payment->isCompleted();
    }

    public function getTotalPriceInCents(): int
    {
        return $this->totalCents;
    }

    public function getTotalAmountCents(): int
    {
        return $this->totalCents;
    }

    #[Groups(['booking:read','user:read'])]
    public function getFormattedPrice(): string
    {
        return number_format($this->subtotalCents / 100, 2) . ' ' . $this->currency;
    }

    #[Groups(['booking:read','user:read'])]
    public function getFormattedTotalPrice(): string
    {
        return number_format($this->totalCents / 100, 2) . ' ' . $this->currency;
    }

    #[Groups(['booking:read','user:read'])]
    public function getPriceLabel(): string
    {
        return $this->getFormattedTotalPrice() . ' total';
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_REFUNDED], true);
    }

    #[Groups(['booking:read','user:read'])]
    public function getCancellationPillText(): string
    {
        return 'Cancellation available';
    }

    // ✅ NEW: Deliverable download tracking
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $deliverableDownloadRequestedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $deliverableDownloadedAt = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $deliverableTrackingToken = null;

    public function getDeliverableDownloadRequestedAt(): ?\DateTimeImmutable
    {
        return $this->deliverableDownloadRequestedAt;
    }

    public function setDeliverableDownloadRequestedAt(?\DateTimeImmutable $deliverableDownloadRequestedAt): self
    {
        $this->deliverableDownloadRequestedAt = $deliverableDownloadRequestedAt;
        return $this;
    }

    public function getDeliverableDownloadedAt(): ?\DateTimeImmutable
    {
        return $this->deliverableDownloadedAt;
    }

    public function setDeliverableDownloadedAt(?\DateTimeImmutable $deliverableDownloadedAt): self
    {
        $this->deliverableDownloadedAt = $deliverableDownloadedAt;
        return $this;
    }

    public function getDeliverableTrackingToken(): ?string
    {
        return $this->deliverableTrackingToken;
    }

    public function setDeliverableTrackingToken(?string $deliverableTrackingToken): self
    {
        $this->deliverableTrackingToken = $deliverableTrackingToken;
        return $this;
    }

    #[Groups(['booking:read'])]
    public function isDeliverablesUnlocked(): bool
    {
        return $this->remainingPaidAt !== null;
    }
}
