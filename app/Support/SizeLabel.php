<?php

namespace App\Support;

use App\Services\Merchandising\BqsHeaderMap;

/**
 * Reduces a size label to a form two documents can be compared on.
 *
 * A BQS and a purchase order are typed by different people from different templates
 * and spell the same size differently:
 *
 * ```text
 * BQS            PO             same size
 * XS(4/5)        XS-4-5         yes
 * L(10/12)       L(10-12)       yes
 * M (7/8)        M(7/8)         yes
 * 3-6M           3-6M           yes — infant sizes already agree
 * ```
 *
 * Normalisation is uppercase, whitespace collapsed away, and every separator a
 * template might choose — `(`, `)`, `/`, `-`, `.` — reduced to a single `-` with no
 * empty parts. `XS(4/5)`, `XS-4-5` and `XS (4/5)` all become `XS-4-5`.
 *
 * **This never changes what is stored.** The BQS keeps the workbook's spelling and a
 * purchase-order line keeps the document's, because each is a quotation from a file
 * somebody can open. Only the *comparison* is normalised — the same posture
 * {@see BqsHeaderMap::normalise()} takes for header text.
 *
 * It lives in `App\Support` rather than beside either reader because both need it and
 * neither owns it: a second copy of this rule is how the two sides would quietly stop
 * agreeing (ARCHITECTURE.md §3).
 */
final class SizeLabel
{
    /**
     * The comparable form of a size label.
     *
     * Returns an empty string for a label with no alphanumeric content, which callers
     * treat as "not a size" rather than as a size that matches everything.
     */
    public static function normalise(?string $label): string
    {
        $value = strtoupper(trim((string) $label));

        if ($value === '') {
            return '';
        }

        /* Every separator becomes one, so the parts can be rejoined uniformly. */
        $value = (string) preg_replace('/[\s()\/\-.]+/', '-', $value);

        return trim($value, '-');
    }

    /**
     * Whether two labels name the same size.
     */
    public static function matches(?string $left, ?string $right): bool
    {
        $normalised = self::normalise($left);

        return $normalised !== '' && $normalised === self::normalise($right);
    }
}
