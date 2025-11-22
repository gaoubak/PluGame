<?php

// src/Controller/MediaAssetController.php
namespace App\Controller;

use App\Entity\MediaAsset;
use App\Entity\User;
use App\Entity\Booking;
use App\Entity\CreatorProfile;
use App\Entity\AthleteProfile;
use App\Service\R2Storage;
use App\Service\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

#[Route('/api/media')]
class MediaAssetController extends AbstractController
{
    public function __construct(
        private readonly R2Storage $r2,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly MercurePublisher $mercurePublisher
    ) {
    }

    /**
     * Multipart upload to API with MULTIPLE files support
     * form-data:
     *   - files[]: (required, can be multiple)
     *   - purpose: AVATAR | CREATOR_FEED | ATHLETE_FEED | BOOKING_DELIVERABLE
     *   - targetId: (optional) booking id, or profile id
     */
    #[Route('/upload', name: 'media_asset_upload', methods: ['POST'])]
    public function upload(Request $req): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        // DEBUG: Log everything we receive
        $contentType = $req->headers->get('Content-Type');
        error_log('Content-Type: ' . $contentType);
        
        // Check if this is a JSON payload (base64 encoded images - handle separately)
        if (str_contains($contentType ?? '', 'application/json')) {
            return $this->handleJsonUpload($req, $user);
        }

        error_log('POST data: ' . json_encode($_POST));
        error_log('Request->get purpose: ' . $req->get('purpose'));
        
        error_log('All files keys: ' . json_encode(array_keys($req->files->all())));
        error_log('All request keys: ' . json_encode(array_keys($req->request->all())));
        
        // Get all files from the request - try multiple keys
        $files = $req->files->get('files'); // Try files[] first
        
        if (!$files) {
            // Fallback: try without brackets (for single file uploads)
            $singleFile = $req->files->get('file');
            if ($singleFile) {
                $files = [$singleFile]; // Convert to array
            }
        }
        
        // If files is not an array, check if it's a single file and convert
        if ($files && !is_array($files)) {
            $files = [$files];
        }
        
        $purpose = (string)(
            $_POST['purpose'] ?? 
            $_REQUEST['purpose'] ?? 
            $req->request->get('purpose') ?? 
            $req->query->get('purpose') ??  // Fallback to query params
            ''
        );

        $targetId = $_POST['targetId'] ?? 
            $_REQUEST['targetId'] ?? 
            $req->request->get('targetId') ?? 
            $req->query->get('targetId') ?? 
            null;

        error_log('Extracted purpose: ' . $purpose);
        error_log('Extracted targetId: ' . ($targetId ?? 'null'));
        // Debug: log what we received
        if (!$files) {
            $allFiles = $req->files->all();
            error_log('No files received. Available keys: ' . json_encode(array_keys($allFiles)));
            
            // Try to get files from any available key
            if (!empty($allFiles)) {
                // Get the first array of files we find
                foreach ($allFiles as $key => $fileOrFiles) {
                    if ($fileOrFiles) {
                        error_log("Found files under key: {$key}");
                        $files = is_array($fileOrFiles) ? $fileOrFiles : [$fileOrFiles];
                        break;
                    }
                }
            }
        }

        if (!$files || !$purpose) {
            return $this->json([
                'message' => 'files and purpose are required',
                'debug' => [
                    'contentType' => $contentType,
                    'receivedFileKeys' => array_keys($req->files->all()),
                    'receivedParams' => array_keys($req->request->all()),
                    'allParams' => $req->request->all(),
                ]
            ], 400);
        }

        // Validate target for specific purposes
        $booking = null;
        $creatorProfile = null;
        $athleteProfile = null;

        switch ($purpose) {
            case MediaAsset::PURPOSE_BOOKING_DELIVERABLE:
                if (!$targetId) {
                    return $this->json(['message' => 'targetId (booking id) required'], 400);
                }
                $booking = $this->em->getRepository(Booking::class)->find($targetId);
                if (!$booking) {
                    return $this->json(['message' => 'Booking not found'], 404);
                }
                if ($booking->getCreator()->getId() !== $user->getId()) {
                    return $this->json(['message' => 'Forbidden'], 403);
                }
                break;

            case MediaAsset::PURPOSE_CREATOR_FEED:
                $creatorProfile = $user->getCreatorProfile();
                if (!$creatorProfile) {
                    return $this->json(['message' => 'No creator profile'], 400);
                }
                break;

            case MediaAsset::PURPOSE_ATHLETE_FEED:
                $athleteProfile = $user->getAthleteProfile();
                if (!$athleteProfile) {
                    return $this->json(['message' => 'No athlete profile'], 400);
                }
                break;
        }

