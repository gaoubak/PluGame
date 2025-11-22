<?php

// src/Service/WalletService.php

namespace App\Service;

use App\Entity\User;
use App\Entity\WalletCredit;
use App\Entity\Payment;
use App\Entity\Booking;
use App\Repository\WalletCreditRepository;
use Doctrine\ORM\EntityManagerInterface;

class WalletService
{
    public function __construct(
        private readonly WalletCreditRepository $walletRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Get user's wallet balance in cents
     */
    public function getBalance(User $user): int
    {
        return $this->walletRepository->getUserBalance($user);
    }

    /**
     * Add credits to user wallet (purchase or bonus)
     */
    public function addCredits(
        User $user,
        int $amountCents,
        string $type = WalletCredit::TYPE_PURCHASE,
        ?string $description = null,
        ?Payment $payment = null,
        ?\DateTimeInterface $expiresAt = null
    ): WalletCredit {
        $credit = new WalletCredit();
        $credit->setUser($user);
        $credit->setAmountCents($amountCents);
        $credit->setType($type);
        $credit->setDescription($description);
        $credit->setPayment($payment);
        $credit->setExpiresAt($expiresAt);

        $this->em->persist($credit);
        $this->em->flush();

        return $credit;
    }

    /**
     * Use credits for booking
     */
    public function useCredits(
        User $user,
        int $amountCents,
        Booking $booking,
        ?string $description = null
    ): WalletCredit {
        $balance = $this->getBalance($user);

        if ($balance < $amountCents) {
            throw new \RuntimeException('Insufficient wallet balance');
        }

        $credit = new WalletCredit();
        $credit->setUser($user);
        $credit->setAmountCents($amountCents);
        $credit->setType(WalletCredit::TYPE_USAGE);
        $credit->setDescription($description ?? 'Payment for booking');
        $credit->setBooking($booking);

        $this->em->persist($credit);
        $this->em->flush();

        return $credit;
    }

    /**
     * Refund credits to wallet
     */
    public function refundCredits(
        User $user,
        int $amountCents,
        ?Booking $booking = null,
        ?string $description = null
    ): WalletCredit {
        $credit = new WalletCredit();
        $credit->setUser($user);
        $credit->setAmountCents($amountCents);
        $credit->setType(WalletCredit::TYPE_REFUND);
        $credit->setDescription($description ?? 'Refund for cancelled booking');
        $credit->setBooking($booking);

        $this->em->persist($credit);
        $this->em->flush();

        return $credit;
    }

    /**
     * Get transaction history
     */
    public function getHistory(User $user, int $limit = 50): array
    {
        return $this->walletRepository->getUserHistory($user, $limit);
    }

    /**
     * Expire old credits
     */
    public function expireCredits(): int
    {
        $qb = $this->em->createQueryBuilder();
        $qb->update(WalletCredit::class, 'wc')
            ->set('wc.isExpired', true)
            ->where('wc.expiresAt IS NOT NULL')
            ->andWhere('wc.expiresAt < :now')
            ->andWhere('wc.isExpired = false')
            ->setParameter('now', new \DateTime());

        return $qb->getQuery()->execute();
    }

    /**
     * Can user pay with wallet?
     */
    public function canPayWithWallet(User $user, int $amountCents): bool
    {
        return $this->getBalance($user) >= $amountCents;
    }

    /**
     * Format balance for display
     */
    public function getFormattedBalance(User $user): string
    {
        $balance = $this->getBalance($user);
        return number_format($balance / 100, 2) . ' €';
    }
}
