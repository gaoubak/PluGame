<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Bookmark;
use App\Form\RegistrationFormType;
use App\Manager\UserManager;
use App\Repository\UserRepository;
use App\Repository\BookmarkRepository;
use App\Traits\ApiResponseTrait;
use App\Traits\FormHandlerTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/users')]
class UserController extends AbstractController
{
    use ApiResponseTrait;
    use FormHandlerTrait;

    public function __construct(
        private readonly UserManager $userManager,
        private readonly UserRepository $userRepository,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/current', name: 'get_current_user', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getCurrentUserAction()
    {
        $user = $this->getUser();

        $serializedUser = $this->serializer->normalize($user, null, ['groups' => ['user:read']]);
        return $this->createApiResponse($serializedUser, Response::HTTP_OK);
    }

    /**
     * List all users (basic)
     */
    #[Route('/', name: 'list_user', methods: ['GET'])]
    public function listUsers(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $users = $this->userRepository->findAll();

        $data = $this->serializer->normalize($users, null, [
            'groups' => ['user:read']
        ]);

        return $this->createApiResponse([
            'users' => $data,
            'total' => count($users),
            'page' => $page,
            'limit' => $limit,
        ], Response::HTTP_OK);
    }

    /**
     * Search users (fields available on this User entity)
     */
    #[Route('/search', name: 'search_users', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $query = (string) $request->query->get('query', '');
        $sport = $request->query->get('sport');
        $location = $request->query->get('location');
        $verified = $request->query->get('verified');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->userRepository->createQueryBuilder('u');

        if ($query !== '') {
            $qb->andWhere(
                'u.username LIKE :q OR u.email LIKE :q OR u.bio LIKE :q OR u.fullName LIKE :q'
            )->setParameter('q', "%{$query}%");
        }

        if ($sport) {
            $qb->andWhere('u.sport = :sport')->setParameter('sport', $sport);
        }

        if ($location) {
            $qb->andWhere('u.location = :location')->setParameter('location', $location);
        }

        if ($verified === 'true' || $verified === '1') {
            // your User entity doesn't have isVerified in the trimmed version;
            // if it does exist in DB add this line, otherwise ignore it.
            if ($this->userRepository->getClassMetadata()->hasField('isVerified')) {
                $qb->andWhere('u.isVerified = true');
            }
        }

        $users = $qb->getQuery()->getResult();

        $data = $this->serializer->normalize($users, null, [
            'groups' => ['user:read']
        ]);

        return $this->createApiResponse([
            'users' => $data,
            'total' => count($users),
            'page' => $page,
            'limit' => $limit,
        ], Response::HTTP_OK);
    }

