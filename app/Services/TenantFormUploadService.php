<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class TenantFormUploadService
{
    private const MAX_IMAGE_EDGE = 2400;

    public function prepare(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());
        throw_if($contents === false, RuntimeException::class, 'File upload tidak dapat dibaca.');

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $name = $file->getClientOriginalName();

        if ($mime !== 'application/pdf' && extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            [$contents, $mime, $name] = $this->optimizeImage($contents, $mime, $name);
        }

        return [
            'original_name' => $name,
            'mime_type' => $mime,
            'size' => strlen($contents),
            'contents' => $contents,
        ];
    }

    private function optimizeImage(string $contents, string $originalMime, string $originalName): array
    {
        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return [$contents, $originalMime, $originalName];
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, self::MAX_IMAGE_EDGE / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            $white = imagecolorallocate($target, 255, 255, 255);
            imagefill($target, 0, 0, $white);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagejpeg($target, null, 85);
            $optimized = ob_get_clean();
            imagedestroy($target);

            if (! is_string($optimized) || $optimized === '') {
                throw new RuntimeException('Foto lampiran gagal diproses.');
            }

            $baseName = pathinfo($originalName, PATHINFO_FILENAME) ?: 'lampiran';
            return [$optimized, 'image/jpeg', $baseName.'.jpg'];
        } finally {
            imagedestroy($source);
        }
    }
}
