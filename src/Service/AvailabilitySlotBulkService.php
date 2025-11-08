<?php

// src/Service/AvailabilitySlotBulkService.php
namespace App\Service;

use App\Entity\AvailabilitySlot;
use App\Entity\User;
use App\Repository\AvailabilitySlotRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AvailabilitySlotBulkService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilitySlotRepository $slots
    ) {
    }

    /**
     * @param User $creator
     * @param array<array{startTime:string,endTime:string}> $payloadSlots
     * @return array{created: list<array>, errors: list<array>}
     */
    public function createMany(User $creator, array $payloadSlots): array
    {
        $created = [];
        $errors  = [];

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            foreach ($payloadSlots as $i => $row) {
                $idx = $i; // keep index for error reporting

                // 1) Basic shape
                $startStr = (string) ($row['startTime'] ?? '');
                $endStr   = (string) ($row['endTime']   ?? '');

                // 2) Parse ISO8601 or "Y-m-d H:i:s"
                try {
                    $start = new \DateTimeImmutable($startStr);
                    $end   = new \DateTimeImmutable($endStr);
                } catch (\Throwable) {
                    $errors[] = ['index' => $idx, 'error' => 'Invalid datetime format'];
                    continue;
                }

                // 3) Semantic validation
                if ($end <= $start) {
                    $errors[] = ['index' => $idx, 'error' => 'endTime must be after startTime'];
                    continue;
                }


                // 4) Overlap check (with DB)
                $overlaps = $this->slots->findOverlaps($creator, $start, $end);
                if (!empty($overlaps)) {
                    $errors[] = ['index' => $idx, 'error' => 'Overlaps with an existing slot'];
                    continue;
                }

                // 5) Build + persist
                $slot = new AvailabilitySlot($creator, $start, $end);
                $this->em->persist($slot);

                $created[] = [
                    'id'        => (string) $slot->getId(),
                    'startTime' => $start->format(\DATE_ATOM),
                    'endTime'   => $end->format(\DATE_ATOM),
                ];
            }

            $this->em->flush();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            // If you prefer full rollback on any failure, uncomment the next line:
            // throw $e;
            $errors[] = ['index' => null, 'error' => 'Unexpected error: ' . $e->getMessage()];
        }

        return ['created' => $created, 'errors' => $errors];
    }
}
