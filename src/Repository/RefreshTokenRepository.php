<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findOneByToken(string $token): ?RefreshToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function revokeAllForUser(User $user): int
    {
        return $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.revoked', ':revoked')
            ->where('rt.user = :user')
            ->setParameter('revoked', true)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function deleteExpired(): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function deleteRevoked(): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.revoked = :revoked')
            ->setParameter('revoked', true)
            ->getQuery()
            ->execute();
    }
}
