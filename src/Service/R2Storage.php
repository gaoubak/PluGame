<?php

// src/Service/R2Storage.php
namespace App\Service;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class R2Storage
{
    private S3Client $client;
    private string $bucket;
    private ?string $publicBaseUrl;
    private SluggerInterface $slugger;

    public function __construct(
        string $accountId,
        string $accessKey,
        string $secretKey,
        string $bucket,
        ?string $publicBaseUrl,
        SluggerInterface $slugger
    ) {
        // Cloudflare R2 S3-compatible endpoint
        $endpoint = sprintf('https://%s.r2.cloudflarestorage.com', $accountId);

        $this->client = new S3Client([
            'version'                 => 'latest',
            'region'                  => 'auto',               // required by R2
            'endpoint'                => $endpoint,
            'use_path_style_endpoint' => true,                 // R2 works well with path-style
            'credentials'             => [
                'key'    => $accessKey,
                'secret' => $secretKey,
            ],
        ]);

        $this->bucket        = $bucket;
        $this->publicBaseUrl = $publicBaseUrl ?: null; // empty => null
        $this->slugger       = $slugger;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    private function makeKey(string $prefix, string $originalName): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $ext  = pathinfo($originalName, PATHINFO_EXTENSION);
        $safe = $this->slugger->slug($name)->lower()->toString() ?: 'file';
        $ext  = $ext ? '.' . strtolower($ext) : '';
        $pre  = trim($prefix, '/');

        return ($pre ? $pre . '/' : '') . $safe . '-' . bin2hex(random_bytes(6)) . $ext;
    }

    /**
     * Server-side upload (Symfony receives file; we upload to R2).
     * @return array{key:string,url:string}
     */
    public function upload(UploadedFile $file, string $prefix = 'uploads'): array
    {
        $key  = $this->makeKey($prefix, $file->getClientOriginalName());
        $mime = $file->getClientMimeType() ?? 'application/octet-stream';

        $this->client->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'Body'        => fopen($file->getRealPath(), 'rb'),
            'ContentType' => $mime,
        ]);

        return ['key' => $key, 'url' => $this->publicUrl($key)];
    }

    /** If a public CDN/domain is configured, return a public URL; else return empty string. */
    public function publicUrl(string $key): string
    {
        if ($this->publicBaseUrl) {
            return rtrim($this->publicBaseUrl, '/') . '/' . ltrim($key, '/');
        }
        return '';
    }

    /** Time-limited GET (e.g., for private buckets) */
    public function presignedGet(string $key, int $ttlSeconds = 86400): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
        $req = $this->client->createPresignedRequest($cmd, "+{$ttlSeconds} seconds");
        /** @var UriInterface $uri */
        $uri = $req->getUri();
        return (string) $uri;
    }

    /**
     * Pre-signed PUT so the client can upload directly to R2.
     * Frontend: PUT the file to "uploadUrl" with header "Content-Type: contentType".
     * Returns a publicUrl only if you configured a public domain.
     */
    public function presignedPut(
        string $filename,
        string $contentType,
        string $prefix = 'uploads',
        int $ttlSeconds = 3600
    ): array {
        $key = $this->makeKey($prefix, $filename);

        $cmd = $this->client->getCommand('PutObject', [
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'ContentType' => $contentType,
        ]);
        $req = $this->client->createPresignedRequest($cmd, "+{$ttlSeconds} seconds");
        /** @var UriInterface $uri */
        $uri = $req->getUri();

        return [
            'key'         => $key,
            'uploadUrl'   => (string) $uri,
            'contentType' => $contentType,
            'publicUrl'   => $this->publicUrl($key),
        ];
    }

    public function delete(string $key): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
    }

    /**
     * Upload a local file (from file path) to R2.
     * Used for server-side file uploads (e.g., generated ZIPs).
     *
     * @param string $localPath Absolute path to the local file
     * @param string $key The S3 key (path) where the file should be stored
     * @return string The public URL of the uploaded file (or empty if no CDN configured)
     */
    public function uploadFile(string $localPath, string $key): string
    {
        if (!file_exists($localPath)) {
            throw new \InvalidArgumentException("File not found: {$localPath}");
        }

        $contentType = mime_content_type($localPath) ?: 'application/octet-stream';

        $this->client->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'Body'        => fopen($localPath, 'rb'),
            'ContentType' => $contentType,
        ]);

        return $this->publicUrl($key);
    }

    /**
     * Generate a signed URL with custom expiration.
     *
     * @param string $key The S3 key (path) of the file
     * @param string $expiration ISO 8601 date string (e.g., "2025-12-01T10:00:00Z")
     * @return string Presigned download URL
     */
    public function generateSignedUrl(string $key, string $expiration): string
    {
        try {
            $expiresAt = new \DateTimeImmutable($expiration);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Invalid expiration format: {$expiration}. Expected ISO 8601 format.");
        }

        $ttl = $expiresAt->getTimestamp() - time();

        if ($ttl <= 0) {
            throw new \InvalidArgumentException("Expiration must be in the future");
        }

        return $this->presignedGet($key, $ttl);
    }

    /**
     * Get file stream for streaming downloads.
     * Returns a resource that can be read and sent to the client.
     *
     * @param string $key The S3 key (path) of the file
     * @return resource Stream resource
     */
    public function getStream(string $key)
    {
        $result = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);

        // Detach the stream from the SDK wrapper to get a raw PHP stream
        return $result['Body']->detach();
    }

    /**
     * Get file metadata without downloading the file.
     * Useful for getting Content-Length, Content-Type, etc.
     *
     * @param string $key The S3 key (path) of the file
     * @return array{ContentLength: int, ContentType: string, LastModified: ?\DateTimeInterface}
     */
    public function head(string $key): array
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);

            return [
                'ContentLength' => $result['ContentLength'] ?? 0,
                'ContentType'   => $result['ContentType'] ?? 'application/octet-stream',
                'LastModified'  => $result['LastModified'] ?? null,
            ];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NotFound') {
                throw new \RuntimeException("File not found: {$key}", 404, $e);
            }
            throw $e;
        }
    }
}
