<?php

namespace App\Enums\Merchandising;

/**
 * A workbook format the BQS reader accepts.
 *
 * Unlike {@see PoFileType}, no case here routes to a different toolchain —
 * PhpSpreadsheet reads both in-process, and neither needs an external binary. The
 * enum exists so `bqs_imports.detected_file_type` is a value with a label rather than
 * a free string, and so an unsupported upload is refused by name.
 *
 * Detected from the file's magic bytes, not its extension: a `.xlsx` is a ZIP
 * (`PK\x03\x04`) and a legacy `.xls` is an OLE2 compound file
 * (`\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1`). A workbook renamed to `.xlsx` is therefore
 * still read correctly, and a `.docx` renamed to `.xlsx` — also a ZIP — is caught
 * later, when the reader finds no BQS header in it.
 */
enum BqsFileType: string
{
    /** OOXML, the format George sends. */
    case Xlsx = 'xlsx';

    /** Legacy binary Excel, read in-process by PhpSpreadsheet's `Xls` reader. */
    case Xls = 'xls';

    /**
     * The label rendered beside an import.
     */
    public function label(): string
    {
        return match ($this) {
            self::Xlsx => 'XLSX',
            self::Xls => 'XLS',
        };
    }

    /**
     * The format's magic bytes, as the detector matches them.
     */
    public function magicBytes(): string
    {
        return match ($this) {
            self::Xlsx => "PK\x03\x04",
            self::Xls => "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1",
        };
    }

    /**
     * Detect the format of a file from its leading bytes.
     *
     * Returns `null` when the file is neither, which the import request turns into a
     * refusal naming the accepted formats.
     */
    public static function detect(string $path): ?self
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $head = (string) fread($handle, 8);
        fclose($handle);

        foreach (self::cases() as $type) {
            if (str_starts_with($head, $type->magicBytes())) {
                return $type;
            }
        }

        return null;
    }
}
