<?php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'notification')]
#[ORM\Index(name: 'idx_notification_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_notification_read', columns: ['read_at'])]
#[ORM\Index(name: 'idx_notification_created', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    use UuidId;
    use Timestamps;

    /**
     * User receiving the notification
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['notification:read'])]
    private ?User $user = null;

    /**
     * Type of notification: booking, message, follow, review, etc.
     */
    #[ORM\Column(name: '`type`', type: 'string', length: 50)]
    #[Groups(['notification:read'])]
    private ?string $type = null;

    /**
     * Notification title
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['notification:read'])]
    private ?string $title = null;

    /**
     * Notification message/body
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['notification:read'])]
    private ?string $message = null;

    /**
     * Additional data (JSON)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['notification:read'])]
    private ?array $data = null;

    /**
     * When notification was read (null = unread)
     */
    #[ORM\Column(name: 'read_at', type: 'datetime', nullable: true)]
    #[Groups(['notification:read'])]
    private ?\DateTimeInterface $readAt = null;

    /**
     * URL to navigate when clicking notification
     */
    #[ORM\Column(name: 'action_url', type: 'string', length: 500, nullable: true)]
    #[Groups(['notification:read'])]
    private ?string $actionUrl = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function setActionUrl(?string $actionUrl): self
    {
        $this->actionUrl = $actionUrl;
        return $this;
    }

    /**
     * Check if notification is read
     */
    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    /**
     * Mark as read
     */
    public function markAsRead(): self
    {
        if ($this->readAt === null) {
            $this->readAt = new \DateTime();
        }
        return $this;
    }

    /**
     * Mark as unread
     */
    public function markAsUnread(): self
    {
        $this->readAt = null;
        return $this;
    }
}
