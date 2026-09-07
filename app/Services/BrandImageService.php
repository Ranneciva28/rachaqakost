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

        $prepared = null;

        try {
            $prepared = $this->prepare($source);

            return [
                $this->variant($prepared, 'LOGO', 512, 512, 24, $file->getClientOriginalName()),
                $this->variant($prepared, 'FAVICON', 96, 96, 5, $file->getClientOriginalName()),
            ];
        } finally {
            if ($prepared !== null) {
                imagedestroy($prepared);
            }
            imagedestroy($source);
        }
    }

    /**
     * Work on a bounded copy so even a 4096px upload cannot exhaust PHP memory.
     * A light background is removed only when it is connected to the image edge;
     * enclosed white details in the actual logo are therefore preserved.
     */
    private function prepare($source)
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, 1024 / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));

        $working = $this->transparentCanvas($width, $height);
        imagecopyresampled(
            $working,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight
        );

        $this->removeLightEdgeBackground($working);
        $bounds = $this->visibleBounds($working);

        if ($bounds === null) {
            imagedestroy($working);
            throw ValidationException::withMessages([
                'logo' => 'Logo tidak memiliki bagian gambar yang dapat ditampilkan.',
            ]);
        }

        $cropped = imagecrop($working, $bounds);
        imagedestroy($working);

        if ($cropped === false) {
            throw ValidationException::withMessages(['logo' => 'Area logo gagal disesuaikan.']);
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);

        return $cropped;
    }

    private function removeLightEdgeBackground($image): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $corners = [
            $this->rgbaAt($image, 0, 0),
            $this->rgbaAt($image, $width - 1, 0),
            $this->rgbaAt($image, 0, $height - 1),
            $this->rgbaAt($image, $width - 1, $height - 1),
        ];
        $lightCorners = array_values(array_filter($corners, fn (array $color) => $this->isLightNeutral($color)));

        // Transparent logos and deliberately coloured tiles must remain untouched.
        if (count($lightCorners) < 3) {
            return;
        }

        $background = [
            'r' => (int) round(array_sum(array_column($lightCorners, 'r')) / count($lightCorners)),
            'g' => (int) round(array_sum(array_column($lightCorners, 'g')) / count($lightCorners)),
            'b' => (int) round(array_sum(array_column($lightCorners, 'b')) / count($lightCorners)),
        ];
        $visited = str_repeat("\0", $width * $height);
        $queue = new \SplQueue();
        $enqueue = static function (int $index) use (&$visited, $queue): void {
            if ($visited[$index] !== "\0") {
                return;
            }
            $visited[$index] = "\1";
            $queue->enqueue($index);
        };

        for ($x = 0; $x < $width; $x++) {
            $enqueue($x);
            if ($height > 1) {
                $enqueue((($height - 1) * $width) + $x);
            }
        }
        for ($y = 1; $y < $height - 1; $y++) {
            $enqueue($y * $width);
            if ($width > 1) {
                $enqueue(($y * $width) + $width - 1);
            }
        }

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);

        while (! $queue->isEmpty()) {
            $index = $queue->dequeue();
            $x = $index % $width;
            $y = intdiv($index, $width);
            $color = $this->rgbaAt($image, $x, $y);

            if (! $this->matchesBackground($color, $background)) {
                continue;
            }

            imagesetpixel($image, $x, $y, $transparent);

            if ($x > 0) {
                $enqueue($index - 1);
            }
            if ($x + 1 < $width) {
                $enqueue($index + 1);
            }
            if ($y > 0) {
                $enqueue($index - $width);
            }
            if ($y + 1 < $height) {
                $enqueue($index + $width);
            }
        }
    }

    private function visibleBounds($image): ?array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $left = $width;
        $top = $height;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($this->rgbaAt($image, $x, $y)['a'] >= 120) {
                    continue;
                }
                $left = min($left, $x);
                $top = min($top, $y);
                $right = max($right, $x);
                $bottom = max($bottom, $y);
            }
        }

        return $right < 0 ? null : [
            'x' => $left,
            'y' => $top,
            'width' => $right - $left + 1,
            'height' => $bottom - $top + 1,
        ];
    }

    private function rgbaAt($image, int $x, int $y): array
    {
        $rgba = imagecolorat($image, $x, $y);

        return [
            'r' => ($rgba >> 16) & 0xff,
            'g' => ($rgba >> 8) & 0xff,
            'b' => $rgba & 0xff,
            'a' => ($rgba >> 24) & 0x7f,
        ];
    }

    private function isLightNeutral(array $color): bool
    {
        $channels = [$color['r'], $color['g'], $color['b']];

        return $color['a'] < 120
            && min($channels) >= 220
            && max($channels) - min($channels) <= 35;
    }

    private function matchesBackground(array $color, array $background): bool
    {
        if ($color['a'] >= 120) {
            return false;
        }

        $channels = [$color['r'], $color['g'], $color['b']];
        $distance = (($color['r'] - $background['r']) ** 2)
            + (($color['g'] - $background['g']) ** 2)
            + (($color['b'] - $background['b']) ** 2);

        return min($channels) >= 200
            && max($channels) - min($channels) <= 55
            && $distance <= 75 ** 2;
    }

    private function transparentCanvas(int $width, int $height)
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        return $canvas;
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

        $canvas = $this->transparentCanvas($width, $height);
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
