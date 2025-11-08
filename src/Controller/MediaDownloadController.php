<?php

namespace App\Controller;

use App\Entity\MediaAsset;
use App\Entity\MediaDownloadToken;
use App\Service\R2Storage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, StreamedResponse};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/api/media')]
class MediaDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly R2Storage $r2
    ) {
    }

    // 1) Create a one-time token for an asset you own
    #[Route('/{id}/one-time-link', name: 'media_one_time_link', methods: ['POST'])]
    public function createOneTimeLink(MediaAsset $asset): Response
    {
        // (optional) auth/authorization checks here

        $token = (string) new Ulid();
        $expires = (new \DateTimeImmutable())->modify('+30 minutes');

        $this->em->persist(new MediaDownloadToken($token, $asset, $expires));
        $this->em->flush();

        return $this->json([
            'downloadUrl' => sprintf(
                '%s/api/media/one-time/%s',
                rtrim($this->getParameter('app_public_base_url'), '/'),
                $token
            ),
            'expiresAt' => $expires->format(\DATE_ATOM),
        ]);
    }

    // 2) Single-use download -> stream from R2, then delete
    #[Route('/one-time/{token}', name: 'media_one_time_download', methods: ['GET'])]
    public function download(string $token): Response
    {
        $repo = $this->em->getRepository(MediaDownloadToken::class);
        /** @var MediaDownloadToken|null $t */
        $t = $repo->find($token);
        if (!$t) {
            return $this->json(['message' => 'Not found'], 404);
        }
        if ($t->getUsedAt() !== null) {
            return $this->json(['message' => 'Already used'], 410);
        }
        if ($t->getExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['message' => 'Expired'], 410);
        }

        $asset = $t->getAsset();
        $key   = $asset->getKey();
        $head  = $this->r2->head($key); // implement a head() to fetch ContentLength/Type if you want

        $response = new StreamedResponse(function () use ($key) {
            // stream the object
            $stream = $this->r2->getStream($key); // implement getStream() to return a PHP stream resource
            fpassthru($stream);
            fclose($stream);
        });

        $response->headers->set('Content-Type', $asset->getContentType());
        $response->headers->set('Content-Disposition', 'attachment; filename="' . addslashes($asset->getFilename()) . '"');

        // Mark used, flush, then delete after response is sent
        $t->setUsedAt(new \DateTimeImmutable());
        $this->em->flush();

        // After sending the response bytes, delete from R2 & optionally DB
        $response->sendCallback = function () use ($key, $asset) {
            // Try to finish client response first
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            // Now delete the object & optionally the DB row
            try {
                $this->r2->delete($key);
            } catch (\Throwable) {
            }
            // You can also $this->em->remove($asset); $this->em->flush(); if you want DB cleanup too.
        };

        return $response;
    }
}
