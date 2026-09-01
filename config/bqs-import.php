<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Uploaded workbooks are kept so an imported BQS can always be read back
    | against the file it came from. That matters more here than for a purchase
    | order: a BQS carries no document number, so the workbook is the only thing
    | that can settle an argument about what the buyer actually sent.
    |
    | With `retain_original` off, `bqs_imports.stored_path` is null and the
    | resolved header map in `payload` is all that survives.
    |
    */

    'storage' => [
        'disk' => env('BQS_IMPORT_DISK', 'local'),
        'upload' => 'bqs-imports',
        'retain_original' => env('BQS_IMPORT_RETAIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Reading runs inside the request, as the purchase-order import does, so
    | these bound how long one upload can take. `max_file_size_kb` is enforced by
    | the form request before any work happens; `max_rows` is enforced by the
    | reader, because the row count is not knowable until the sheet is open.
    |
    | `max_rows` is generous on purpose. A BQS is one program's buy plan — the
    | reference file holds six rows — but a department-wide export of several
    | hundred is plausible and there is no reason to refuse it. What the limit
    | actually stops is a workbook with a runaway blank tail.
    |
    */

    'limits' => [
        'max_file_size_kb' => env('BQS_MAX_KB', 10240),
        'max_rows' => env('BQS_MAX_ROWS', 5000),
    ],

];
