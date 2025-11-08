<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use App\Entity\Traits\SoftDeletable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Review
{
    use UuidId;
    use Timestamps;
    use SoftDeletable;

    #[ORM\OneToOne(inversedBy: 'review', targetEntity: Booking::class)]
    #[ORM\JoinColumn(name: 'booking_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private Booking $booking;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['review:read','review:write'])]
    private ?User $reviewer = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'receivedReviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['review:read','review:write'])]
    private ?User $creator = null;

    #[ORM\Column(type: 'smallint')]
    #[Groups(['user:read','review:read','review:write'])]
    private int $rating = 5;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['user:read','review:read','review:write'])]
    private ?string $comment = null;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
        if ($booking->getReview() !== $this) {
            $booking->setReview($this);
        }
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }
    public function setBooking(?Booking $booking): self
    {
        $this->booking = $booking;
        return $this;
    }
    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }
    public function setReviewer(?User $u): self
    {
        $this->reviewer = $u;
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
    public function getRating(): int
    {
        return $this->rating;
    }
    public function setRating(int $r): self
    {
        $this->rating = max(1, min(5, $r));
        return $this;
    }
    public function getComment(): ?string
    {
        return $this->comment;
    }
    public function setComment(?string $c): self
    {
        $this->comment = $c;
        return $this;
    }
}
