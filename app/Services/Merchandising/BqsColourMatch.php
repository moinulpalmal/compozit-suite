<?php

namespace App\Services\Merchandising;

use App\Models\Merchandising\BqsRow;

/**
 * Reads a purchase-order colour, and decides whether it is a BQS row's colour.
 *
 * **The only place `LTBLUE-BALLAD B` is parsed.** Nothing else may split that string;
 * if the format changes, it changes here.
 *
 * ## What the field actually holds
 *
 * A Walmart purchase order states colour as `{colour family}-{Pantone colour}`, in a
 * fixed-width column **fifteen characters wide**. Measured on the reference document:
 *
 * ```text
 * LTBLUE-BALLAD B   (15)  <- BQS: family LTBLUE, pantone BALLAD BLUE  — truncated
 * NATURL-SANDSHEL   (15)  <- BQS: family NATURL, pantone SANDSHELL    — truncated
 * PINK-CANDY PINK   (15)  <- BQS: family PINK,   pantone CANDY PINK   — fits exactly
 * TEAL-ICY MORN     (13)  <- no BQS row exists for this colour at all
 * ```
 *
 * ## Matching is strict equality, and that is a decision
 *
 * Both halves must equal the BQS row's exactly. **The owner was shown that truncation
 * therefore makes `BALLAD BLUE` and `SANDSHELL` permanently unmatchable — only
 * `CANDY PINK` auto-links out of the four — and confirmed the rule.** Everything else
 * goes to manual selection, which {@see BqsPoLinker} then remembers as a reusable rule
 * so the same decision is not re-made on every future order.
 *
 * Do not "improve" this into a prefix match. `BqsPoLinkTest` pins both non-matches
 * precisely so that widening it fails loudly rather than silently linking more than
 * was agreed. If prefix matching is ever wanted, it is a new decision by the owner,
 * recorded in `documentation/merchandising.md` first.
 *
 * Comparison is case-insensitive and whitespace-collapsed via
 * {@see BqsHeaderMap::normalise()} — that is not a widening of the rule, it is the
 * same rule applied to values two different documents typed by hand.
 */
class BqsColourMatch
{
    /**
     * Split a purchase-order colour into its family and Pantone halves.
     *
     * Splits on the **first** hyphen: a family code never contains one, and a Pantone
     * name may. Returns `null` when there is no hyphen at all, which is not a colour
     * this rule can read and is treated as "no match" rather than guessed at.
     *
     * @return array{family: string, pantone: string}|null
     */
    public static function split(?string $poColor): ?array
    {
        $value = trim((string) $poColor);

        if ($value === '' || ! str_contains($value, '-')) {
            return null;
        }

        [$family, $pantone] = explode('-', $value, 2);

        $family = trim($family);
        $pantone = trim($pantone);

        return $family === '' || $pantone === ''
            ? null
            : ['family' => $family, 'pantone' => $pantone];
    }

    /**
     * Whether this purchase-order colour is that BQS row's colour.
     *
     * Strict: family equal **and** Pantone equal. The vendor style is checked by the
     * caller, because it comes from the pack rather than from this field.
     */
    public static function matches(?string $poColor, BqsRow $row): bool
    {
        $parts = self::split($poColor);

        if ($parts === null) {
            return false;
        }

        return BqsHeaderMap::normalise($parts['family']) === BqsHeaderMap::normalise($row->colour_family)
            && BqsHeaderMap::normalise($parts['pantone']) === BqsHeaderMap::normalise($row->pantone_colour);
    }

    /**
     * How near a BQS row is to a purchase-order colour, for **ordering the manual
     * picker only**.
     *
     * Higher is nearer. This never creates a link and never influences
     * {@see self::matches()} — it exists so that a merchandiser resolving
     * `LTBLUE-BALLAD B` by hand finds `BALLAD BLUE` at the top of the list instead of
     * hunting for it. Delete it and the behaviour of the feature is unchanged; only
     * the order of the options moves.
     *
     * The truncation is what makes it useful: the Pantone half of a truncated colour
     * is by definition a prefix of the real one.
     */
    public static function affinity(?string $poColor, BqsRow $row): int
    {
        $parts = self::split($poColor);

        if ($parts === null) {
            return 0;
        }

        $family = BqsHeaderMap::normalise($parts['family']);
        $pantone = BqsHeaderMap::normalise($parts['pantone']);
        $rowFamily = BqsHeaderMap::normalise($row->colour_family);
        $rowPantone = BqsHeaderMap::normalise($row->pantone_colour);

        $score = 0;

        if ($family === $rowFamily) {
            $score += 2;
        }

        if ($pantone !== '' && str_starts_with($rowPantone, $pantone)) {
            $score += 1;
        }

        return $score;
    }
}
