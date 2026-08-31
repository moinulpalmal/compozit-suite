<?php

namespace App\Enums\Merchandising;

use App\Services\Merchandising\PoParser\TextExtractor\FileTypeDetector;

/**
 * A document format the purchase-order parser can read.
 *
 * Detected from the file's magic bytes rather than its extension — see
 * {@see FileTypeDetector} — so a
 * mislabelled file is handled correctly and a renamed one is refused.
 *
 * Each case names the toolchain it needs; {@see self::requiresLibreOffice()} is what
 * routes the legacy formats through conversion rather than duplicating that decision
 * in the service.
 */
enum PoFileType: string
{
    /** OOXML. Read in-process with `ext-zip` and `ext-dom`; no external binary. */
    case Docx = 'docx';

    /** Legacy binary Word. Converted to `.docx` by LibreOffice first. */
    case Doc = 'doc';

    /** Rich Text. Converted to `.docx` by LibreOffice first. */
    case Rtf = 'rtf';

    /** Converted to fixed-width text by Xpdf's `pdftotext -layout`. */
    case Pdf = 'pdf';

    /**
     * Whether reading this format needs LibreOffice on the machine.
     */
    public function requiresLibreOffice(): bool
    {
        return match ($this) {
            self::Doc, self::Rtf => true,
            self::Docx, self::Pdf => false,
        };
    }

    /**
     * The label rendered beside an import.
     */
    public function label(): string
    {
        return match ($this) {
            self::Docx => 'DOCX',
            self::Doc => 'DOC',
            self::Rtf => 'RTF',
            self::Pdf => 'PDF',
        };
    }
}
