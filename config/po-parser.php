<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Uploaded documents are kept so a parsed purchase order can always be read
    | back against the file it came from — that is what makes a `failed` import
    | diagnosable rather than merely reported. `tmp` holds the intermediate
    | `.docx` LibreOffice produces and the scratch file `pdftotext` writes; both
    | are removed after each parse.
    |
    */

    'storage' => [
        'disk' => env('PO_PARSER_DISK', 'local'),
        'upload' => 'po-imports',
        'tmp' => 'po-parser/tmp',
        'retain_original' => env('PO_PARSER_RETAIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | LibreOffice — the .doc and .rtf path
    |--------------------------------------------------------------------------
    |
    | There is deliberately NO default path. A wrong default turns "LibreOffice is
    | not installed" into a confusing message naming a path nobody chose; an empty
    | one produces the instruction to set this key. See documentation/deployment.md.
    |
    | Windows: C:\Program Files\LibreOffice\program\soffice.exe
    | Linux:   /usr/bin/soffice
    |
    */

    'libreoffice' => [
        'bin' => env('LIBREOFFICE_BIN'),
        'timeout' => env('LIBREOFFICE_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | pdftotext — the .pdf path
    |--------------------------------------------------------------------------
    |
    | Must be the XPDF build (xpdfreader.com), not Poppler. Both accept `-layout`
    | and both are commonly installed as `pdftotext`, but their column output is
    | not byte-identical — and this parser reads column positions. Verify with
    | `pdftotext -v`; the banner names the implementation.
    |
    */

    'pdftotext' => [
        'bin' => env('PDFTOTEXT_BIN'),
        'timeout' => env('PDFTOTEXT_TIMEOUT', 60),
        'encoding' => env('PDFTOTEXT_ENCODING', 'UTF-8'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Parsing runs inside the request, so these are what bound how long one can
    | take. `max_file_size_kb` is enforced by the form request before any work
    | happens; the other two are checked as soon as the document's shape is known
    | and before any extraction runs.
    |
    */

    'limits' => [
        'max_file_size_kb' => env('PO_MAX_KB', 20480),
        'max_pages' => env('PO_MAX_PAGES', 200),
        'max_pos_per_file' => env('PO_MAX_POS', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parsing
    |--------------------------------------------------------------------------
    |
    | `default_tab_stop` drives the tab expansion in DocxTextExtractor and so
    | decides where every column lands — do not change it without re-running the
    | fixture tests. `warn_threshold` is the confidence below which a parse is
    | marked `needs_review`.
    |
    | `size_vocab` is a LAST RESORT and is not how sizes are normally read. A pack
    | states its own columns in the heading above its rows, and LineItemRowExtractor
    | takes the size from that column's position; this list is consulted only when
    | that heading cannot be read, and then only after the buyer's own BQS has been
    | asked (App\Services\Merchandising\BqsSizeVocabulary).
    |
    | It stays because it is what the girls' reference document in tests/Fixtures
    | prints, and because a purchase order can arrive before any BQS exists to ask.
    | Do not extend it for a new product programme — the size run comes with the
    | buyer's workbook, which is why an infant order needed no code change here.
    |
    */

    'parsing' => [
        'default_tab_stop' => 8,
        'column_separator_dot' => '.',
        'warn_threshold' => 0.90,
        'size_vocab' => ['XS-4-5', 'S(6)', 'M(7/8)', 'L(10-12)', 'XL(14-16)'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Accepted uploads
    |--------------------------------------------------------------------------
    |
    | These gate the upload form. The file's real type is then read from its magic
    | bytes by FileTypeDetector — these are the client's claim, not the fact.
    |
    */

    'accepted_extensions' => ['doc', 'docx', 'pdf', 'rtf'],

    'accepted_mimes' => [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/pdf',
        'application/rtf',
        'text/rtf',
        'application/octet-stream',
    ],

];
