<?php

// src/Service/R2Storage.php
namespace App\Service;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
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
}
