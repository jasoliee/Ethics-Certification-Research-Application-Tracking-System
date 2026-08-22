<?php

namespace App\Services\Certificates;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Generates the deterministic first-party QR fallback without any network request. */
class DefaultCertificateQrService
{
    public const URL = 'https://kld.edu.ph/ovprii.php';

    public const STORED_PATH = 'settings/certificate-qr/official-ovprii-fallback.png';

    /** @return array{path: string, stored_path: string, sha256: string, width: int, height: int} */
    public function asset(): array
    {
        $bytes = $this->pngBytes();
        $disk = Storage::disk('local');
        $hash = hash('sha256', $bytes);
        if (! $disk->exists(self::STORED_PATH)
            || ! hash_equals($hash, (string) hash_file('sha256', $disk->path(self::STORED_PATH)))) {
            if (! $disk->put(self::STORED_PATH, $bytes)) {
                throw new RuntimeException('The official certificate QR fallback could not be stored privately.');
            }
        }

        return [
            'path' => $disk->path(self::STORED_PATH),
            'stored_path' => self::STORED_PATH,
            'sha256' => $hash,
            'width' => 296,
            'height' => 296,
        ];
    }

    private function pngBytes(): string
    {
        $modules = $this->matrix(self::URL);
        $scale = 8;
        $border = 4;
        $size = (count($modules) + ($border * 2)) * $scale;
        $image = imagecreatetruecolor($size, $size);
        if ($image === false) {
            throw new RuntimeException('The certificate QR fallback could not be rendered.');
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);
        foreach ($modules as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    imagefilledrectangle(
                        $image,
                        ($x + $border) * $scale,
                        ($y + $border) * $scale,
                        (($x + $border + 1) * $scale) - 1,
                        (($y + $border + 1) * $scale) - 1,
                        $black,
                    );
                }
            }
        }
        ob_start();
        imagepng($image, null, 9, PNG_NO_FILTER);
        $bytes = ob_get_clean();
        imagedestroy($image);
        if (! is_string($bytes)) {
            throw new RuntimeException('The certificate QR fallback could not be encoded.');
        }

        return $bytes;
    }

    /** @return array<int, array<int, bool>> */
    private function matrix(string $text): array
    {
        $version = 3;
        $size = 17 + (4 * $version);
        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $function = array_fill(0, $size, array_fill(0, $size, false));
        $setFunction = function (int $x, int $y, bool $dark) use (&$modules, &$function, $size): void {
            if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) {
                $modules[$y][$x] = $dark;
                $function[$y][$x] = true;
            }
        };
        $finder = function (int $centerX, int $centerY) use ($setFunction): void {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $distance = max(abs($dx), abs($dy));
                    $setFunction($centerX + $dx, $centerY + $dy, $distance !== 2 && $distance !== 4);
                }
            }
        };
        $finder(3, 3);
        $finder($size - 4, 3);
        $finder(3, $size - 4);
        for ($i = 8; $i < $size - 8; $i++) {
            $setFunction(6, $i, $i % 2 === 0);
            $setFunction($i, 6, $i % 2 === 0);
        }
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $setFunction(22 + $dx, 22 + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $setFunction(8, $i, false);
                $setFunction($i, 8, false);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $setFunction($size - 1 - $i, 8, false);
        }
        for ($i = 0; $i < 7; $i++) {
            $setFunction(8, $size - 1 - $i, false);
        }
        $setFunction(8, $size - 8, true);

        $data = $this->dataCodewords($text);
        $codewords = [...$data, ...$this->reedSolomon($data, 15)];
        $bits = [];
        foreach ($codewords as $byte) {
            for ($bit = 7; $bit >= 0; $bit--) {
                $bits[] = (($byte >> $bit) & 1) !== 0;
            }
        }
        $bitIndex = 0;
        $upward = true;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }
            for ($vertical = 0; $vertical < $size; $vertical++) {
                $y = $upward ? $size - 1 - $vertical : $vertical;
                for ($offset = 0; $offset < 2; $offset++) {
                    $x = $right - $offset;
                    if ($function[$y][$x]) {
                        continue;
                    }
                    $value = $bits[$bitIndex++] ?? false;
                    $mask = (($x + $y) % 2) === 0;
                    $modules[$y][$x] = $value !== $mask;
                }
            }
            $upward = ! $upward;
        }

        $format = $this->formatBits(0);
        for ($i = 0; $i <= 5; $i++) {
            $modules[$i][8] = (($format >> $i) & 1) !== 0;
        }
        $modules[7][8] = (($format >> 6) & 1) !== 0;
        $modules[8][8] = (($format >> 7) & 1) !== 0;
        $modules[8][7] = (($format >> 8) & 1) !== 0;
        for ($i = 9; $i < 15; $i++) {
            $modules[8][14 - $i] = (($format >> $i) & 1) !== 0;
        }
        for ($i = 0; $i < 8; $i++) {
            $modules[8][$size - 1 - $i] = (($format >> $i) & 1) !== 0;
        }
        for ($i = 8; $i < 15; $i++) {
            $modules[$size - 15 + $i][8] = (($format >> $i) & 1) !== 0;
        }
        $modules[$size - 8][8] = true;

        return $modules;
    }

    /** @return array<int, int> */
    private function dataCodewords(string $text): array
    {
        $bits = [false, true, false, false]; // byte mode
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ((strlen($text) >> $i) & 1) !== 0;
        }
        foreach (str_split($text) as $character) {
            $byte = ord($character);
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = (($byte >> $i) & 1) !== 0;
            }
        }
        for ($i = 0; $i < 4 && count($bits) < 440; $i++) {
            $bits[] = false;
        }
        while (count($bits) % 8 !== 0) {
            $bits[] = false;
        }
        $bytes = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $value = 0;
            foreach ($chunk as $bit) {
                $value = ($value << 1) | ($bit ? 1 : 0);
            }
            $bytes[] = $value;
        }
        for ($pad = 0; count($bytes) < 55; $pad++) {
            $bytes[] = $pad % 2 === 0 ? 0xEC : 0x11;
        }

        return $bytes;
    }

    /** @param array<int, int> $data @return array<int, int> */
    private function reedSolomon(array $data, int $degree): array
    {
        $generator = [1];
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= $this->gfMultiply($coefficient, $root);
            }
            $generator = $next;
            $root = $this->gfMultiply($root, 2);
        }
        $remainder = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $remainder[$i] ^= $this->gfMultiply($generator[$i + 1], $factor);
            }
        }

        return $remainder;
    }

    private function gfMultiply(int $x, int $y): int
    {
        $result = 0;
        for ($i = 0; $i < 8; $i++) {
            if (($y & 1) !== 0) {
                $result ^= $x;
            }
            $carry = ($x & 0x80) !== 0;
            $x = ($x << 1) & 0xFF;
            if ($carry) {
                $x ^= 0x1D;
            }
            $y >>= 1;
        }

        return $result;
    }

    private function formatBits(int $mask): int
    {
        $data = (1 << 3) | $mask; // error correction level L
        $remainder = $data << 10;
        for ($bit = 14; $bit >= 10; $bit--) {
            if ((($remainder >> $bit) & 1) !== 0) {
                $remainder ^= 0x537 << ($bit - 10);
            }
        }

        return (($data << 10) | $remainder) ^ 0x5412;
    }
}
