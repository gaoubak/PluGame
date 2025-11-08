<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\Booking;
use App\Entity\User;
use App\Form\ReviewType;
use App\Repository\ReviewRepository;
use App\Traits\ApiResponseTrait;
use App\Traits\FormHandlerTrait;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/api/reviews')]
class ReviewController extends AbstractFOSRestController
{
    use ApiResponseTrait;
    use FormHandlerTrait;

    public function __construct(
        private readonly ReviewRepository $repo,
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly Security $security
    ) {
    }

    #[Route('/', name: 'review_list', methods: ['GET'])]
    public function list(): Response
    {
        $items = $this->repo->findAll();
        $data  = $this->serializer->normalize($items, null, ['groups' => ['review:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'review_get', methods: ['GET'])]
    public function getOne(Review $review): Response
    {
        $data = $this->serializer->normalize($review, null, ['groups' => ['review:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    /**
     * Create a review: body must include `bookingId`, `rating`, optional `comment`.
     * The reviewer is the current user; creator is inferred from the booking.
     */
    #[Route('/create', name: 'review_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $reviewer = $this->security->getUser();
        if (!$reviewer) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload   = json_decode($request->getContent(), true) ?? [];
        $bookingId = $payload['bookingId'] ?? null;
        if (!$bookingId) {
            return $this->createApiResponse(['message' => 'bookingId is required'], Response::HTTP_BAD_REQUEST);
        }

        /** @var Booking|null $booking */
        $booking = $this->em->getRepository(Booking::class)->find($bookingId);
        if (!$booking) {
            return $this->createApiResponse(['message' => 'Booking not found'], Response::HTTP_BAD_REQUEST);
        }

        $review = new Review($booking);
        $review->setReviewer($reviewer);
        $review->setCreator($booking->getCreator());

        // Use form to apply rating/comment
        $form = $this->createForm(ReviewType::class, $review);
        $this->handleForm($request, $form);



        $this->em->persist($review);
        $this->em->flush();

        $data = $this->serializer->normalize($review, null, ['groups' => ['review:write']]);
        return $this->createApiResponse($data, Response::HTTP_CREATED);
    }

    #[Route('/update/{id}', name: 'review_update', methods: ['PUT','PATCH'])]
    public function update(Request $request, Review $review): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$user || $review->getReviewer()?->getId() !== $user->getId()) {
            return $this->createApiResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $form = $this->formFactory->create(ReviewType::class, $review, [
            'method' => $request->getMethod(),
        ]);
        $this->handleForm($request, $form);

        if (!$form->isValid()) {
            return $this->createApiResponse(['errors' => (string)$form->getErrors(true, false)], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();

        $data = $this->serializer->normalize($review, null, ['groups' => ['review:write']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/delete/{id}', name: 'review_delete', methods: ['DELETE'])]
    public function delete(Review $review): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$user || $review->getReviewer()?->getId() !== $user->getId()) {
            return $this->createApiResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($review);
        $this->em->flush();
        return $this->renderDeletedResponse('Review deleted successfully');
    }
}
