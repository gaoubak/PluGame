<?php

// src/Controller/FollowerController.php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\FollowerService;
use App\Traits\ApiResponseTrait;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\{Response, Request};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/api/follow')]
class FollowerController extends AbstractFOSRestController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly Security $security,
        private readonly UserRepository $users,
        private readonly FollowerService $service
    ) {
    }

    #[Route('/{userId}', name: 'follow_user', methods: ['POST'])]
    public function follow(string $userId): Response
    {
        $me = $this->security->getUser();
        if (!$me instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $target = $this->users->find($userId);
        if (!$target) {
            return $this->createApiResponse(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $res = $this->service->follow($target, $me);
        } catch (\InvalidArgumentException $e) {
            return $this->createApiResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->createApiResponse([
            'follower' => [
                'id'       => $res['follower']->getId(),
                'userId'   => $target->getId(),
                'follower' => $me->getId(),
            ],
            'conversation' => [
                'id'       => (string) $res['conversation']->getId(),
                'athlete'  => $res['conversation']->getAthlete()->getId(),
                'creator'  => $res['conversation']->getCreator()->getId(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{userId}', name: 'unfollow_user', methods: ['DELETE'])]
    public function unfollow(string $userId): Response
    {
        $me = $this->security->getUser();
        if (!$me instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $target = $this->users->find($userId);
        if (!$target) {
            return $this->createApiResponse(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $this->service->unfollow($target, $me);
        return $this->createApiResponse(['message' => 'Unfollowed'], Response::HTTP_OK);
    }
}
