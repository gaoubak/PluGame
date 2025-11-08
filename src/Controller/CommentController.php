<?php

// src/Controller/CommentController.php - BACKEND COMMENTS

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\UserRepository;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/api')]
class CommentController extends AbstractFOSRestController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CommentRepository $commentRepo,
        private readonly UserRepository $userepo,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly Security $security
    ) {
    }

    /**
     * Get comments for a post
     * GET /api/feed/{postId}/comments
     */
    #[Route('/feed/{postId}/comments', name: 'comment_list', methods: ['GET'])]
    public function list(string $postId, Request $request): Response
    {
        $post = $this->userepo->find($postId);
        if (!$post) {
            return $this->createApiResponse(['message' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);
        $sortBy = $request->query->get('sortBy', 'newest');

        $offset = ($page - 1) * $limit;

        $qb = $this->commentRepo->createQueryBuilder('c')
            ->where('c.post = :post')
            ->andWhere('c.parentComment IS NULL') // Only top-level comments
            ->setParameter('post', $post)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        // Sort
        switch ($sortBy) {
            case 'oldest':
                $qb->orderBy('c.createdAt', 'ASC');
                break;
            case 'popular':
                $qb->orderBy('c.likesCount', 'DESC');
                break;
            case 'newest':
            default:
                $qb->orderBy('c.createdAt', 'DESC');
                break;
        }

        $comments = $qb->getQuery()->getResult();

        // Get current user for isLiked field
        $currentUser = $this->security->getUser();

        // Add isLiked field to each comment
        $data = array_map(function ($comment) use ($currentUser) {
            $normalized = $this->serializer->normalize($comment, null, ['groups' => ['comment:read']]);

            // Check if current user liked this comment
            if ($currentUser instanceof User) {
                $normalized['isLiked'] = $comment->isLikedByUser($currentUser);
            } else {
                $normalized['isLiked'] = false;
            }

            return $normalized;
        }, $comments);

        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    /**
     * Create a comment
     * POST /api/feed/{postId}/comments
     */
    #[Route('/feed/{postId}/comments', name: 'comment_create', methods: ['POST'])]
    public function create(string $postId, Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $post = $this->userepo->find($postId);
        if (!$post) {
            return $this->createApiResponse(['message' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $content = trim($payload['content'] ?? '');

        if (empty($content)) {
            return $this->createApiResponse(['message' => 'Content is required'], Response::HTTP_BAD_REQUEST);
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setUser($user);
        $comment->setContent($content);
        $comment->setCreatedAt(new \DateTime());

        // Increment post comments count

        $this->em->persist($comment);
        $this->em->flush();

        $data = $this->serializer->normalize($comment, null, ['groups' => ['comment:read']]);
        $data['isLiked'] = false;

        return $this->createApiResponse($data, Response::HTTP_CREATED);
    }

    /**
     * Reply to a comment
     * POST /api/comments/{commentId}/replies
     */
    #[Route('/comments/{commentId}/replies', name: 'comment_reply', methods: ['POST'])]
    public function reply(string $commentId, Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $parentComment = $this->commentRepo->find($commentId);
        if (!$parentComment) {
            return $this->createApiResponse(['message' => 'Comment not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $content = trim($payload['content'] ?? '');

        if (empty($content)) {
            return $this->createApiResponse(['message' => 'Content is required'], Response::HTTP_BAD_REQUEST);
        }

        $reply = new Comment();
        $reply->setPost($parentComment->getPost());
        $reply->setUser($user);
        $reply->setContent($content);
        $reply->setParentComment($parentComment);
        $reply->setCreatedAt(new \DateTime());

        // Increment parent's replies count
        $parentComment->incrementRepliesCount();

        $this->em->persist($reply);
        $this->em->flush();

        $data = $this->serializer->normalize($reply, null, ['groups' => ['comment:read']]);
        $data['isLiked'] = false;

        return $this->createApiResponse($data, Response::HTTP_CREATED);
    }

    /**
     * Get replies for a comment
     * GET /api/comments/{commentId}/replies
     */
    #[Route('/comments/{commentId}/replies', name: 'comment_replies', methods: ['GET'])]
    public function replies(string $commentId): Response
    {
        $comment = $this->commentRepo->find($commentId);
        if (!$comment) {
            return $this->createApiResponse(['message' => 'Comment not found'], Response::HTTP_NOT_FOUND);
        }

        $replies = $this->commentRepo->findBy(
            ['parentComment' => $comment],
            ['createdAt' => 'ASC']
        );

        $currentUser = $this->security->getUser();

        $data = array_map(function ($reply) use ($currentUser) {
            $normalized = $this->serializer->normalize($reply, null, ['groups' => ['comment:read']]);

            if ($currentUser instanceof User) {
                $normalized['isLiked'] = $reply->isLikedByUser($currentUser);
            } else {
                $normalized['isLiked'] = false;
            }

            return $normalized;
        }, $replies);

        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    /**
     * Like a comment
     * POST /api/comments/{commentId}/like
     */
    #[Route('/comments/{commentId}/like', name: 'comment_like', methods: ['POST'])]
    public function like(string $commentId): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $comment = $this->commentRepo->find($commentId);
        if (!$comment) {
            return $this->createApiResponse(['message' => 'Comment not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if already liked
        if ($comment->isLikedByUser($user)) {
            return $this->createApiResponse([
                'message' => 'Already liked',
                'likesCount' => $comment->getLikesCount(),
            ], Response::HTTP_OK);
        }

        $comment->addLike($user);
        $this->em->flush();

        return $this->createApiResponse([
            'message' => 'Comment liked',
            'likesCount' => $comment->getLikesCount(),
        ], Response::HTTP_OK);
    }

    /**
     * Unlike a comment
     * DELETE /api/comments/{commentId}/like
     */
    #[Route('/comments/{commentId}/like', name: 'comment_unlike', methods: ['DELETE'])]
    public function unlike(string $commentId): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $comment = $this->commentRepo->find($commentId);
        if (!$comment) {
            return $this->createApiResponse(['message' => 'Comment not found'], Response::HTTP_NOT_FOUND);
        }

        $comment->removeLike($user);
        $this->em->flush();

        return $this->createApiResponse([
            'message' => 'Comment unliked',
            'likesCount' => $comment->getLikesCount(),
        ], Response::HTTP_OK);
    }

    /**
     * Edit a comment
     * PUT /api/comments/{commentId}
     */
    #[Route('/comments/{commentId}', name: 'comment_edit', methods: ['PUT'])]
    public function edit(string $commentId, Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $comment = $this->commentRepo->find($commentId);
        if (!$comment) {
            return $this->createApiResponse(['message' => 'Comment not found'], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($comment->getUser()->getId() !== $user->getId()) {
            return $this->createApiResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $content = trim($payload['content'] ?? '');

        if (empty($content)) {
            return $this->createApiResponse(['message' => 'Content is required'], Response::HTTP_BAD_REQUEST);
        }

        $comment->setContent($content);
        $comment->setUpdatedAt(new \DateTime());

        $this->em->flush();

        $data = $this->serializer->normalize($comment, null, ['groups' => ['comment:read']]);
        $data['isLiked'] = $comment->isLikedByUser($user);

        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    /**
     * Delete a comment
     * DELETE /api/comments/{commentId}
     */
    #[Route('/comments/{commentId}', name: 'comment_delete', methods: ['DELETE'])]
    public function delete(string $commentId): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $comment = $this->commentRepo->find($commentId);
        if (!$comment) {
            return $this->createApiResponse(['message' => 'Comment not found'], Response::HTTP_NOT_FOUND);
        }

        // Check ownership or admin
        if ($comment->getUser()->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->createApiResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        // Decrement post comments count
        $comment->getPost()->decrementCommentsCount();

        // If has parent, decrement parent's replies count
        if ($comment->getParentComment()) {
            $comment->getParentComment()->decrementRepliesCount();
        }

        $this->em->remove($comment);
        $this->em->flush();

        return $this->createApiResponse(['message' => 'Comment deleted'], Response::HTTP_OK);
    }

    /**
     * Report a comment
     * POST /api/comments/{commentId}/report
     */
    #[Route('/comments/{commentId}/report', name: 'comment_report', methods: ['POST'])]
    public function report(string $commentId, Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $comment = $this->commentRepo->find($commentId);
        if (!$comment) {
            return $this->createApiResponse(['message' => 'Comment not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $reason = trim($payload['reason'] ?? '');

        if (empty($reason)) {
            return $this->createApiResponse(['message' => 'Reason is required'], Response::HTTP_BAD_REQUEST);
        }

        // TODO: Create Report entity and save report
        // For now, just log it
        error_log("Comment {$commentId} reported by user {$user->getId()} for: {$reason}");

        return $this->createApiResponse(['message' => 'Comment reported'], Response::HTTP_OK);
    }
}
