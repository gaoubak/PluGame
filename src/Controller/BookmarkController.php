<?php

// src/Controller/BookmarkController.php

namespace App\Controller;

use App\Entity\Bookmark;
use App\Entity\User;
use App\Repository\BookmarkRepository;
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
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api')]
class BookmarkController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * ✅ Get all bookmarks for current user
     */
    #[Route('/users/me/bookmarks', name: 'get_my_bookmarks', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getMyBookmarks(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $collection = $request->query->get('collection');

        $bookmarks = $this->bookmarkRepository->findByUser($user, $collection);

        // Get the bookmarked users
        $bookmarkedUsers = array_map(fn($b) => $b->getTargetUser(), $bookmarks);

        $data = $this->serializer->normalize($bookmarkedUsers, null, [
            'groups' => ['user:read', 'profile:read']
        ]);

        return $this->createApiResponse([
            'bookmarks' => $data,
            'total' => count($bookmarks),
            'collection' => $collection,
        ]);
    }

    /**
     * ✅ Add a bookmark
     */
    #[Route('/users/{id}/bookmark', name: 'add_bookmark', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function addBookmark(
        User $targetUser,
        Request $request,
        #[CurrentUser] User $currentUser
    ): JsonResponse {
        // Can't bookmark yourself
        if ($targetUser->getId() === $currentUser->getId()) {
            return $this->createApiResponse([
                'message' => 'Cannot bookmark yourself',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if already bookmarked
        $existingBookmark = $this->bookmarkRepository->findByUserAndTarget($currentUser, $targetUser);

        if ($existingBookmark) {
            return $this->createApiResponse([
                'message' => 'Already bookmarked',
                'bookmarkId' => $existingBookmark->getId(),
            ], Response::HTTP_OK);
        }

        // Create new bookmark
        $data = json_decode($request->getContent(), true);
        $bookmark = new Bookmark($currentUser, $targetUser);

        if (isset($data['note'])) {
            $bookmark->setNote($data['note']);
        }
        if (isset($data['collection'])) {
            $bookmark->setCollection($data['collection']);
        }

        $this->em->persist($bookmark);
        $this->em->flush();

        return $this->createApiResponse([
            'message' => 'Bookmark added',
            'bookmarkId' => $bookmark->getId(),
            'targetUserId' => $targetUser->getId(),
        ], Response::HTTP_CREATED);
    }

    /**
     * ✅ Remove a bookmark
     */
    #[Route('/users/{id}/bookmark', name: 'remove_bookmark', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeBookmark(
        User $targetUser,
        #[CurrentUser] User $currentUser
    ): JsonResponse {
        $bookmark = $this->bookmarkRepository->findByUserAndTarget($currentUser, $targetUser);

        if (!$bookmark) {
            return $this->createApiResponse([
                'message' => 'Bookmark not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($bookmark);
        $this->em->flush();

        return $this->createApiResponse([
            'message' => 'Bookmark removed',
            'targetUserId' => $targetUser->getId(),
        ], Response::HTTP_OK);
    }

    /**
     * ✅ Update a bookmark (note/collection)
     */
    #[Route('/bookmarks/{id}', name: 'update_bookmark', methods: ['PUT', 'PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateBookmark(
        Bookmark $bookmark,
        Request $request,
        #[CurrentUser] User $currentUser
    ): JsonResponse {
        // Check ownership
        if ($bookmark->getUser() !== $currentUser) {
            return $this->createApiResponse([
                'message' => 'Not authorized',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['note'])) {
            $bookmark->setNote($data['note']);
        }
        if (isset($data['collection'])) {
            $bookmark->setCollection($data['collection']);
        }

        $this->em->flush();

        return $this->createApiResponse([
            'message' => 'Bookmark updated',
            'bookmarkId' => $bookmark->getId(),
        ]);
    }

    /**
     * ✅ Check if user is bookmarked
     */
    #[Route('/users/{id}/bookmark/check', name: 'check_bookmark', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function checkBookmark(
        User $targetUser,
        #[CurrentUser] User $currentUser
    ): JsonResponse {
        $bookmark = $this->bookmarkRepository->findByUserAndTarget($currentUser, $targetUser);

        return $this->createApiResponse([
            'isBookmarked' => $bookmark !== null,
            'bookmarkId' => $bookmark?->getId(),
        ]);
    }

    /**
     * ✅ Get bookmark collections
     */
    #[Route('/bookmarks/collections', name: 'get_bookmark_collections', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getCollections(#[CurrentUser] User $user): JsonResponse
    {
        $collections = $this->bookmarkRepository->findCollectionsByUser($user);

        return $this->createApiResponse([
            'collections' => $collections,
            'total' => count($collections),
        ]);
    }

    /**
     * ✅ Batch check which users are bookmarked
     */
    #[Route('/bookmarks/batch-check', name: 'batch_check_bookmarks', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function batchCheckBookmarks(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $userIds = $data['userIds'] ?? [];

        if (empty($userIds)) {
            return $this->createApiResponse([
                'message' => 'userIds required',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Convert to User objects
        $users = $this->userRepository->findBy(['id' => $userIds]);

        $bookmarkedIds = $this->bookmarkRepository->findBookmarkedUserIds($user, $users);

        return $this->createApiResponse([
            'bookmarkedUserIds' => $bookmarkedIds,
        ]);
    }

    /**
     * ✅ Get bookmark stats
     */
    #[Route('/bookmarks/stats', name: 'get_bookmark_stats', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getStats(#[CurrentUser] User $user): JsonResponse
    {
        $total = $this->bookmarkRepository->countByUser($user);
        $collections = $this->bookmarkRepository->findCollectionsByUser($user);

        return $this->createApiResponse([
            'totalBookmarks' => $total,
            'totalCollections' => count($collections),
            'collections' => $collections,
        ]);
    }
}
