<?php

// src/Service/FollowerService.php
namespace App\Service;

use App\Entity\Follower;
use App\Entity\User;
use App\Entity\Conversation;
use App\Repository\FollowerRepository;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class FollowerService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FollowerRepository $followers,
        private readonly ConversationRepository $conversations
    ) {
    }

    /**
     * Create a follow (if not already) and a conversation (if not already).
     * @return array{follower:Follower, conversation:Conversation}
     */
    public function follow(User $target, User $follower): array
    {
        if ($target->getId() === $follower->getId()) {
            throw new \InvalidArgumentException('You cannot follow yourself.');
        }

        // 1) ensure Follower row exists
        $row = $this->followers->findOneByUserAndFollower($target, $follower);
        if (!$row) {
            $row = (new Follower())
                ->setFollower($target)
                ->setFollowing($follower);
            $this->em->persist($row);
        }

        // 2) ensure a Conversation between both exists
        $conv = $this->conversations->findBetweenUsers($target, $follower);
        if (!$conv) {
            $athlete = $follower->getAthleteProfile() ? $follower : ($target->getAthleteProfile() ? $target : $follower);
            $creator = $athlete === $follower ? $target : $follower;
            if (!$creator->getCreatorProfile() && $athlete === $target) {
                // fallback swap to keep roles consistent
                $athlete = $follower;
                $creator = $target;
            }

            $conv = (new Conversation())
                ->setAthlete($athlete)
                ->setCreator($creator);

            $this->em->persist($conv);
        }

        $this->em->flush();

        return ['follower' => $row, 'conversation' => $conv];
    }

    /** Unfollow (does not delete the conversation) */
    public function unfollow(User $target, User $follower): void
    {
        $row = $this->followers->findOneByUserAndFollower($target, $follower);
        if ($row) {
            $this->em->remove($row);
            $this->em->flush();
        }
    }
}
