<?php

// src/Entity/Bookmark.php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use App\Repository\BookmarkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BookmarkRepository::class)]
#[ORM\Table(name: 'bookmarks')]
#[ORM\UniqueConstraint(name: 'unique_user_target', columns: ['user_id', 'target_user_id'])]
#[ORM\HasLifecycleCallbacks]
class Bookmark
{
    use UuidId;
    use Timestamps;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookmarks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['bookmark:read'])]
    private User $user;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookmarkedBy')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['bookmark:read', 'bookmark:write'])]
    private User $targetUser;

    // ✅ Optional: Note/label for bookmark
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['bookmark:read', 'bookmark:write'])]
    private ?string $note = null;

    // ✅ Optional: Collection/folder
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['bookmark:read', 'bookmark:write'])]
    private ?string $collection = null;

    public function __construct(User $user, User $targetUser)
    {
        $this->user = $user;
        $this->targetUser = $targetUser;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getTargetUser(): User
    {
        return $this->targetUser;
    }

    public function setTargetUser(User $targetUser): self
    {
        $this->targetUser = $targetUser;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }

    public function getCollection(): ?string
    {
        return $this->collection;
    }

    public function setCollection(?string $collection): self
    {
        $this->collection = $collection;
        return $this;
    }
}
