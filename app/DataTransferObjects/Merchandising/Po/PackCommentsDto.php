<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * The `PACK COMMENTS` block that closes each pack: vendor type, letter of credit,
 * defective allowance wording, and the mill/factory compliance declarations.
 */
final readonly class PackCommentsDto
{
    /**
     * @param  array{fabrics_mill: string|null, yarn_mill: string|null, factory: string|null}  $compliance
     */
    public function __construct(
        public ?string $vendorTypePico = null,
        public ?string $letterOfCredit = null,
        public ?string $defectiveAllowanceText = null,
        public array $compliance = ['fabrics_mill' => null, 'yarn_mill' => null, 'factory' => null],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vendor_type_pico' => $this->vendorTypePico,
            'letter_of_credit' => $this->letterOfCredit,
            'defective_allowance_text' => $this->defectiveAllowanceText,
            'compliance' => $this->compliance,
        ];
    }
}
