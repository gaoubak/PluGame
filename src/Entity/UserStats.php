<?php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'user_stats')]
#[ORM\HasLifecycleCallbacks]
class UserStats
{
    use UuidId;
    use Timestamps;

    /**
     * User (OneToOne relationship)
     */
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    #[Groups(['stats:read'])]
    private ?User $user = null;

    /**
     * Number of followers
     */
    #[ORM\Column(name: 'followers_count', type: 'integer', options: ['default' => 0])]
    #[Groups(['stats:read'])]
    private int $followersCount = 0;

    /**
     * Number of users this user is following
     */
    #[ORM\Column(name: 'following_count', type: 'integer', options: ['default' => 0])]
    #[Groups(['stats:read'])]
    private int $followingCount = 0;

    /**
     * Total number of bookings (all statuses)
     */
    #[ORM\Column(name: 'total_bookings', type: 'integer', options: ['default' => 0])]
    #[Groups(['stats:read'])]
    private int $totalBookings = 0;

    /**
     * Number of completed bookings
     */
    #[ORM\Column(name: 'completed_bookings', type: 'integer', options: ['default' => 0])]
    #[Groups(['stats:read'])]
    private int $completedBookings = 0;

    /**
     * Total earnings in cents (for creators)
     */
    #[ORM\Column(name: 'total_earnings_cents', type: 'bigint', options: ['default' => 0])]
    #[Groups(['stats:read'])]
    private int $totalEarningsCents = 0;

    /**
     * Total number of reviews received
     */
    #[ORM\Column(name: 'total_reviews', type: 'integer', options: ['default' => 0])]
    #[Groups(['stats:read'])]
    private int $totalReviews = 0;

    /**
     * Average rating (0-5)
     */
    #[ORM\Column(name: 'average_rating', type: 'float', options: ['default' => 0.0])]
    #[Groups(['stats:read'])]
    private float $averageRating = 0.0;

    /**
     * Average response time in minutes
     */
    #[ORM\Column(name: 'response_time_minutes', type: 'integer', options: ['default' => 60])]
    #[Groups(['stats:read'])]
    private int $responseTimeMinutes = 60;

    /**
     * Last time stats were updated
     */
    #[ORM\Column(name: 'last_updated', type: 'datetime')]
    #[Groups(['stats:read'])]
    private ?\DateTimeInterface $lastUpdated = null;

    public function __construct()
    {
        $this->lastUpdated = new \DateTime();
    }

    // Getters and Setters

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getFollowersCount(): int
    {
        return $this->followersCount;
    }

    public function setFollowersCount(int $followersCount): self
    {
        $this->followersCount = $followersCount;
        $this->updateLastUpdated();
        return $this;
    }

    public function incrementFollowersCount(): self
    {
        $this->followersCount++;
        $this->updateLastUpdated();
        return $this;
    }

    public function decrementFollowersCount(): self
    {
        if ($this->followersCount > 0) {
            $this->followersCount--;
            $this->updateLastUpdated();
        }
        return $this;
    }

    public function getFollowingCount(): int
    {
        return $this->followingCount;
    }

    public function setFollowingCount(int $followingCount): self
    {
        $this->followingCount = $followingCount;
        $this->updateLastUpdated();
        return $this;
    }

    public function incrementFollowingCount(): self
    {
        $this->followingCount++;
        $this->updateLastUpdated();
        return $this;
    }

    public function decrementFollowingCount(): self
    {
        if ($this->followingCount > 0) {
            $this->followingCount--;
            $this->updateLastUpdated();
        }
        return $this;
    }

    public function getTotalBookings(): int
    {
        return $this->totalBookings;
    }

    public function setTotalBookings(int $totalBookings): self
    {
        $this->totalBookings = $totalBookings;
        $this->updateLastUpdated();
        return $this;
    }

    public function incrementTotalBookings(): self
    {
        $this->totalBookings++;
        $this->updateLastUpdated();
        return $this;
    }

    public function getCompletedBookings(): int
    {
        return $this->completedBookings;
    }

    public function setCompletedBookings(int $completedBookings): self
    {
        $this->completedBookings = $completedBookings;
        $this->updateLastUpdated();
        return $this;
    }

    public function incrementCompletedBookings(): self
    {
        $this->completedBookings++;
        $this->updateLastUpdated();
        return $this;
    }

    public function getTotalEarningsCents(): int
    {
        return $this->totalEarningsCents;
    }

    public function setTotalEarningsCents(int $totalEarningsCents): self
    {
        $this->totalEarningsCents = $totalEarningsCents;
        $this->updateLastUpdated();
        return $this;
    }

    public function addEarnings(int $cents): self
    {
        $this->totalEarningsCents += $cents;
        $this->updateLastUpdated();
        return $this;
    }

    public function getTotalReviews(): int
    {
        return $this->totalReviews;
    }

    public function setTotalReviews(int $totalReviews): self
    {
        $this->totalReviews = $totalReviews;
        $this->updateLastUpdated();
        return $this;
    }

    public function getAverageRating(): float
    {
        return $this->averageRating;
    }

    public function setAverageRating(float $averageRating): self
    {
        $this->averageRating = $averageRating;
        $this->updateLastUpdated();
        return $this;
    }

    public function getResponseTimeMinutes(): int
    {
        return $this->responseTimeMinutes;
    }

    public function setResponseTimeMinutes(int $responseTimeMinutes): self
    {
        $this->responseTimeMinutes = $responseTimeMinutes;
        $this->updateLastUpdated();
        return $this;
    }

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeInterface $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    private function updateLastUpdated(): void
    {
        $this->lastUpdated = new \DateTime();
    }

    /**
     * Get completion rate percentage
     */
    public function getCompletionRate(): float
    {
        if ($this->totalBookings === 0) {
            return 100.0;
        }
        return ($this->completedBookings / $this->totalBookings) * 100;
    }

    /**
     * Get total earnings in EUR
     */
    public function getTotalEarningsEur(): float
    {
        return $this->totalEarningsCents / 100;
    }
}
