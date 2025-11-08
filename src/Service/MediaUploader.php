<?php

// src/Service/MediaUploader.php
namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class MediaUploader
{
    public function __construct(
        private readonly string $targetDir,        // parameter: media_upload_dir
        private readonly string $publicPrefix,     // parameter: media_public_prefix
        private readonly SluggerInterface $slugger
    ) {
    }

    /** @return array{relative:string, publicUrl:string, filename:string} */
    public function upload(UploadedFile $file): array
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safe     = $this->slugger->slug($original)->lower()->toString();
        $ext      = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';

        // Make unique filename
        $filename = sprintf('%s-%s.%s', $safe ?: 'asset', uniqid('', true), $ext);

        // Move to target dir
        $file->move($this->targetDir, $filename);

        $relative  = $this->publicPrefix . '/' . $filename;     // e.g. /uploads/media/xxx.mp4
        $publicUrl = $relative; // served by Nginx from public/

        return ['relative' => $relative, 'publicUrl' => $publicUrl, 'filename' => $filename];
    }
}
