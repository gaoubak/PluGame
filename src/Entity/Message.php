<?php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use App\Entity\Traits\SoftDeletable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'message')]
#[ORM\Index(columns: ['conversation_id'], name: 'idx_message_conversation')]
#[ORM\Index(columns: ['sender_id'], name: 'idx_message_sender')]
#[ORM\HasLifecycleCallbacks]
class Message
{
    use UuidId;
    use Timestamps;
    use SoftDeletable;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['get_message'])]
    private ?User $sender = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['get_message'])]
    private string $content = '';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['get_message'])]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\ManyToOne(targetEntity: MediaAsset::class)]
    #[ORM\JoinColumn(name: 'media_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['get_message'])]
    private ?MediaAsset $media = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'reply_to_message_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['get_message'])]
    private ?Message $replyTo = null;

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }
    public function setConversation(?Conversation $c): self
    {
        $this->conversation = $c;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }
    public function setSender(User $u): self
    {
        $this->sender = $u;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }
    public function setReadAt(?\DateTimeImmutable $d): self
    {
        $this->readAt = $d;
        return $this;
    }

    public function getMedia(): ?MediaAsset
    {
        return $this->media;
    }
    public function setMedia(?MediaAsset $m): self
    {
        $this->media = $m;
        return $this;
    }

    public function getReplyTo(): ?Message
    {
        return $this->replyTo;
    }
    public function setReplyTo(?Message $msg): self
    {
        $this->replyTo = $msg;
        return $this;
    }
}
