<?php

namespace App\Controller;

use App\Entity\Like;
use App\Entity\User;
use App\Repository\LikeRepository;
use App\Repository\UserRepository;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class LikeController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly LikeRepository $likeRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * ✅ Like a post/feed item
     */
    private function tryResolveCreatorFromPostId(string $postId): ?User
    {
        if (str_starts_with($postId, 'post_')) {
            $candidateId = substr($postId, strlen('post_'));
            if (ctype_digit($candidateId)) {
                return $this->userRepository->find((int)$candidateId);
            }
        }
        return null;
    }

    #[Route('/feed/{postId}/like', name: 'like_post', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function likePost(string $postId, #[CurrentUser] User $user): JsonResponse
    {

        $existingLike = $this->likeRepository->findByUserAndPostOrCreator($user, $postId);

        if ($existingLike) {
            return $this->createApiResponse([
                'message' => 'Already liked',
                'postId' => $postId,
            ], Response::HTTP_OK);
        }

        // if this is a creator post, set likedUser relation too (if field exists)
        $creator = $this->tryResolveCreatorFromPostId($postId);
        if ($creator) {
            $like = new Like($user, $creator);
            $like->setUser($user);

            if (method_exists($like, 'setLikedUser')) {
                $like->setLikedUser($creator);
            }
        }

        $this->em->persist($like);
        $this->em->flush();

        $likesCount = $this->likeRepository->countByPostOrCreator($postId);

        return $this->createApiResponse([
            'message' => 'Post liked',
            'postId' => $postId,
            'likeId' => $like->getId(),
            'likesCount' => $likesCount,
        ], Response::HTTP_CREATED);
    }

    /**
     * ✅ Unlike a post/feed item
     */
    #[Route('/feed/{postId}/like', name: 'unlike_post', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function unlikePost(string $postId, #[CurrentUser] User $user): JsonResponse
    {
        $like = $this->likeRepository->findByUserAndPostOrCreator($user, $postId);

        if (!$like) {
            return $this->createApiResponse([
                'message' => 'Not liked',
                'postId' => $postId,
            ], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($like);
        $this->em->flush();

        $likesCount = $this->likeRepository->countByPostOrCreator($postId);

        return $this->createApiResponse([
            'message' => 'Post unliked',
            'postId' => $postId,
            'likesCount' => $likesCount,
        ], Response::HTTP_OK);
    }

    /**
     * ✅ Get likes count for a post
     */
    #[Route('/feed/{postId}/likes/count', name: 'get_likes_count', methods: ['GET'])]
    public function getLikesCount(string $postId): JsonResponse
    {
        $count = $this->likeRepository->countByPostOrCreator($postId);

        return $this->createApiResponse([
            'postId' => $postId,
            'likesCount' => $count,
        ]);
    }

    /**
     * ✅ Check if user has liked a post
     */
    #[Route('/feed/{postId}/likes/me', name: 'check_my_like', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function checkMyLike(string $postId, #[CurrentUser] User $user): JsonResponse
    {
        $like = $this->likeRepository->findByUserAndPostOrCreator($user, $postId);

        return $this->createApiResponse([
            'postId' => $postId,
            'isLiked' => $like !== null,
            'likeId' => $like?->getId(),
        ]);
    }
    /**
     * ✅ Get all posts liked by current user
     */
    #[Route('/likes/me', name: 'get_my_likes', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getMyLikes(#[CurrentUser] User $user): JsonResponse
    {
        $likes = $this->likeRepository->findByUser($user);

        $data = array_map(fn($like) => [
            'id' => $like->getId(),
            'postId' => $like->getPostId(),
            'postType' => $like->getPostType(),
            'createdAt' => $like->getCreatedAt()->format('c'),
        ], $likes);

        return $this->createApiResponse($data);
    }

    /**
     * ✅ Batch check which posts are liked
     */
    #[Route('/likes/batch-check', name: 'batch_check_likes', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function batchCheckLikes(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $postIds = $data['postIds'] ?? [];

        if (empty($postIds)) {
            return $this->createApiResponse([
                'message' => 'postIds required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $likedPostIds = $this->likeRepository->findLikedPostIds($user, $postIds);

        return $this->createApiResponse([
            'likedPostIds' => $likedPostIds,
        ]);
    }
}
