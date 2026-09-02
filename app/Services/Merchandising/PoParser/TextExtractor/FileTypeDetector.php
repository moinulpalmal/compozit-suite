<?php

namespace App\Services\Merchandising\PoParser\TextExtractor;

use App\Enums\Merchandising\PoFileType;
use App\Exceptions\Merchandising\PoParser\UnsupportedFileTypeException;

/**
 * Identifies an uploaded file from its first bytes.
 *
 * **The extension is never trusted.** A `.doc` that Word actually saved as RTF is
 * common enough to matter, and the upload validation's `mimes:` rule checks the
 * client's claim rather than the content. Reading the signature is also what stops
 * a renamed executable reaching LibreOffice.
 */
final class FileTypeDetector
{
    private const string SIGNATURE_ZIP = "PK\x03\x04";

    private const string SIGNATURE_OLE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    private const string SIGNATURE_RTF = '{\rtf';

    private const string SIGNATURE_PDF = '%PDF';

    /**
     * @throws UnsupportedFileTypeException when the signature matches no known format
     */
    public function detect(string $path): PoFileType
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new UnsupportedFileTypeException('Cannot open file for reading: '.$path);
        }

        $head = (string) fread($handle, 8);
        fclose($handle);

        return match (true) {
            str_starts_with($head, self::SIGNATURE_ZIP) => PoFileType::Docx,
            str_starts_with($head, self::SIGNATURE_OLE) => PoFileType::Doc,
            str_starts_with($head, self::SIGNATURE_RTF) => PoFileType::Rtf,
            str_starts_with($head, self::SIGNATURE_PDF) => PoFileType::Pdf,
            default => throw new UnsupportedFileTypeException(
                'Unrecognised file signature. Expected a Word document (.doc/.docx), RTF or PDF.'
            ),
        };
    }
}
