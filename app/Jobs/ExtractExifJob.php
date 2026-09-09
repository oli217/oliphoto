<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Statamic\Facades\Asset;

class ExtractExifJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $assetId) {}

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            return;
        }

        $path = $asset->resolvedPath();

        if (! is_file($path)) {
            return;
        }

        $exif = @exif_read_data($path, 'ANY_TAG', false);

        if (! $exif) {
            return;
        }

        $data = [
            'exif_camera'        => $this->camera($exif),
            'exif_lens'          => $this->lens($exif),
            'exif_focal_length'  => $this->focalLength($exif),
            'exif_aperture'      => $this->aperture($exif),
            'exif_shutter_speed' => $this->shutterSpeed($exif),
            'exif_iso'           => $this->iso($exif),
            'exif_date_taken'    => $this->dateTaken($exif),
            'exif_width'         => $exif['COMPUTED']['Width'] ?? null,
            'exif_height'        => $exif['COMPUTED']['Height'] ?? null,
        ];

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $asset->set($key, $value);
            }
        }

        $asset->save();
    }

    private function camera(array $exif): ?string
    {
        $make  = trim($exif['Make'] ?? '');
        $model = trim($exif['Model'] ?? '');

        if (! $model) {
            return $make ?: null;
        }

        if ($make && ! str_starts_with($model, $make)) {
            return "{$make} {$model}";
        }

        return $model;
    }

    private function lens(array $exif): ?string
    {
        return trim(
            $exif['LensModel']
            ?? $exif['UndefinedTag:0xA434']
            ?? $exif['Exif_IFD_Pointer']['LensModel']
            ?? ''
        ) ?: null;
    }

    private function focalLength(array $exif): ?string
    {
        $raw = $exif['FocalLength'] ?? $exif['FocalLengthIn35mmFilm'] ?? null;

        if ($raw === null) {
            return null;
        }

        $mm = $this->rational($raw);

        return $mm !== null ? round($mm) . ' mm' : null;
    }

    private function aperture(array $exif): ?string
    {
        $computed = $exif['COMPUTED']['ApertureFNumber'] ?? null;

        if ($computed) {
            return $computed;
        }

        $raw = $exif['FNumber'] ?? $exif['ApertureValue'] ?? null;

        if ($raw === null) {
            return null;
        }

        $f = $this->rational($raw);

        return $f !== null ? 'f/' . round($f, 1) : null;
    }

    private function shutterSpeed(array $exif): ?string
    {
        $raw = $exif['ExposureTime'] ?? null;

        if ($raw === null) {
            return null;
        }

        if (str_contains($raw, '/')) {
            [$num, $den] = explode('/', $raw, 2);
            $den = (int) $den;
            $num = (int) $num;

            if ($den === 0) {
                return null;
            }

            if ($den === 1) {
                return "{$num}s";
            }

            $gcd = $this->gcd($num, $den);

            return ($num / $gcd) . '/' . ($den / $gcd) . 's';
        }

        return $raw . 's';
    }

    private function iso(array $exif): ?string
    {
        $iso = $exif['ISOSpeedRatings'] ?? null;

        if (is_array($iso)) {
            $iso = $iso[0] ?? null;
        }

        return $iso !== null ? 'ISO ' . $iso : null;
    }

    private function dateTaken(array $exif): ?string
    {
        $raw = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;

        if (! $raw) {
            return null;
        }

        return str_replace(':', '-', substr($raw, 0, 10)) . substr($raw, 10);
    }

    private function rational(string $value): ?float
    {
        if (str_contains($value, '/')) {
            [$num, $den] = explode('/', $value, 2);
            $den = (float) $den;

            return $den != 0 ? round((float) $num / $den, 4) : null;
        }

        return (float) $value;
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}
