<?php

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

trait UuidId
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['booking:read','user:read','conversation:read','conversation:write','slot:read','slot:write','user:read','service:read','comment:read','review:read'])]
    private Uuid $id;

    #[ORM\PrePersist]
    public function _uuidOnCreate(): void
    {
        if (!isset($this->id)) {
            $this->id = Uuid::v4();
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
}