    /**
     * Get a specific user by ID
     */
    #[Route('/{id}', name: 'get_user', methods: ['GET'])]
    public function getUserById(User $user): JsonResponse
    {
        $data = $this->serializer->normalize($user, null, [
            'groups' => ['user:read']
        ]);

        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    /**
     * Register a new user
     */
    #[Route('/register', name: 'create_user', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $this->handleForm($request, $form);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->createApiResponse([
                'message' => 'User registration failed',
                'errors' => $this->getFormErrors($form)
            ], Response::HTTP_BAD_REQUEST);
        }

        $plainPassword = $form->get('plainPassword')->getData();
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->userManager->save($user);
        $this->userManager->flush();

        $data = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'message' => 'User registered successfully'
        ];

        return $this->createApiResponse($data, Response::HTTP_CREATED);
    }

    /**
     * Update current user profile
     */
    #[Route('/update', name: 'update_current_user', methods: ['PUT', 'PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateCurrentUser(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];

        // Map allowed fields from incoming payload to actual entity fields
        if (isset($payload['username'])) {
            $user->setUsername((string)$payload['username']);
        }
        if (isset($payload['email'])) {
            $user->setEmail((string)$payload['email']);
        }
        if (isset($payload['fullName'])) {
            $user->setFullName((string)$payload['fullName']);
        }
        if (isset($payload['bio'])) {
            $user->setBio((string)$payload['bio']);
        }
        if (isset($payload['avatarUrl'])) {
            $user->setAvatarUrl((string)$payload['avatarUrl']);
        }
        if (isset($payload['coverUrl'])) {
            $user->setCoverUrl((string)$payload['coverUrl']);
        }
        if (isset($payload['sport'])) {
            $user->setSport((string)$payload['sport']);
        }
        if (isset($payload['location'])) {
            $user->setLocation((string)$payload['location']);
        }
        // roles and other sensitive fields should be managed by admin flows

        $this->em->flush();

        return $this->createApiResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'message' => 'User updated successfully'
        ]);
    }

    /**
     * Update online status (kept for compatibility if your entity has it)
     */
    #[Route('/me/status', name: 'update_online_status', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateOnlineStatus(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? null;

        // Only set if field exists on entity
        if (method_exists($user, 'setOnlineStatus') && $status !== null) {
            $user->setOnlineStatus($status);
            if (method_exists($user, 'updateLastSeen')) {
                $user->updateLastSeen();
            }
            $this->em->flush();
        }

        return $this->createApiResponse([
            'status' => 'ok',
            'onlineStatus' => method_exists($user, 'getOnlineStatus') ? $user->getOnlineStatus() : null,
            'lastSeenAt' => method_exists($user, 'getLastSeenAt') ? $user->getLastSeenAt()?->format('c') : null,
        ]);
    }

    /**
     * Heartbeat to keep user online (if supported)
     */
    #[Route('/me/heartbeat', name: 'heartbeat', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function heartbeat(#[CurrentUser] User $user): JsonResponse
    {
        if (method_exists($user, 'updateLastSeen')) {
            $user->updateLastSeen();
            $this->em->flush();
        }
        return $this->createApiResponse(['status' => 'ok']);
    }

    /**
     * Get current user's bookmarks
     */
    #[Route('/me/bookmarks', name: 'get_bookmarks', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getBookmarks(#[CurrentUser] User $user): JsonResponse
    {
        $bookmarks = $this->bookmarkRepository->findBy(['user' => $user]);

        $data = array_map(fn(Bookmark $b) => [
            'id' => $b->getId(),
            'targetUserId' => $b->getTargetUser()->getId(),
            'note' => $b->getNote(),
            'collection' => $b->getCollection(),
            'createdAt' => method_exists($b, 'getCreatedAt') ? $b->getCreatedAt()?->format('c') : null,
        ], $bookmarks);

        return $this->createApiResponse($data);
    }

    /**
     * Add bookmark (bookmarks current user -> target user)
     */
    #[Route('/{id}/bookmark', name: 'add_bookmark', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function addBookmark(
        User $targetUser,
        #[CurrentUser] User $currentUser
    ): JsonResponse {
        // Prevent bookmarking self
        if ($targetUser->getId() === $currentUser->getId()) {
            return $this->createApiResponse(['message' => 'Cannot bookmark yourself'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->bookmarkRepository->findOneBy([
            'user' => $currentUser,
            'targetUser' => $targetUser
        ]);

        if ($existing) {
            return $this->createApiResponse(['message' => 'Already bookmarked'], Response::HTTP_OK);
        }

        $bookmark = new Bookmark($currentUser, $targetUser);
        $this->em->persist($bookmark);
        $this->em->flush();

        return $this->createApiResponse([
            'message' => 'Bookmark added',
            'bookmarkId' => $bookmark->getId(),
            'userId' => $targetUser->getId(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Remove bookmark
     */
    #[Route('/{id}/bookmark', name: 'remove_bookmark', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeBookmark(
        User $targetUser,
        #[CurrentUser] User $currentUser
    ): JsonResponse {
        $existing = $this->bookmarkRepository->findOneBy([
            'user' => $currentUser,
            'targetUser' => $targetUser
        ]);

        if (!$existing) {
            return $this->createApiResponse(['message' => 'Not bookmarked'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($existing);
        $this->em->flush();

        return $this->createApiResponse(['message' => 'Bookmark removed']);
    }

    /**
     * Delete a user by ID (admin only)
     */
    #[Route('/delete/{id}', name: 'delete_user', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUser(User $user): JsonResponse
    {
        $this->userManager->removeUser($user);

        return $this->createApiResponse([
            'message' => 'User deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Helper: Extract form errors recursively
     */
    private function getFormErrors($form): array
    {
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->all() as $childForm) {
            if ($childForm instanceof \Symfony\Component\Form\FormInterface) {
                $childErrors = $this->getFormErrors($childForm);
                if (!empty($childErrors)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }
}
