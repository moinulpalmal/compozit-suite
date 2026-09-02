<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\Services\Merchandising\PoParser\Support\Capture;

/**
 * Reads the two legal paragraphs the `Notes:` block carries.
 *
 * Both patterns are `/s` (dot matches newline) because these are wrapped prose, not
 * fields, and both stop at a lookahead rather than a length: the security clause ends
 * where the acceptance clause begins, and the acceptance clause ends at the rule that
 * closes the block, or at the end of the text when the block is the last thing on the
 * page.
 */
final class NotesExtractor
{
    /**
     * @return array{security: string|null, acceptance_clause: string|null}
     */
    public function build(string $text): array
    {
        return [
            'security' => Capture::text('/Security\s+(.+?)(?=Acceptance Clause)/s', $text),
            'acceptance_clause' => Capture::text('/Acceptance Clause\s+(.+?)(?=\n_{3,}|\z)/s', $text),
        ];
    }
}
