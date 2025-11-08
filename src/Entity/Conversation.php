<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Conversation
{
    use UuidId;
    use Timestamps;

    #[ORM\OneToOne(inversedBy: 'conversation', targetEntity: Booking::class)]
    #[ORM\JoinColumn(name: 'booking_id', referencedColumnName: 'id', nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'conversationsAsAthlete')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['conversation:read','conversation:write'])]
    private ?User $athlete = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'conversationsAsCreator')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['conversation:read','conversation:write'])]
    private ?User $creator = null;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class, cascade: ['persist','remove'], orphanRemoval: true)]
    private Collection $messages;

    // New fields
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['conversation:read'])]
    private ?\DateTimeImmutable $lastMessageAt = null;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    #[Groups(['conversation:read'])]
    private ?string $lastMessagePreview = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['conversation:read'])]
    private int $unreadCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['conversation:read'])]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['conversation:read'])]
    private ?\DateTimeImmutable $mutedUntil = null;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }
    public function setBooking(?Booking $b): self
    {
        $this->booking = $b;
        return $this;
    }

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

    /** @return Collection<int, Message> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $m): self
    {
        if (!$this->messages->contains($m)) {
            $this->messages->add($m);
            $m->setConversation($this);
            // update conversation summary fields
            $this->setLastMessageAt($m->getCreatedAt());
            $preview = mb_substr($m->getContent(), 0, 200);
            $this->setLastMessagePreview($preview);
            $this->incrementUnreadCount();
        }
        return $this;
    }

    public function removeMessage(Message $m): self
    {
        if ($this->messages->removeElement($m) && $m->getConversation() === $this) {
            $m->setConversation(null);
        }
        return $this;
    }

    public function getLastMessageAt(): ?\DateTimeImmutable
    {
        return $this->lastMessageAt;
    }
    public function setLastMessageAt(?\DateTimeImmutable $d): self
    {
        $this->lastMessageAt = $d;
        return $this;
    }

    public function getLastMessagePreview(): ?string
    {
        return $this->lastMessagePreview;
    }
    public function setLastMessagePreview(?string $s): self
    {
        $this->lastMessagePreview = $s;
        return $this;
    }

    public function getUnreadCount(): int
    {
        return $this->unreadCount;
    }
    public function setUnreadCount(int $n): self
    {
        $this->unreadCount = max(0, $n);
        return $this;
    }
    public function incrementUnreadCount(int $by = 1): self
    {
        $this->unreadCount = max(0, $this->unreadCount + $by);
        return $this;
    }
    public function resetUnreadCount(): self
    {
        $this->unreadCount = 0;
        return $this;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }
    public function setArchivedAt(?\DateTimeImmutable $d): self
    {
        $this->archivedAt = $d;
        return $this;
    }

    public function getMutedUntil(): ?\DateTimeImmutable
    {
        return $this->mutedUntil;
    }
    public function setMutedUntil(?\DateTimeImmutable $d): self
    {
        $this->mutedUntil = $d;
        return $this;
    }

    public function isMuted(): bool
    {
        if ($this->mutedUntil === null) {
            return false;
        }
        return $this->mutedUntil > new \DateTimeImmutable();
    }
}
