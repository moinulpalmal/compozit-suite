<?php

namespace App\Models\Merchandising;

use App\Concerns\Audited;
use App\Concerns\BuyerScoped;
use App\Models\Admin\Buyer;
use App\Models\User;
use App\Observers\ActorObserver;
use App\Services\Merchandising\BqsPoLinker;
use App\Services\Merchandising\BqsRowKey;
use Database\Factories\Merchandising\BqsColourLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A standing decision that a purchase-order colour means a particular BQS row.
 *
 * Written whenever somebody resolves a colour by hand on the purchase-order detail
 * page, and read by {@see BqsPoLinker} on every later import — so the decision is made
 * once and then applies to every order carrying that colour.
 *
 * It exists because colour matching is strict equality and Walmart truncates the
 * colour column, which leaves roughly half of every order to be resolved by a person;
 * the `create_bqs_colour_links_table` migration records that reasoning in full.
 *
 * `bqs_row_key` is {@see BqsRowKey}'s hash rather than a foreign key on purpose: it
 * survives BQS revisions, and it may name a row that has not been imported yet.
 *
 * Buyer-owned, so a mapping is only ever visible — and only ever applied — within the
 * buyer it was made for (ARCHITECTURE.md §9.2).
 *
 * @property int $id
 * @property int $buyer_id
 * @property string $vendor_style_no
 * @property string $po_color
 * @property string $bqs_row_key
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Buyer $buyer
 * @property-read User|null $insertedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable(['buyer_id', 'vendor_style_no', 'po_color', 'bqs_row_key'])]
class BqsColourLink extends Model implements Auditable
{
    /** @use HasFactory<BqsColourLinkFactory> */
    use Audited, BuyerScoped, HasFactory;

    /**
     * The buyer this mapping belongs to.
     *
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * The user who made the decision, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function insertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inserted_by');
    }
}
