<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The library keeps the file itself — that is the entire point of it, unlike
    | the two importers, where the file is evidence for a parse result and
    | `retain_original` may reasonably be switched off. There is no equivalent
    | toggle here: a document library that discards its documents is nothing.
    |
    | The disk is private. Files are served by a route that checks the
    | permission and the buyer scope, never by a public URL — see
    | `DocumentFileController`.
    |
    | Each batch gets a directory named for its id, and each file inside it is
    | named with a ULID. The uploader's own filename is held on the row and
    | restored by the download response; it never reaches the filesystem, so it
    | can neither collide nor carry a traversal segment.
    |
    */

    'storage' => [
        'disk' => env('DOCUMENT_LIBRARY_DISK', 'local'),
        'root' => 'merchandising-documents',
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | **There is deliberately no per-file size limit.** `upload_max_filesize`
    | and `post_max_size` are what actually bound an upload, and the owner's
    | decision is that whatever the server allows, the library accepts.
    |
    | `max_files_per_batch` is a different kind of number: it is **PHP's**
    | `max_file_uploads`, not a preference. Files beyond that count are dropped
    | from `$_FILES` before any PHP code runs — no warning, no validation error,
    | they simply never arrive. Validating against it is the only way the user
    | hears about it at all, which is why the message names batching rather than
    | a policy. Raise `max_file_uploads` in `php.ini` and raise this to match;
    | setting this higher on its own re-opens the silent loss.
    |
    */

    'limits' => [
        'max_files_per_batch' => env('DOCUMENT_LIBRARY_MAX_FILES', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed extensions
    |--------------------------------------------------------------------------
    |
    | Validated with Laravel's `extensions:` rule — the filename's extension —
    | and **not** with `mimes:`, which guesses from content and guesses badly
    | for legacy Office containers: `mimes:doc` rejects genuine `.doc` files
    | that LibreOffice opens without complaint.
    |
    | `svg` and `html` are absent on purpose and must stay absent. Both can
    | carry script, and the preview route renders images and PDFs inline from
    | the application's own origin — an inline SVG there is stored XSS. Anything
    | added to this list should be checked against that route first.
    |
    */

    'allowed_extensions' => [
        'xlsx', 'xls', 'xlsm', 'csv',
        'pdf',
        'doc', 'docx', 'rtf', 'txt',
        'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic',
        'zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Inline preview
    |--------------------------------------------------------------------------
    |
    | The extensions the browser can render in place. Everything else falls back
    | to a download prompt in the UI — no converter is involved, and none is
    | going to be: converting an `.xlsx` to something previewable is parsing,
    | which this surface does not do.
    |
    */

    'previewable_extensions' => [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'txt',
    ],

];
