<?php

// src/Repository/MediaAssetRepository.php
namespace App\Repository;

use App\Entity\MediaAsset;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MediaAssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaAsset::class);
    }

    /** @return MediaAsset[] */
    public function findRecentByPurposePaginated(string $purpose, int $offset, int $limit): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.purpose = :p')
            ->setParameter('p', $purpose)
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findLastForOwnerAndPurpose(User $owner, string $purpose): ?MediaAsset
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.owner = :u')
            ->andWhere('m.purpose = :p')
            ->setParameters(['u' => $owner, 'p' => $purpose])
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
