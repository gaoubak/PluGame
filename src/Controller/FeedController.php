<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Feed\CreatorFeedService;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/feed')]
class FeedController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly CreatorFeedService $creatorFeedService,
    ) {
    }

    /**
     * ✅ Get feed posts
     * For now, returns users as posts (mock)
     * TODO: Create Post entity
     */
    #[Route('/', name: 'get_feed', methods: ['GET'])]
    public function getFeed(Request $request, #[CurrentUser] ?User $viewer = null): JsonResponse
    {
        // Collect filters from query params
        $filters = [
            'page'        => $request->query->getInt('page', 1),
            'limit'       => $request->query->getInt('limit', 20),
            'city'        => $request->query->get('city', ''),
            'specialties' => $request->query->get('specialties', ''),
            'gear'        => $request->query->get('gear', ''),
            'interests'   => $request->query->get('interests', ''),
            'maxMedia'    => $request->query->getInt('maxMedia', 4),
            'type'        => $request->query->get('type', 'all'),
        ];


        if ($viewer === null) {
            return $this->createApiResponse([
                'message' => 'Authentication required to get personalized feed',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $result = $this->creatorFeedService->getCreatorFeed($viewer, $filters);

        return $this->createApiResponse($result, Response::HTTP_OK);
    }

    /**
     * ✅ Get specific post
     */
    #[Route('/{id}', name: 'get_feed_post', methods: ['GET'])]
    public function getFeedPost(string $id): JsonResponse
    {
        // TODO: Implement real Post entity
        // For now, return mock data
        return $this->createApiResponse([
            'id' => $id,
            'creator' => [
                'id' => '1',
                'name' => 'John Doe',
                'username' => 'johndoe',
                'avatar' => 'https://picsum.photos/200',
                'isVerified' => true,
            ],
            'mediaUrl' => 'https://picsum.photos/400/600',
            'mediaType' => 'image',
            'caption' => 'Amazing shot!',
            'likesCount' => 150,
            'commentsCount' => 25,
            'sharesCount' => 10,
            'createdAt' => (new \DateTime())->format('c'),
        ]);
    }

    /**
     * ✅ NEW: Like a post
     */
    #[Route('/{id}/like', name: 'like_post', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function likePost(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {

        return $this->createApiResponse([
            'message' => 'Post liked',
            'postId' => $id,
            'userId' => $user->getId(),
        ]);
    }

    /**
     * ✅ NEW: Unlike a post
     */
    #[Route('/{id}/like', name: 'unlike_post', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function unlikePost(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        // TODO: Implement Like entity
        return $this->createApiResponse([
            'message' => 'Post unliked',
            'postId' => $id,
        ]);
    }

    /**
     * ✅ NEW: Comment on a post
     */
    #[Route('/{id}/comment', name: 'comment_post', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function commentPost(
        string $id,
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? '';

        if (empty($content)) {
            return $this->createApiResponse([
                'message' => 'Comment content is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        // TODO: Implement Comment entity
        return $this->createApiResponse([
            'message' => 'Comment added',
            'postId' => $id,
            'content' => $content,
        ]);
    }

    /**
     * ✅ NEW: Share a post
     */
    #[Route('/{id}/share', name: 'share_post', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function sharePost(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        // TODO: Implement Share tracking
        return $this->createApiResponse([
            'message' => 'Post shared',
            'postId' => $id,
        ]);
    }
}
