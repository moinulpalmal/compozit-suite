<?php

namespace App\Exceptions\Merchandising\PoParser;

/**
 * The file's magic bytes match none of the supported formats.
 *
 * Detection reads the signature rather than trusting the extension, so a `.docx`
 * that is really a PDF is handled correctly and a renamed executable is refused.
 */
class UnsupportedFileTypeException extends PoParserException {}