        // Process each file
        $uploadedAssets = [];
        $errors = [];

        foreach ($files as $index => $file) {
            try {
                // Decide R2 prefix based on purpose
                $prefix = match ($purpose) {
                    MediaAsset::PURPOSE_AVATAR => 'avatars',
                    MediaAsset::PURPOSE_BOOKING_DELIVERABLE => "deliverables/{$targetId}",
                    MediaAsset::PURPOSE_CREATOR_FEED => 'creator',
                    MediaAsset::PURPOSE_ATHLETE_FEED => 'athlete',
                    default => 'uploads',
                };

                // Upload to R2
                $put = $this->r2->upload($file, $prefix);
                $contentType = $file->getClientMimeType() ?? 'application/octet-stream';
                $bytes = $file->getSize() ?? 0;

                // Auto-detect media type
                $mediaType = $this->detectMediaType($contentType);

                // Get dimensions and aspect ratio
                $dimensions = $this->getMediaDimensions($file, $contentType);
                $aspectRatio = $this->detectAspectRatio($dimensions['width'], $dimensions['height']);

                // Create asset
                $asset = (new MediaAsset())
                    ->setOwner($user)
                    ->setPurpose($purpose)
                    ->setStorageKey($put['key'])
                    ->setPublicUrl($put['url'] ?: null)
                    ->setFilename($file->getClientOriginalName())
                    ->setContentType($contentType)
                    ->setBytes((int)$bytes)
                    ->setType($mediaType)
                    ->setWidth($dimensions['width'])
                    ->setHeight($dimensions['height'])
                    ->setAspectRatio($aspectRatio);

                // Link to target
                switch ($purpose) {
                    case MediaAsset::PURPOSE_AVATAR:
                        // Only update user photo with the FIRST file for avatar
                        if ($index === 0) {
                            $user->setUserPhoto($put['url'] ?: $put['key']);
                        }
                        break;

                    case MediaAsset::PURPOSE_BOOKING_DELIVERABLE:
                        $asset->setBooking($booking);
                        break;

                    case MediaAsset::PURPOSE_CREATOR_FEED:
                        $asset->setCreatorProfile($creatorProfile);
                        break;

                    case MediaAsset::PURPOSE_ATHLETE_FEED:
                        $asset->setAthleteProfile($athleteProfile);
                        break;
                }

                $this->em->persist($asset);

                $uploadedAssets[] = [
                    'id' => (string)$asset->getId(),
                    'purpose' => $asset->getPurpose(),
                    'key' => $asset->getStorageKey(),
                    'publicUrl' => $asset->getPublicUrl(),
                    'filename' => $asset->getFilename(),
                    'type' => $asset->getType(),
                    'aspectRatio' => $asset->getAspectRatio(),
                    'width' => $asset->getWidth(),
                    'height' => $asset->getHeight(),
                    'contentType' => $asset->getContentType(),
                    'bytes' => $asset->getBytes(),
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Flush all persisted assets at once
        $this->em->flush();

        // Notify via Mercure if deliverables were uploaded
        if ($purpose === MediaAsset::PURPOSE_BOOKING_DELIVERABLE && $booking) {
            $totalFiles = $this->em->getRepository(MediaAsset::class)
                ->count(['booking' => $booking]);
            $this->mercurePublisher->publishDeliverableUploaded($booking, $totalFiles);
        }

        $response = [
            'message' => sprintf('%d file(s) uploaded successfully', count($uploadedAssets)),
            'uploaded' => $uploadedAssets,
            'total' => count($uploadedAssets),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
            $response['failedCount'] = count($errors);
        }

        return $this->json($response, 201);
    }

    /**
     * Register multiple pre-uploaded assets
     * JSON:
     * {
     *   "purpose": "BOOKING_DELIVERABLE",
     *   "targetId": "booking-uuid-or-null",
     *   "assets": [
     *     {
     *       "key": "deliverables/file-abc123.mp4",
     *       "filename": "file.mp4",
     *       "contentType": "video/mp4",
     *       "bytes": 123456789
     *     },
     *     ...
     *   ]
     * }
     */
    #[Route('/register', name: 'media_asset_register', methods: ['POST'])]
    public function register(Request $req): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $data = json_decode((string)$req->getContent(), true) ?? [];
        $purpose = (string)($data['purpose'] ?? '');
        $targetId = $data['targetId'] ?? null;
        $assetsData = $data['assets'] ?? [];

        if (!$purpose || empty($assetsData)) {
            return $this->json(['message' => 'purpose and assets are required'], 400);
        }

        // Validate target for specific purposes
        $booking = null;
        $creatorProfile = null;
        $athleteProfile = null;

        switch ($purpose) {
            case MediaAsset::PURPOSE_BOOKING_DELIVERABLE:
                if (!$targetId) {
                    return $this->json(['message' => 'targetId (booking id) required'], 400);
                }
                $booking = $this->em->getRepository(Booking::class)->find($targetId);
                if (!$booking) {
                    return $this->json(['message' => 'Booking not found'], 404);
                }
                if ($booking->getCreator()->getId() !== $user->getId()) {
                    return $this->json(['message' => 'Forbidden'], 403);
                }
                break;

            case MediaAsset::PURPOSE_CREATOR_FEED:
                $creatorProfile = $user->getCreatorProfile();
                if (!$creatorProfile) {
                    return $this->json(['message' => 'No creator profile'], 400);
                }
                break;

            case MediaAsset::PURPOSE_ATHLETE_FEED:
                $athleteProfile = $user->getAthleteProfile();
                if (!$athleteProfile) {
                    return $this->json(['message' => 'No athlete profile'], 400);
                }
                break;
        }

        // Process each asset
        $registeredAssets = [];
        $errors = [];

        foreach ($assetsData as $index => $assetData) {
            try {
                $key = (string)($assetData['key'] ?? '');
                $filename = (string)($assetData['filename'] ?? 'file');
                $contentType = (string)($assetData['contentType'] ?? 'application/octet-stream');
                $bytes = (int)($assetData['bytes'] ?? 0);

                if (!$key) {
                    throw new \Exception('key is required for each asset');
                }

                $mediaType = $this->detectMediaType($contentType);

                $asset = (new MediaAsset())
                    ->setOwner($user)
                    ->setPurpose($purpose)
                    ->setStorageKey($key)
                    ->setFilename($filename)
                    ->setContentType($contentType)
                    ->setBytes($bytes)
                    ->setType($mediaType)
                    ->setPublicUrl($this->r2->publicUrl($key) ?: null);

                // Link to target
                switch ($purpose) {
                    case MediaAsset::PURPOSE_AVATAR:
                        if ($index === 0) {
                            $user->setUserPhoto($asset->getPublicUrl() ?: $asset->getStorageKey());
                        }
                        break;

                    case MediaAsset::PURPOSE_BOOKING_DELIVERABLE:
                        $asset->setBooking($booking);
                        break;

                    case MediaAsset::PURPOSE_CREATOR_FEED:
                        $asset->setCreatorProfile($creatorProfile);
                        break;

                    case MediaAsset::PURPOSE_ATHLETE_FEED:
                        $asset->setAthleteProfile($athleteProfile);
                        break;
                }

                $this->em->persist($asset);

                $registeredAssets[] = [
                    'id' => (string)$asset->getId(),
                    'key' => $asset->getStorageKey(),
                    'publicUrl' => $asset->getPublicUrl(),
                    'filename' => $asset->getFilename(),
                    'type' => $asset->getType(),
                    'contentType' => $asset->getContentType(),
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'key' => $assetData['key'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Flush all at once
        $this->em->flush();

        // Notify via Mercure
        if ($purpose === MediaAsset::PURPOSE_BOOKING_DELIVERABLE && $booking) {
            $totalFiles = $this->em->getRepository(MediaAsset::class)
                ->count(['booking' => $booking]);
            $this->mercurePublisher->publishDeliverableUploaded($booking, $totalFiles);
        }

        $response = [
            'message' => sprintf('%d asset(s) registered successfully', count($registeredAssets)),
            'registered' => $registeredAssets,
            'total' => count($registeredAssets),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
            $response['failedCount'] = count($errors);
        }

        return $this->json($response, 201);
    }

    /**
     * Get presigned upload URLs for multiple files
     * POST /api/media/presigned-upload
     * 
     * JSON body:
     * {
     *   "purpose": "CREATOR_FEED",
     *   "targetId": "booking-id-optional",
     *   "files": [
     *     {"filename": "video1.mp4", "contentType": "video/mp4"},
     *     {"filename": "image1.jpg", "contentType": "image/jpeg"}
     *   ]
     * }
     */
    #[Route('/presigned-upload', name: 'media_presigned_upload', methods: ['POST'])]
    public function getPresignedUploadUrl(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $purpose = $data['purpose'] ?? '';
        $targetId = $data['targetId'] ?? null;
        $filesData = $data['files'] ?? [];

        if (!$purpose || empty($filesData)) {
            return $this->json([
                'message' => 'purpose and files are required'
            ], 400);
        }

        $allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/quicktime', 'video/webm', 'video/x-matroska',
        ];

        $prefix = match ($purpose) {
            MediaAsset::PURPOSE_AVATAR => 'avatars',
            MediaAsset::PURPOSE_BOOKING_DELIVERABLE => "deliverables/{$targetId}",
            MediaAsset::PURPOSE_CREATOR_FEED => 'creator',
            MediaAsset::PURPOSE_ATHLETE_FEED => 'athlete',
            default => 'uploads',
        };

        $presignedUrls = [];
        $errors = [];

        foreach ($filesData as $fileData) {
            $filename = $fileData['filename'] ?? '';
            $contentType = $fileData['contentType'] ?? 'application/octet-stream';

            if (!$filename) {
                $errors[] = ['error' => 'filename is required for each file'];
                continue;
            }

            if (!in_array($contentType, $allowedTypes, true)) {
                $errors[] = [
                    'filename' => $filename,
                    'error' => 'File type not supported'
                ];
                continue;
            }

            $uuid = Uuid::v4()->toRfc4122();
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $key = "{$prefix}/{$uuid}.{$extension}";

            $uploadUrl = $this->r2->presignedPut($key, $contentType, 3600);
            $publicUrl = $this->r2->publicUrl($key);

            $presignedUrls[] = [
                'filename' => $filename,
                'uploadUrl' => $uploadUrl,
                'key' => $key,
                'publicUrl' => $publicUrl,
                'contentType' => $contentType,
            ];
        }

        return $this->json([
            'urls' => $presignedUrls,
            'expiresIn' => 3600,
            'errors' => $errors,
        ]);
    }

    /**
     * Auto-detect media type (IMAGE or VIDEO) from MIME type
     */
    private function detectMediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return MediaAsset::TYPE_IMAGE;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return MediaAsset::TYPE_VIDEO;
        }

        $extension = strtolower(pathinfo($mimeType, PATHINFO_EXTENSION));

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'svg', 'bmp', 'tiff'];
        if (in_array($extension, $imageExtensions, true)) {
            return MediaAsset::TYPE_IMAGE;
        }

        $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', 'flv', 'wmv', '3gp'];
        if (in_array($extension, $videoExtensions, true)) {
            return MediaAsset::TYPE_VIDEO;
        }

        return MediaAsset::TYPE_IMAGE;
    }

    /**
     * Get media dimensions (width x height)
     */
    private function getMediaDimensions($file, string $contentType): array
    {
        $width = 0;
        $height = 0;

        try {
            if (str_starts_with($contentType, 'image/')) {
                $imageInfo = @getimagesize($file->getPathname());
                if ($imageInfo !== false) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                }
            }
        } catch (\Exception $e) {
            // If dimensions can't be read, return 0,0
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Auto-detect aspect ratio from dimensions
     */
    private function detectAspectRatio(int $width, int $height): string
    {
        if ($width === 0 || $height === 0) {
            return '1:1';
        }

        $ratio = $width / $height;

        if ($ratio >= 0.95 && $ratio <= 1.05) {
            return '1:1';
        }

        if ($ratio >= 0.75 && $ratio <= 0.85) {
            return '4:5';
        }

        if ($ratio >= 0.5 && $ratio <= 0.6) {
            return '9:16';
        }

        if ($ratio >= 1.7 && $ratio <= 1.85) {
            return '16:9';
        }

        if ($ratio >= 2.2 && $ratio <= 2.5) {
            return '21:9';
        }

        if ($ratio > 1.4) {
            return '16:9';
        } else if ($ratio < 0.7) {
            return '9:16';
        } else {
            return '1:1';
        }
    }

    /**
     * Handle JSON-based upload (base64 encoded images from React Native)
     * This is a fallback for clients that send base64 encoded images
     */
    private function handleJsonUpload(Request $req, User $user): JsonResponse
    {
        $data = json_decode($req->getContent(), true);
        
        if (!$data) {
            return $this->json(['message' => 'Invalid JSON'], 400);
        }

        $purpose = (string)($data['purpose'] ?? '');
        $targetId = $data['targetId'] ?? null;
        $files = $data['files'] ?? [];

        if (empty($files) || !$purpose) {
            return $this->json(['message' => 'files and purpose are required in JSON payload'], 400);
        }

        // Validate target
        $booking = null;
        $creatorProfile = null;
        $athleteProfile = null;

        switch ($purpose) {
            case MediaAsset::PURPOSE_BOOKING_DELIVERABLE:
                if (!$targetId) {
                    return $this->json(['message' => 'targetId required'], 400);
                }
                $booking = $this->em->getRepository(Booking::class)->find($targetId);
                if (!$booking || $booking->getCreator()->getId() !== $user->getId()) {
                    return $this->json(['message' => 'Booking not found or forbidden'], 403);
                }
                break;

            case MediaAsset::PURPOSE_CREATOR_FEED:
                $creatorProfile = $user->getCreatorProfile();
                if (!$creatorProfile) {
                    return $this->json(['message' => 'No creator profile'], 400);
                }
                break;

            case MediaAsset::PURPOSE_ATHLETE_FEED:
                $athleteProfile = $user->getAthleteProfile();
                if (!$athleteProfile) {
                    return $this->json(['message' => 'No athlete profile'], 400);
                }
                break;
        }

        $uploadedAssets = [];
        $errors = [];

        foreach ($files as $index => $fileData) {
            try {
                $base64Data = $fileData['data'] ?? $fileData['base64'] ?? '';
                $filename = $fileData['name'] ?? "file_{$index}";
                $mimeType = $fileData['type'] ?? 'application/octet-stream';

                if (empty($base64Data)) {
                    throw new \Exception('No base64 data provided');
                }

                // Decode base64
                $decoded = base64_decode($base64Data, true);
                if ($decoded === false) {
                    throw new \Exception('Invalid base64 encoding');
                }

                // Create temp file
                $tempFile = tempnam(sys_get_temp_dir(), 'upload_');
                file_put_contents($tempFile, $decoded);

                // Create UploadedFile
                $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
                    $tempFile,
                    $filename,
                    $mimeType,
                    null,
                    true // test mode
                );

                // Use existing upload logic
                $prefix = match ($purpose) {
                    MediaAsset::PURPOSE_AVATAR => 'avatars',
                    MediaAsset::PURPOSE_BOOKING_DELIVERABLE => "deliverables/{$targetId}",
                    MediaAsset::PURPOSE_CREATOR_FEED => 'creator',
                    MediaAsset::PURPOSE_ATHLETE_FEED => 'athlete',
                    default => 'uploads',
                };

                $put = $this->r2->upload($uploadedFile, $prefix);
                $mediaType = $this->detectMediaType($mimeType);
                $dimensions = $this->getMediaDimensions($uploadedFile, $mimeType);
                $aspectRatio = $this->detectAspectRatio($dimensions['width'], $dimensions['height']);

                $asset = (new MediaAsset())
                    ->setOwner($user)
                    ->setPurpose($purpose)
                    ->setStorageKey($put['key'])
                    ->setPublicUrl($put['url'] ?: null)
                    ->setFilename($filename)
                    ->setContentType($mimeType)
                    ->setBytes(strlen($decoded))
                    ->setType($mediaType)
                    ->setWidth($dimensions['width'])
                    ->setHeight($dimensions['height'])
                    ->setAspectRatio($aspectRatio);

                // Link to target
                if ($booking) $asset->setBooking($booking);
                if ($creatorProfile) $asset->setCreatorProfile($creatorProfile);
                if ($athleteProfile) $asset->setAthleteProfile($athleteProfile);

                $this->em->persist($asset);

                $uploadedAssets[] = [
                    'id' => (string)$asset->getId(),
                    'publicUrl' => $asset->getPublicUrl(),
                    'filename' => $asset->getFilename(),
                ];

                // Clean up temp file
                @unlink($tempFile);
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->em->flush();

        if ($purpose === MediaAsset::PURPOSE_BOOKING_DELIVERABLE && $booking) {
            $totalFiles = $this->em->getRepository(MediaAsset::class)->count(['booking' => $booking]);
            $this->mercurePublisher->publishDeliverableUploaded($booking, $totalFiles);
        }

        return $this->json([
            'message' => sprintf('%d file(s) uploaded', count($uploadedAssets)),
            'uploaded' => $uploadedAssets,
            'total' => count($uploadedAssets),
            'errors' => $errors,
        ], 201);
    }
}