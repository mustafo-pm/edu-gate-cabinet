<?php

declare(strict_types=1);

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * QR codes for receipts.
 *
 * SVG on the web page — it stays sharp at any zoom, which matters when someone
 * points a phone camera at a laptop screen. PNG for the PDF, because the PDF
 * renderer's SVG support is patchy and a QR that fails to draw makes the whole
 * document useless.
 *
 * Error correction is High: these get printed, folded, and photographed off
 * screens, and the redundancy is what survives that.
 *
 * Built with the constructor rather than a fluent builder — the library's v6
 * release removed the static factory in favour of named arguments.
 */
final class Qr
{
    public static function svg(string $text, int $size = 220): string
    {
        return (new Builder(
            writer: new SvgWriter,
            data: $text,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 0,
        ))->build()->getString();
    }

    /** Data URI, so the PDF carries the image rather than fetching it. */
    public static function pngDataUri(string $text, int $size = 220): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $text,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 0,
        ))->build()->getDataUri();
    }
}
