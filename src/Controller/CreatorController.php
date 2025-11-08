<?php

// src/Controller/CreatorController.php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/creators')]
class CreatorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly UserRepository $userRepo
    ) {
    }

    /**
     * GET /api/creators
     * Query params:
     *  - q: full-text-ish search on username/fullName/displayName
     *  - sport: exact filter
     *  - location: exact filter
     *  - page, limit, sort (eg. sort=rating_desc|rating_asc|created_at)
     */
    #[Route('', name: 'api_creators_list', methods: ['GET'])]
    public function index(Request $req): JsonResponse
    {
        $q = trim((string)$req->query->get('q', ''));
        $sport = $req->query->get('sport');
        $location = $req->query->get('location');
        $page = max(1, (int)$req->query->get('page', 1));
        $limit = min(50, max(1, (int)$req->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;
        $sort = $req->query->get('sort', 'relevance'); // relevance | rating_desc | rating_asc | newest

        $qb = $this->em->createQueryBuilder()
            ->select('u', 'cp')
            ->from(User::class, 'u')
            ->leftJoin('u.creatorProfile', 'cp')
            ->where("u.roles LIKE :role") // naive check for role presence
            ->setParameter('role', '%ROLE_CREATOR%');

        if ($q !== '') {
            // simple LIKE search on username/fullName and creatorProfile.displayName
            $qb->andWhere('(u.username LIKE :q OR u.fullName LIKE :q OR cp.displayName LIKE :q)')
               ->setParameter('q', "%{$q}%");
        }

        if ($sport) {
            $qb->andWhere('u.sport = :sport')->setParameter('sport', $sport);
        }

        if ($location) {
            $qb->andWhere('u.location = :location')->setParameter('location', $location);
        }

        // sorting
        switch ($sort) {
            case 'rating_desc':
                $qb->leftJoin('u.receivedReviews', 'r_avg')
                   ->groupBy('u.id')
                   ->orderBy('AVG(r_avg.rating)', 'DESC');
                break;
            case 'rating_asc':
                $qb->leftJoin('u.receivedReviews', 'r_avg')
                   ->groupBy('u.id')
                   ->orderBy('AVG(r_avg.rating)', 'ASC');
                break;
            case 'newest':
                $qb->orderBy('u.id', 'DESC');
                break;
            default:
                // relevance / default
                $qb->orderBy('u.fullName', 'ASC');
        }

        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $data = $this->serializer->normalize($items, null, ['groups' => ['user:read','creator:read']]);

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'results' => $data,
        ], Response::HTTP_OK);
    }
}
