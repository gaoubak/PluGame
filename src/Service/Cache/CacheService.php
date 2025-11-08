<?php

namespace App\Service\Cache;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Centralized cache service with tag support
 *
 * Provides cache invalidation by tags, making it easy to clear
 * related cached items when data changes.
 */
class CacheService
{
    public function __construct(
        private readonly TagAwareCacheInterface $servicesCache,
        private readonly TagAwareCacheInterface $usersCache,
        private readonly TagAwareCacheInterface $availabilityCache,
        private readonly TagAwareCacheInterface $staticCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get or set cached service listings
     */
    public function getServices(callable $callback, array $tags = []): mixed
    {
        return $this->get(
            $this->servicesCache,
            'services.all',
            $callback,
            array_merge(['services'], $tags)
        );
    }

    /**
     * Get or set cached service by ID
     */
    public function getService(string $id, callable $callback): mixed
    {
        return $this->get(
            $this->servicesCache,
            "service.{$id}",
            $callback,
            ['services', "service.{$id}"]
        );
    }

    /**
     * Get or set cached user profile
     */
    public function getUserProfile(string $userId, callable $callback): mixed
    {
        return $this->get(
            $this->usersCache,
            "user.{$userId}",
            $callback,
            ['users', "user.{$userId}"]
        );
    }

    /**
     * Get or set cached creator availability
     */
    public function getCreatorAvailability(string $creatorId, callable $callback): mixed
    {
        return $this->get(
            $this->availabilityCache,
            "availability.creator.{$creatorId}",
            $callback,
            ['availability', "creator.{$creatorId}"]
        );
    }

    /**
     * Get or set static data (rarely changes)
     */
    public function getStatic(string $key, callable $callback, array $tags = []): mixed
    {
        return $this->get(
            $this->staticCache,
            $key,
            $callback,
            array_merge(['static'], $tags)
        );
    }

    /**
     * Invalidate all service caches
     */
    public function invalidateServices(): void
    {
        $this->invalidateTags($this->servicesCache, ['services']);
        $this->logger->info('Cache invalidated: services');
    }

    /**
     * Invalidate specific service cache
     */
    public function invalidateService(string $serviceId): void
    {
        $this->invalidateTags($this->servicesCache, ["service.{$serviceId}"]);
        $this->logger->info('Cache invalidated: service', ['id' => $serviceId]);
    }

    /**
     * Invalidate user profile cache
     */
    public function invalidateUser(string $userId): void
    {
        $this->invalidateTags($this->usersCache, ["user.{$userId}"]);
        $this->logger->info('Cache invalidated: user', ['id' => $userId]);
    }

    /**
     * Invalidate creator availability cache
     */
    public function invalidateAvailability(string $creatorId): void
    {
        $this->invalidateTags($this->availabilityCache, ["creator.{$creatorId}", 'availability']);
        $this->logger->info('Cache invalidated: availability', ['creator_id' => $creatorId]);
    }

    /**
     * Invalidate all caches (use sparingly!)
     */
    public function invalidateAll(): void
    {
        $this->servicesCache->clear();
        $this->usersCache->clear();
        $this->availabilityCache->clear();
        $this->staticCache->clear();
        $this->logger->warning('Cache invalidated: ALL');
    }

    /**
     * Generic cache get with tags
     */
    private function get(
        TagAwareCacheInterface $cache,
        string $key,
        callable $callback,
        array $tags = []
    ): mixed {
        try {
            return $cache->get($key, function (ItemInterface $item) use ($callback, $tags) {
                $item->tag($tags);
                return $callback($item);
            });
        } catch (\Exception $e) {
            $this->logger->error('Cache error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            // Fallback to direct call if cache fails
            return $callback(null);
        }
    }

    /**
     * Invalidate by tags
     */
    private function invalidateTags(TagAwareCacheInterface $cache, array $tags): void
    {
        try {
            $cache->invalidateTags($tags);
        } catch (\Exception $e) {
            $this->logger->error('Cache invalidation error', [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);
        }
    }
}
