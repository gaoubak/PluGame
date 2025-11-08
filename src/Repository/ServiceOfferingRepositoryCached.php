<?php

namespace App\Repository;

use App\Entity\ServiceOffering;
use App\Entity\User;
use App\Service\Cache\CacheService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Cached Service Offering Repository
 *
 * Example of repository with caching layer
 */
class ServiceOfferingRepositoryCached extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CacheService $cache,
    ) {
        parent::__construct($registry, ServiceOffering::class);
    }

    /**
     * Find all active services (cached)
     */
    public function findAllActiveCached(): array
    {
        return $this->cache->getServices(function () {
            return $this->createQueryBuilder('s')
                ->where('s.isActive = true')
                ->orderBy('s.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        }, ['active']);
    }

    /**
     * Find service by ID (cached)
     */
    public function findByIdCached(string $id): ?ServiceOffering
    {
        return $this->cache->getService($id, function () use ($id) {
            return $this->find($id);
        });
    }

    /**
     * Find services by creator (cached per creator)
     */
    public function findByCreatorCached(User $creator): array
    {
        $creatorId = $creator->getId();

        return $this->cache->getStatic(
            "services.creator.{$creatorId}",
            function () use ($creator) {
                return $this->createQueryBuilder('s')
                    ->where('s.creator = :creator')
                    ->andWhere('s.isActive = true')
                    ->setParameter('creator', $creator)
                    ->orderBy('s.createdAt', 'DESC')
                    ->getQuery()
                    ->getResult();
            },
            ['services', "creator.{$creatorId}"]
        );
    }

    /**
     * Save and invalidate cache
     */
    public function saveAndInvalidateCache(ServiceOffering $service, bool $flush = true): void
    {
        $this->getEntityManager()->persist($service);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        // Invalidate caches
        $this->cache->invalidateService($service->getId());
        $this->cache->invalidateServices(); // Invalidate list cache too
    }

    /**
     * Delete and invalidate cache
     */
    public function deleteAndInvalidateCache(ServiceOffering $service): void
    {
        $serviceId = $service->getId();

        $this->getEntityManager()->remove($service);
        $this->getEntityManager()->flush();

        // Invalidate caches
        $this->cache->invalidateService($serviceId);
        $this->cache->invalidateServices();
    }
}
