<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GiftCard;
use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\GiftCardRepository;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/gift-cards')]
#[OA\Tag(name: 'Gift Cards')]
class GiftCardController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly GiftCardRepository $giftCardRepo,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('/validate', name: 'gift_card_validate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/validate',
        summary: 'Validate a gift card',
        security: [['bearerAuth' => []]],
        tags: ['Gift Cards']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['code'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'GIFT-ABC12345'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Gift card is valid',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'valid', type: 'boolean', example: true),
                new OA\Property(property: 'balance', type: 'integer', description: 'Balance in cents', example: 5000),
                new OA\Property(property: 'balanceFormatted', type: 'string', example: '50.00'),
                new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                new OA\Property(property: 'message', type: 'string', example: 'Carte cadeau valide'),
            ]
        )
    )]
    public function validate(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['code'])) {
            throw ApiProblemException::badRequest('Code de carte cadeau requis');
        }

        $code = strtoupper(trim($data['code']));

        $giftCard = $this->giftCardRepo->findActiveByCode($code);

        if (!$giftCard) {
            return $this->createApiResponse([
                'valid' => false,
                'message' => 'Carte cadeau invalide ou expirée'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$giftCard->isValid()) {
            return $this->createApiResponse([
                'valid' => false,
                'message' => 'Cette carte cadeau n\'est plus valide'
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->createApiResponse([
            'valid' => true,
            'code' => $giftCard->getCode(),
            'balance' => $giftCard->getCurrentBalance(),
            'balanceFormatted' => number_format($giftCard->getCurrentBalance() / 100, 2),
            'currency' => $giftCard->getCurrency(),
            'expiresAt' => $giftCard->getExpiresAt()?->format(\DateTime::ATOM),
            'message' => 'Carte cadeau valide'
        ]);
    }

    #[Route('/mine', name: 'gift_card_mine', methods: ['GET'])]
    #[OA\Get(
        path: '/api/gift-cards/mine',
        summary: 'List my gift cards',
        security: [['bearerAuth' => []]],
        tags: ['Gift Cards']
    )]
    public function mine(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $giftCards = $this->giftCardRepo->findByUser($user);

        $data = array_map(fn (GiftCard $card) => [
            'id' => $card->getId(),
            'code' => $card->getCode(),
            'initialBalance' => $card->getInitialBalance(),
            'currentBalance' => $card->getCurrentBalance(),
            'balanceDisplay' => $card->getBalanceDisplay(),
            'currency' => $card->getCurrency(),
            'isActive' => $card->isActive(),
            'isValid' => $card->isValid(),
            'expiresAt' => $card->getExpiresAt()?->format(\DateTime::ATOM),
            'redeemedAt' => $card->getRedeemedAt()?->format(\DateTime::ATOM),
            'message' => $card->getMessage(),
        ], $giftCards);

        return $this->createApiResponse($data);
    }

    #[Route('/create', name: 'gift_card_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/create',
        summary: 'Create a new gift card (Admin only)',
        security: [['bearerAuth' => []]],
        tags: ['Gift Cards']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['amount'],
            properties: [
                new OA\Property(property: 'amount', type: 'integer', description: 'Amount in cents', example: 10000),
                new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                new OA\Property(property: 'recipientEmail', type: 'string', example: 'recipient@example.com'),
                new OA\Property(property: 'recipientName', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'message', type: 'string', example: 'Happy Birthday!'),
                new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', example: '2026-12-31T23:59:59Z'),
            ]
        )
    )]
    public function create(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw ApiProblemException::forbidden('Only admins can create gift cards');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['amount']) || $data['amount'] <= 0) {
            throw ApiProblemException::badRequest('Valid amount is required');
        }

        $giftCard = new GiftCard();
        $giftCard->setInitialBalance((int) $data['amount']);
        $giftCard->setCurrency($data['currency'] ?? 'EUR');
        $giftCard->setPurchasedBy($user);
        $giftCard->setRecipientEmail($data['recipientEmail'] ?? null);
        $giftCard->setRecipientName($data['recipientName'] ?? null);
        $giftCard->setMessage($data['message'] ?? null);

        if (!empty($data['expiresAt'])) {
            $giftCard->setExpiresAt(new \DateTimeImmutable($data['expiresAt']));
        }

        $this->em->persist($giftCard);
        $this->em->flush();

        return $this->createApiResponse([
            'id' => $giftCard->getId(),
            'code' => $giftCard->getCode(),
            'balance' => $giftCard->getCurrentBalance(),
            'balanceDisplay' => $giftCard->getBalanceDisplay(),
            'message' => 'Carte cadeau créée avec succès'
        ], Response::HTTP_CREATED);
    }
}