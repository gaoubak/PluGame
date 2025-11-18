<?php

// src/Service/MercurePublisher.php - COMPLETE BACKEND MERCURE PUBLISHER

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Message;
use App\Entity\Payment;
use App\Entity\Review;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub
    ) {
    }

    /**
     * Publish new message event
     */
    public function publishMessageCreated(Message $message): void
    {
        $conversation = $message->getConversation();
        $sender = $message->getSender();

        // Publish to both participants
        $topics = [];

        if ($conversation->getAthlete()) {
            $topics[] = "https://plugame.app/users/{$conversation->getAthlete()->getId()}/messages";
        }

        if ($conversation->getCreator()) {
            $topics[] = "https://plugame.app/users/{$conversation->getCreator()->getId()}/messages";
        }

        $data = [
            'type' => 'message.new',
            'data' => [
                'id' => $message->getId(),
                'conversationId' => $conversation->getId(),
                'senderId' => $sender->getId(),
                'senderUsername' => $sender->getFullName() ?? $sender->getUsername(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format('c'),
                'readAt' => $message->getReadAt()?->format('c'),
                'media' => $message->getMedia() ? [
                    'publicUrl' => $message->getMedia()->getPublicUrl(),
                    'thumbnailUrl' => $message->getMedia()->getThumbnailUrl(),
                ] : null,
            ],
            'timestamp' => (new \DateTime())->format('c'),
        ];

        foreach ($topics as $topic) {
            $update = new Update($topic, json_encode($data));
            $this->hub->publish($update);
        }
    }

    /**
     * Publish message read event
     */
    public function publishMessageRead(Message $message): void
    {
        $conversation = $message->getConversation();

        // Publish to both participants
        $topics = [];

        if ($conversation->getAthlete()) {
            $topics[] = "https://plugame.app/users/{$conversation->getAthlete()->getId()}/messages";
        }

        if ($conversation->getCreator()) {
            $topics[] = "https://plugame.app/users/{$conversation->getCreator()->getId()}/messages";
        }

        $data = [
            'type' => 'message.read',
            'data' => [
                'id' => $message->getId(),
                'conversationId' => $conversation->getId(),
                'messageId' => $message->getId(),
                'readAt' => $message->getReadAt()->format('c'),
            ],
            'timestamp' => (new \DateTime())->format('c'),
        ];

        foreach ($topics as $topic) {
            $update = new Update($topic, json_encode($data));
            $this->hub->publish($update);
        }
    }

    /**
     * Publish booking created event
     */
    public function publishBookingCreated(Booking $booking): void
    {
        // Publish to both athlete and creator
        $topics = [
            "https://plugame.app/users/{$booking->getAthlete()->getId()}/bookings",
            "https://plugame.app/users/{$booking->getCreator()->getId()}/bookings",
        ];

        $data = [
            'type' => 'booking.created',
            'data' => [
                'id' => $booking->getId(),
                'status' => $booking->getStatus(),
                'athleteId' => $booking->getAthlete()->getId(),
                'athleteName' => $booking->getAthlete()->getUsername(),
                'creatorId' => $booking->getCreator()->getId(),
                'creatorName' => $booking->getCreator()->getUsername(),
                'serviceId' => $booking->getService()->getId(),
                'serviceName' => $booking->getService()->getTitle(),
                'startTime' => $booking->getStartTime()->format('c'),
                'endTime' => $booking->getEndTime()->format('c'),
                'priceCents' => $booking->getService()->getPriceCents(),
            ],
            'timestamp' => (new \DateTime())->format('c'),
        ];

        foreach ($topics as $topic) {
            $update = new Update($topic, json_encode($data));
            $this->hub->publish($update);
        }
    }

    /**
     * Publish booking status changed event
     */
    public function publishBookingStatusChanged(Booking $booking): void
    {
        $eventType = 'booking.' . strtolower($booking->getStatus());

        // Publish to both athlete and creator
        $topics = [
            "https://plugame.app/users/{$booking->getAthlete()->getId()}/bookings",
            "https://plugame.app/users/{$booking->getCreator()->getId()}/bookings",
        ];

        $data = [
            'type' => $eventType,
            'data' => [
                'id' => $booking->getId(),
                'status' => $booking->getStatus(),
                'athleteId' => $booking->getAthlete()->getId(),
                'athleteName' => $booking->getAthlete()->getUsername(),
                'creatorId' => $booking->getCreator()->getId(),
                'creatorName' => $booking->getCreator()->getUsername(),
                'serviceId' => $booking->getService()->getId(),
                'serviceName' => $booking->getService()->getTitle(),
                'startTime' => $booking->getStartTime()->format('c'),
                'endTime' => $booking->getEndTime()->format('c'),
                'priceCents' => $booking->getService()->getPriceCents(),
                'cancelReason' => $booking->getCancelReason(),
                'completedAt' => $booking->getCompletedAt()?->format('c'),
            ],
            'timestamp' => (new \DateTime())->format('c'),
        ];

        foreach ($topics as $topic) {
            $update = new Update($topic, json_encode($data));
            $this->hub->publish($update);
        }
    }

    /**
     * Publish payment completed event
     */
    public function publishPaymentCompleted(Payment $payment): void
    {
        $update = new Update(
            "https://plugame.app/users/{$payment->getUser()->getId()}/payments",
            json_encode([
                'type' => 'payment.completed',
                'data' => [
                    'id' => $payment->getId(),
                    'bookingId' => $payment->getBooking()?->getId(),
                    'amountCents' => $payment->getAmountCents(),
                    'currency' => $payment->getCurrency(),
                    'status' => $payment->getStatus(),
                    'userId' => $payment->getUser()->getId(),
                    'createdAt' => $payment->getCreatedAt()->format('c'),
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish payment failed event
     */
    public function publishPaymentFailed(Payment $payment): void
    {
        $update = new Update(
            "https://plugame.app/users/{$payment->getUser()->getId()}/payments",
            json_encode([
                'type' => 'payment.failed',
                'data' => [
                    'id' => $payment->getId(),
                    'bookingId' => $payment->getBooking()?->getId(),
                    'amountCents' => $payment->getAmountCents(),
                    'status' => $payment->getStatus(),
                    //'error' => $payment->getErrorMessage(),
                    'userId' => $payment->getUser()->getId(),
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish review created event
     */
    public function publishReviewCreated(Review $review): void
    {
        // Notify the creator who received the review
        $update = new Update(
            "https://plugame.app/users/{$review->getCreator()->getId()}/notifications",
            json_encode([
                'type' => 'review.created',
                'data' => [
                    'id' => $review->getId(),
                    'rating' => $review->getRating(),
                    'comment' => $review->getComment(),
                    'reviewerId' => $review->getReviewer()->getId(),
                    'reviewerName' => $review->getReviewer()->getUsername(),
                    'creatorId' => $review->getCreator()->getId(),
                    'bookingId' => $review->getBooking()->getId(),
                    'createdAt' => $review->getCreatedAt()->format('c'),
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish deliverable uploaded event
     */
    public function publishDeliverableUploaded(Booking $booking, int $filesCount): void
    {
        // Notify the athlete that files are ready
        $update = new Update(
            "https://plugame.app/users/{$booking->getAthlete()->getId()}/deliverables",
            json_encode([
                'type' => 'deliverable.uploaded',
                'data' => [
                    'bookingId' => $booking->getId(),
                    'creatorId' => $booking->getCreator()->getId(),
                    'creatorName' => $booking->getCreator()->getCreatorProfile()?->getDisplayName() ?? $booking->getCreator()->getUsername(),
                    'serviceTitle' => $booking->getService()->getTitle(),
                    'filesCount' => $filesCount,
                    'canDownload' => $booking->isDeliverablesUnlocked(),
                    'uploadedAt' => (new \DateTime())->format('c'),
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish deliverable download requested event
     */
    public function publishDeliverableDownloadRequested(Booking $booking): void
    {
        // Notify the creator that athlete requested download
        $update = new Update(
            "https://plugame.app/users/{$booking->getCreator()->getId()}/deliverables",
            json_encode([
                'type' => 'deliverable.download_requested',
                'data' => [
                    'bookingId' => $booking->getId(),
                    'athleteId' => $booking->getAthlete()->getId(),
                    'athleteName' => $booking->getAthlete()->getAthleteProfile()?->getDisplayName() ?? $booking->getAthlete()->getUsername(),
                    'serviceTitle' => $booking->getService()->getTitle(),
                    'requestedAt' => $booking->getDeliverableDownloadRequestedAt()?->format('c'),
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish deliverable downloaded event (tracking pixel fired)
     */
    public function publishDeliverableDownloaded(Booking $booking): void
    {
        // Notify both parties that download was confirmed
        $topics = [
            "https://plugame.app/users/{$booking->getAthlete()->getId()}/deliverables",
            "https://plugame.app/users/{$booking->getCreator()->getId()}/deliverables",
        ];

        $data = [
            'type' => 'deliverable.downloaded',
            'data' => [
                'bookingId' => $booking->getId(),
                'athleteId' => $booking->getAthlete()->getId(),
                'creatorId' => $booking->getCreator()->getId(),
                'downloadedAt' => $booking->getDeliverableDownloadedAt()?->format('c'),
                'payoutTriggered' => true,
            ],
            'timestamp' => (new \DateTime())->format('c'),
        ];

        foreach ($topics as $topic) {
            $update = new Update($topic, json_encode($data));
            $this->hub->publish($update);
        }
    }

    /**
     * Publish payout completed event
     */
    public function publishPayoutCompleted(Booking $booking, int $amountCents): void
    {
        // Notify the creator that payout was completed
        $update = new Update(
            "https://plugame.app/users/{$booking->getCreator()->getId()}/payouts",
            json_encode([
                'type' => 'payout.completed',
                'data' => [
                    'bookingId' => $booking->getId(),
                    'athleteId' => $booking->getAthlete()->getId(),
                    'athleteName' => $booking->getAthlete()->getAthleteProfile()?->getDisplayName() ?? $booking->getAthlete()->getUsername(),
                    'serviceTitle' => $booking->getService()->getTitle(),
                    'amountCents' => $amountCents,
                    'currency' => $booking->getCurrency() ?? 'EUR',
                    'completedAt' => $booking->getPayoutCompletedAt()?->format('c'),
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish availability changed event
     */
    public function publishAvailabilityChanged(
        int $creatorId,
        string $date,
        int $slotsAvailable
    ): void {
        $update = new Update(
            "https://plugame.app/users/{$creatorId}/availability",
            json_encode([
                'type' => 'availability.changed',
                'data' => [
                    'creatorId' => $creatorId,
                    'date' => $date,
                    'slotsAvailable' => $slotsAvailable,
                    'changedAt' => (new \DateTime())->format('c'),
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish booking reminder event
     */
    public function publishBookingReminder(Booking $booking, string $startsIn): void
    {
        // Notify both athlete and creator
        $topics = [
            "https://plugame.app/users/{$booking->getAthlete()->getId()}/bookings",
            "https://plugame.app/users/{$booking->getCreator()->getId()}/bookings",
        ];

        $data = [
            'type' => 'booking.reminder',
            'data' => [
                'bookingId' => $booking->getId(),
                'serviceTitle' => $booking->getService()->getTitle(),
                'startsIn' => $startsIn,
                'startTime' => $booking->getStartTime()->format('c'),
                'athleteId' => $booking->getAthlete()->getId(),
                'athleteName' => $booking->getAthlete()->getUsername(),
                'creatorId' => $booking->getCreator()->getId(),
                'creatorName' => $booking->getCreator()->getUsername(),
            ],
            'timestamp' => (new \DateTime())->format('c'),
        ];

        foreach ($topics as $topic) {
            $update = new Update($topic, json_encode($data));
            $this->hub->publish($update);
        }
    }

    /**
     * Publish refund completed event
     */
    public function publishRefundCompleted(
        Booking $booking,
        int $amountCents,
        string $reason
    ): void {
        $update = new Update(
            "https://plugame.app/users/{$booking->getAthlete()->getId()}/payments",
            json_encode([
                'type' => 'refund.completed',
                'data' => [
                    'bookingId' => $booking->getId(),
                    'amountCents' => $amountCents,
                    'currency' => $booking->getCurrency() ?? 'EUR',
                    'reason' => $reason,
                    'creatorId' => $booking->getCreator()->getId(),
                    'creatorName' => $booking->getCreator()->getUsername(),
                    'serviceTitle' => $booking->getService()->getTitle(),
                    'refundedAt' => (new \DateTime())->format('c'),
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish generic notification event
     */
    public function publishNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): void {
        $update = new Update(
            "https://plugame.app/users/{$userId}/notifications",
            json_encode([
                'type' => 'notification.new',
                'data' => [
                    'userId' => $userId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                    'createdAt' => (new \DateTime())->format('c'),
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ])
        );

        $this->hub->publish($update);
    }
}
