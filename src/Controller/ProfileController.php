<?php

// src/Controller/ProfileController.php
namespace App\Controller;

use App\Entity\User;
use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use App\Repository\UserRepository;
use App\Repository\ServiceOfferingRepository;

#[Route('/api/profiles')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer
    ) {
    }

    #[Route('/creator/{id}/reviews', name: 'creator_reviews', methods: ['GET'])]
    public function creatorReviews(User $creator, Request $req): JsonResponse
    {
        if (!$creator->isCreator()) {
            return $this->json(['message' => 'Not a creator'], 400);
        }

        $page  = max(1, (int)$req->query->get('page', 1));
        $limit = min(50, max(1, (int)$req->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        // Reviews ABOUT this creator
        $qb = $this->em->createQueryBuilder()
            ->select('r', 'rev', 'b')
            ->from(Review::class, 'r')
            ->leftJoin('r.reviewer', 'rev')      // fetch reviewer minimal fields
            ->leftJoin('r.booking', 'b')        // if you need booking info
            ->where('r.creator = :creator')
            ->setParameter('creator', $creator)
            ->orderBy('r.createdAt', 'DESC');

        $total = (clone $qb)->select('COUNT(r.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $data  = $this->serializer->normalize($items, null, ['groups' => ['review:read','user:read']]);

        // quick average (if you store rating on Review as int 1..5)
        $avg = $this->em->createQueryBuilder()
            ->select('AVG(r.rating)')
            ->from(Review::class, 'r')
            ->where('r.creator = :c')
            ->setParameter('c', $creator)
            ->getQuery()->getSingleScalarResult();

        return $this->json([
            'page'     => $page,
            'limit'    => $limit,
            'total'    => (int)$total,
            'avgRating' => $avg !== null ? round((float)$avg, 2) : null,
            'results'  => $data,
        ]);
    }

    #[Route('/athlete/{id}/reviews', name: 'athlete_reviews', methods: ['GET'])]
    public function athleteReviews(User $athlete, Request $req): JsonResponse
    {
        if (!$athlete->isAthlete()) {
            return $this->json(['message' => 'Not an athlete'], 400);
        }

        $page  = max(1, (int)$req->query->get('page', 1));
        $limit = min(50, max(1, (int)$req->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        // Reviews WRITTEN BY this athlete (if that's what you want)
        $qb = $this->em->createQueryBuilder()
            ->select('r', 'cre', 'b')
            ->from(Review::class, 'r')
            ->leftJoin('r.creator', 'cre')
            ->leftJoin('r.booking', 'b')
            ->where('r.reviewer = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('r.createdAt', 'DESC');

        $total = (clone $qb)->select('COUNT(r.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $data  = $this->serializer->normalize($items, null, ['groups' => ['review:read','user:read']]);

        return $this->json([
            'page'    => $page,
            'limit'   => $limit,
            'total'   => (int)$total,
            'results' => $data,
        ]);
    }

    #[Route('/api/creators/{id}/services', name: 'api_creator_services', methods: ['GET'])]
    public function services(string $id, UserRepository $userRepo, ServiceOfferingRepository $serviceRepo): JsonResponse
    {
        $user = $userRepo->find($id);
        if (!$user) {
            return $this->json(['error' => 'Creator not found'], 404);
        }

        $services = $serviceRepo->findBy(['creator' => $user, 'isActive' => true]);

        $data = array_map(fn($s) => [
            'id' => (string)$s->getId(),
            'title' => $s->getTitle(),
            'description' => $s->getDescription(),
            'priceCents' => $s->getPriceCents(),
            'durationMin' => $s->getDurationMin(),
            'kind' => $s->getKind(),
            'isActive' => $s->isActive()
        ], $services);

        return $this->json($data);
    }
}
