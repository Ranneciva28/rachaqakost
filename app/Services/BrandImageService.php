<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BrandImageService
{
    public function variants(UploadedFile $file): array
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages([
                'logo' => 'Ekstensi PHP GD belum aktif. Aktifkan lsphp83-gd pada server.',
            ]);
        }

        $binary = file_get_contents($file->getRealPath());
        $source = $binary === false ? false : @imagecreatefromstring($binary);

        if ($source === false) {
            throw ValidationException::withMessages(['logo' => 'File logo tidak dapat diproses.']);
        }

        try {
            return [
                $this->variant($source, 'LOGO', 512, 512, 36, $file->getClientOriginalName()),
                $this->variant($source, 'FAVICON', 96, 96, 8, $file->getClientOriginalName()),
            ];
        } finally {
            imagedestroy($source);
        }
    }

    private function variant($source, string $kind, int $width, int $height, int $padding, string $originalName): array
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $availableWidth = $width - ($padding * 2);
        $availableHeight = $height - ($padding * 2);
        $scale = min($availableWidth / $sourceWidth, $availableHeight / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $targetX = (int) floor(($width - $targetWidth) / 2);
        $targetY = (int) floor(($height - $targetHeight) / 2);

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $source, $targetX, $targetY, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagepng($canvas, null, 9);
        $contents = ob_get_clean();
        imagedestroy($canvas);

        if ($contents === false) {
            throw ValidationException::withMessages(['logo' => 'Versi logo gagal dibuat.']);
        }

        $baseName = pathinfo($originalName, PATHINFO_FILENAME) ?: 'rachaqakost-logo';

        return [
            'kind' => $kind,
            'original_name' => $baseName.'-'.strtolower($kind).'.png',
            'mime_type' => 'image/png',
            'size' => strlen($contents),
            'contents' => $contents,
            'position' => 0,
        ];
    }
}
