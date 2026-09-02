<?php

namespace App\Exceptions\Merchandising\PoParser;

/**
 * Text could not be recovered from the uploaded file.
 *
 * Raised when an external converter is missing or fails (LibreOffice for `.doc`,
 * `pdftotext` for `.pdf`), when a `.docx` is not a readable OOXML package, or when
 * a PDF yields no text because it is a scan. The messages name the binary and the
 * `.env` key to set, because that is almost always the fix.
 */
class TextExtractionException extends PoParserException {}
