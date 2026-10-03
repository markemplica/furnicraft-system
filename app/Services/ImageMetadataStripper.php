<?php

namespace App\Services;

use RuntimeException;

class ImageMetadataStripper
{
    public function strip(string $bytes, string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => $this->stripJpeg($bytes),
            'image/png' => $this->stripPng($bytes),
            'image/webp' => $this->stripWebp($bytes),
            default => throw new RuntimeException('Unsupported room image format.'),
        };
    }

    private function stripJpeg(string $bytes): string
    {
        if (! str_starts_with($bytes, "\xFF\xD8")) {
            throw new RuntimeException('Invalid JPEG image.');
        }
        $output = substr($bytes, 0, 2);
        $offset = 2;
        $length = strlen($bytes);

        while ($offset < $length) {
            if (ord($bytes[$offset]) !== 0xFF) {
                throw new RuntimeException('Invalid JPEG segment.');
            }
            $markerStart = $offset;
            while ($offset < $length && ord($bytes[$offset]) === 0xFF) $offset++;
            if ($offset >= $length) break;
            $marker = ord($bytes[$offset++]);

            if ($marker === 0xDA) {
                if ($offset + 2 > $length) throw new RuntimeException('Incomplete JPEG scan header.');
                $segmentLength = unpack('n', substr($bytes, $offset, 2))[1];
                if ($segmentLength < 2 || $offset + $segmentLength > $length) throw new RuntimeException('Invalid JPEG scan header.');
                $scanStart = $offset + $segmentLength;
                $output .= substr($bytes, $markerStart, $scanStart - $markerStart);
                $scanOffset = $scanStart;
                while ($scanOffset < $length) {
                    if (ord($bytes[$scanOffset]) !== 0xFF) {
                        $scanOffset++;
                        continue;
                    }
                    $scanMarkerStart = $scanOffset;
                    while ($scanOffset < $length && ord($bytes[$scanOffset]) === 0xFF) $scanOffset++;
                    if ($scanOffset >= $length) break;
                    $scanMarker = ord($bytes[$scanOffset]);
                    if ($scanMarker === 0x00 || in_array($scanMarker, range(0xD0, 0xD7), true)) {
                        $scanOffset++;
                        continue;
                    }
                    $output .= substr($bytes, $scanStart, $scanMarkerStart - $scanStart);
                    $offset = $scanMarkerStart;
                    continue 2;
                }
                $output .= substr($bytes, $scanStart);
                return $output;
            }
            if ($marker === 0xD9) {
                return $output."\xFF\xD9";
            }
            if (in_array($marker, [0xD8, 0x01, ...range(0xD0, 0xD7)], true)) {
                $segment = substr($bytes, $markerStart, $offset - $markerStart);
                $output .= $segment;
                continue;
            }
            if ($offset + 2 > $length) throw new RuntimeException('Incomplete JPEG segment.');
            $segmentLength = unpack('n', substr($bytes, $offset, 2))[1];
            if ($segmentLength < 2 || $offset + $segmentLength > $length) throw new RuntimeException('Invalid JPEG segment length.');
            $end = $offset + $segmentLength;
            if (! in_array($marker, [0xE1, 0xED, 0xFE], true)) {
                $output .= substr($bytes, $markerStart, $end - $markerStart);
            }
            $offset = $end;
        }

        return $output;
    }

    private function stripPng(string $bytes): string
    {
        if (! str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) throw new RuntimeException('Invalid PNG image.');
        $output = substr($bytes, 0, 8);
        $offset = 8;
        $length = strlen($bytes);

        while ($offset + 12 <= $length) {
            $chunkLength = unpack('N', substr($bytes, $offset, 4))[1];
            $end = $offset + 12 + $chunkLength;
            if ($end > $length) throw new RuntimeException('Invalid PNG chunk length.');
            $type = substr($bytes, $offset + 4, 4);
            if (! in_array($type, ['eXIf', 'tEXt', 'zTXt', 'iTXt'], true)) {
                $output .= substr($bytes, $offset, $end - $offset);
            }
            $offset = $end;
            if ($type === 'IEND') return $output;
        }

        throw new RuntimeException('Incomplete PNG image.');
    }

    private function stripWebp(string $bytes): string
    {
        if (substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP') throw new RuntimeException('Invalid WebP image.');
        $chunks = [];
        $offset = 12;
        $length = strlen($bytes);
        while ($offset + 8 <= $length) {
            $type = substr($bytes, $offset, 4);
            $size = unpack('V', substr($bytes, $offset + 4, 4))[1];
            $end = $offset + 8 + $size + ($size % 2);
            if ($end > $length) throw new RuntimeException('Invalid WebP chunk length.');
            if (! in_array($type, ['EXIF', 'XMP '], true)) {
                $chunk = substr($bytes, $offset, $end - $offset);
                if ($type === 'VP8X' && $size >= 1) {
                    $chunk[8] = chr(ord($chunk[8]) & ~0x0C);
                }
                $chunks[] = $chunk;
            }
            $offset = $end;
        }
        $output = 'RIFF'.pack('V', 4 + array_sum(array_map('strlen', $chunks))).'WEBP'.implode('', $chunks);

        return $output;
    }
}
