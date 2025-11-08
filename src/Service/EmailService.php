<?php

// src/Service/EmailService.php - DELIVERABLE EMAIL WITH TRACKING

namespace App\Service;

use App\Entity\Booking;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $senderEmail = 'no-reply@plugame.app',
        private readonly string $senderName = 'Plugame',
    ) {
    }

    /**
     * ✅ Send deliverable download link email with tracking pixel
     */
    public function sendDeliverableEmail(
        string $recipientEmail,
        Booking $booking,
        string $downloadUrl,
        string $trackingToken
    ): void {
        $creator = $booking->getCreator();
        $creatorName = $creator->getCreatorProfile()?->getDisplayName()
            ?? $creator->getUsername();

        // Generate tracking pixel URL
        $trackingUrl = $this->urlGenerator->generate(
            'deliverable_track',
            ['token' => $trackingToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Render email template
        $htmlContent = $this->twig->render('emails/deliverable.html.twig', [
            'creatorName' => $creatorName,
            'serviceTitle' => $booking->getService()->getTitle(),
            'downloadUrl' => $downloadUrl,
            'trackingUrl' => $trackingUrl,
            'expiresIn' => '7 days',
            'bookingId' => $booking->getId(),
        ]);

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($recipientEmail)
            ->subject("Your files from {$creatorName} are ready!")
            ->html($htmlContent);

        $this->mailer->send($email);
    }

    /**
     * ✅ Send booking confirmation email
     */
    public function sendBookingConfirmation(
        string $recipientEmail,
        Booking $booking
    ): void {
        $creator = $booking->getCreator();
        $athlete = $booking->getAthlete();

        $htmlContent = $this->twig->render('emails/booking_confirmed.html.twig', [
            'creatorName' => $creator->getCreatorProfile()?->getDisplayName() ?? $creator->getUsername(),
            'athleteName' => $athlete->getAthleteProfile()?->getDisplayName() ?? $athlete->getUsername(),
            'serviceTitle' => $booking->getService()->getTitle(),
            'startDate' => $booking->getStartDate(),
            'endDate' => $booking->getEndDate(),
            'totalAmount' => $booking->getTotalAmountCents() / 100,
            'currency' => $booking->getCurrency(),
        ]);

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($recipientEmail)
            ->subject('Booking Confirmed - Plugame')
            ->html($htmlContent);

        $this->mailer->send($email);
    }

    /**
     * ✅ Send payout notification email
     */
    public function sendPayoutNotification(
        string $recipientEmail,
        Booking $booking,
        float $amount,
        string $currency = 'EUR'
    ): void {
        $htmlContent = $this->twig->render('emails/payout_completed.html.twig', [
            'amount' => $amount,
            'currency' => $currency,
            'serviceTitle' => $booking->getService()->getTitle(),
            'bookingId' => $booking->getId(),
        ]);

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($recipientEmail)
            ->subject("You've received a payment - Plugame")
            ->html($htmlContent);

        $this->mailer->send($email);
    }
}
