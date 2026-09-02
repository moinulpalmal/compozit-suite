<?php

namespace App\Services\Merchandising\PoParser\Support;

use App\DataTransferObjects\Merchandising\Po\WarningDto;
use App\Enums\Merchandising\PoWarningSeverity;

/**
 * Gathers the warnings raised while one purchase order is being built.
 *
 * One collector per purchase order, so every warning it makes already carries that
 * order's number and a caller never has to remember to pass it. This is a mutable
 * accumulator by design — it is the one place in the parser that is.
 */
final class WarningCollector
{
    /** @var list<WarningDto> */
    private array $warnings = [];

    public function __construct(
        private readonly ?string $poNumber = null,
    ) {}

    /**
     * Record a warning against the purchase order this collector belongs to.
     */
    public function add(
        string $code,
        PoWarningSeverity $severity,
        string $field,
        string $message,
        ?int $page = null,
        ?int $lineIndex = null,
    ): void {
        $this->warnings[] = new WarningDto(
            code: $code,
            severity: $severity,
            field: $field,
            poNumber: $this->poNumber,
            page: $page,
            lineIndex: $lineIndex,
            message: $message,
        );
    }

    /**
     * Every warning recorded, in the order it was raised.
     *
     * @return list<WarningDto>
     */
    public function all(): array
    {
        return $this->warnings;
    }
}
