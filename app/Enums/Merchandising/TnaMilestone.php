<?php

namespace App\Enums\Merchandising;

use App\Models\Merchandising\PurchaseOrder;
use App\Models\Settings\TnaTemplate;

/**
 * A dated checkpoint on the way from a buy plan to a shipment.
 *
 * The proof-of-concept slice of `Master Order recap.xls`, which tracks roughly
 * twenty-five of these. Adding the twenty-sixth is a case here and a row in
 * `tna_template_milestones` — deliberately not a migration.
 *
 * **Two kinds of milestone live in one enum, and the difference is load-bearing.**
 * Most are *planned*: a template says how many days after the BQS date they fall, and
 * moving the template moves them. `Shipment` is not — it is read from the purchase
 * order the buyer sent, and it is the date lead time is measured *to*. Letting a
 * template offset it would let the register silently contradict the order it is
 * describing, and the resulting lead time would no longer be the one the template was
 * chosen by. {@see self::offsetFromBqs()} is that distinction, stated once; the write
 * requests enforce it so nobody has to remember it.
 */
enum TnaMilestone: string
{
    case TrimsApproval = 'trims_approval';
    case ProductionSampleApproval = 'production_sample_approval';
    case Shipment = 'shipment';

    /**
     * The column heading this milestone is rendered under.
     */
    public function label(): string
    {
        return match ($this) {
            self::TrimsApproval => __('Trims approval'),
            self::ProductionSampleApproval => __('Production sample approval'),
            self::Shipment => __('Shipment'),
        };
    }

    /**
     * Whether this milestone's date is a template offset from the BQS date.
     *
     * False for {@see self::Shipment} alone, which comes from
     * {@see PurchaseOrder::$vendor_ship_date}. A {@see TnaTemplate} may only carry
     * offsets for the cases this returns true for.
     */
    public function offsetFromBqs(): bool
    {
        return $this !== self::Shipment;
    }

    /**
     * The milestones a template supplies an offset for, in the order they are shown.
     *
     * @return list<self>
     */
    public static function planned(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $milestone): bool => $milestone->offsetFromBqs(),
        ));
    }

    /**
     * Every case as the option list the front end renders.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $milestone): array => ['value' => $milestone->value, 'label' => $milestone->label()],
            self::cases(),
        );
    }
}
