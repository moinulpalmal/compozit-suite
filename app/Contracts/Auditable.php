<?php

namespace App\Contracts;

use App\Concerns\Audited;
use App\Services\Admin\AuditRecorder;
use OwenIt\Auditing\Contracts\Auditable as PackageAuditable;

/**
 * What an audited model in this application is — ARCHITECTURE.md §9.3.
 *
 * Extends the package's contract and adds the two operations
 * {@see AuditRecorder} needs. Every audited model implements **this** rather than
 * the package's, so a model declares one interface and gets both.
 *
 * **It exists because a custom audit is driven by public properties.** The
 * package's own documented way to record an event of your own is to assign
 * `auditEvent`, `isCustomEvent`, `auditCustomOld` and `auditCustomNew` on the
 * model and dispatch `AuditCustom`. Those properties live on the package's
 * *trait*, not on its contract — so a caller holding the contract cannot see
 * them, and reaching for them anyway is exactly the "access to an undefined
 * property" that static analysis is right to reject.
 *
 * Naming the operations instead of the properties is also the better shape: the
 * recorder asks the model to prepare itself rather than reaching in and setting
 * four fields in the correct order, and the resetting half cannot be forgotten
 * because it has a name. {@see Audited} is the only implementation.
 */
interface Auditable extends PackageAuditable
{
    /**
     * Stage an event the package's Eloquent observers cannot produce.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function prepareCustomAudit(string $event, array $old, array $new): void;

    /**
     * Discard staged custom-event state.
     *
     * **Not optional, and not merely tidiness.** The staged values live on the
     * model instance, so an instance left prepared would write the *next*
     * ordinary save as another custom event.
     */
    public function clearCustomAudit(): void;
}
