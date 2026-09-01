<?php

namespace App\Concerns;

use App\Enums\Merchandising\TnaMilestone;
use App\Enums\RecordStatus;
use App\Models\Settings\TnaTemplate;
use App\Services\Settings\TnaTemplateService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The rules the TNA template create and edit forms share.
 *
 * Extracted the way {@see NotificationColorValidationRules} is, so the two requests
 * differ only in the id they ignore.
 *
 * Three rules here cannot be expressed as field rules and live in
 * {@see self::validateTnaTemplate()} instead. Each is a constraint the database
 * cannot carry either, which is why validation is the only thing standing behind
 * them — see the migrations for why in each case.
 */
trait TnaTemplateValidationRules
{
    /**
     * Get the validation rules that apply to a TNA template.
     *
     * The lead-time bounds are capped at 65535 because the columns are
     * `unsignedSmallInteger`; past that the driver errors instead of the field,
     * which is a 500 rather than a message. `lead_time_from` starts at 1 because a
     * zero-day programme is a data error, not a band — {@see TnaCalculator} refuses
     * to schedule one, so a band covering it could never match anything.
     *
     * `max_days_remaining` is deliberately allowed to be **negative**: that is how a
     * rung says "the date has passed". It is nullable because exactly one rung may be
     * the catch-all.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function tnaTemplateRules(?int $tnaTemplateId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('tna_templates', 'name')->ignore($tnaTemplateId),
            ],
            'lead_time_from' => ['required', 'integer', 'min:1', 'max:65535'],
            'lead_time_to' => ['required', 'integer', 'min:1', 'max:65535', 'gte:lead_time_from'],
            'status' => ['required', Rule::enum(RecordStatus::class)],

            'milestones' => ['present', 'array', 'max:'.count(TnaMilestone::cases())],
            'milestones.*.milestone' => ['required', Rule::enum(TnaMilestone::class)],
            'milestones.*.offset_days' => ['required', 'integer', 'min:0', 'max:65535'],

            'colors' => ['present', 'array', 'max:20'],
            'colors.*.notification_color_id' => ['required', 'integer', Rule::exists('notification_colors', 'id')],
            'colors.*.max_days_remaining' => ['nullable', 'integer', 'min:-32768', 'max:32767'],
        ];
    }

    /**
     * The three cross-field rules, applied after the field rules pass.
     *
     * They are here rather than in the database because none of them can be:
     *
     * - **No two active bands may overlap.** MySQL has no exclusion constraint and
     *   SQLite has neither, so there is no portable way to state it in schema.
     * - **At most one catch-all rung.** A unique index would not do it: repeated
     *   `NULL`s are permitted in unique indexes on both drivers, so the index would
     *   read as a guard while allowing exactly what it appears to forbid — the same
     *   trap documented on `bqs_sheets.root_id`.
     * - **Only schedulable milestones may carry an offset.** `Shipment` comes from
     *   the purchase order; a template offsetting it would contradict the order it
     *   describes, and the lead time it was chosen by.
     *
     * Duplicate milestones and duplicate colours *are* refused by unique constraints,
     * but are checked here too so the user gets a field message instead of a 500.
     */
    protected function validateTnaTemplate(Validator $validator, TnaTemplateService $templates, ?int $tnaTemplateId = null): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $this->validateNoOverlap($validator, $templates, $tnaTemplateId);
        $this->validateMilestones($validator);
        $this->validateColors($validator);
    }

    /**
     * Refuse a band that overlaps another active one.
     *
     * Skipped entirely for an inactive template: it can never match an order, so
     * overlapping is harmless and forbidding it would make a retired band impossible
     * to keep alongside its replacement.
     */
    private function validateNoOverlap(Validator $validator, TnaTemplateService $templates, ?int $tnaTemplateId): void
    {
        if ($this->input('status') !== RecordStatus::Active->value) {
            return;
        }

        $from = (int) $this->input('lead_time_from');
        $to = (int) $this->input('lead_time_to');

        if (! $templates->overlaps($from, $to, $tnaTemplateId)) {
            return;
        }

        $validator->errors()->add(
            'lead_time_from',
            __('Another active template already covers part of :from–:to days. Bands may not overlap.', [
                'from' => $from,
                'to' => $to,
            ]),
        );
    }

    /**
     * Refuse an unschedulable milestone, and a milestone scheduled twice.
     */
    private function validateMilestones(Validator $validator): void
    {
        /** @var list<array{milestone?: string, offset_days?: mixed}> $milestones */
        $milestones = $this->input('milestones', []);

        $seen = [];

        foreach ($milestones as $index => $milestone) {
            $case = TnaMilestone::tryFrom((string) ($milestone['milestone'] ?? ''));

            if ($case === null) {
                continue;
            }

            if (! $case->offsetFromBqs()) {
                $validator->errors()->add(
                    "milestones.{$index}.milestone",
                    __(':milestone is read from the purchase order and cannot be scheduled by a template.', [
                        'milestone' => $case->label(),
                    ]),
                );

                continue;
            }

            if (in_array($case->value, $seen, true)) {
                $validator->errors()->add(
                    "milestones.{$index}.milestone",
                    __(':milestone is scheduled twice.', ['milestone' => $case->label()]),
                );

                continue;
            }

            $seen[] = $case->value;
        }
    }

    /**
     * Refuse a second catch-all rung, and the same colour used twice.
     */
    private function validateColors(Validator $validator): void
    {
        /** @var list<array{notification_color_id?: mixed, max_days_remaining?: mixed}> $colors */
        $colors = $this->input('colors', []);

        $catchAll = null;
        $seen = [];

        foreach ($colors as $index => $color) {
            $colorId = (int) ($color['notification_color_id'] ?? 0);

            if (in_array($colorId, $seen, true)) {
                $validator->errors()->add(
                    "colors.{$index}.notification_color_id",
                    __('This colour is already used by another band on this template.'),
                );
            } else {
                $seen[] = $colorId;
            }

            if (($color['max_days_remaining'] ?? null) !== null) {
                continue;
            }

            if ($catchAll !== null) {
                $validator->errors()->add(
                    "colors.{$index}.max_days_remaining",
                    __('Only one band may be left open-ended. Give this one an upper bound in days.'),
                );

                continue;
            }

            $catchAll = $index;
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    protected function tnaTemplateMessages(): array
    {
        return [
            'name.unique' => __('A TNA template with this name already exists.'),
            'lead_time_to.gte' => __('The end of the band must not be before its start.'),
            'colors.*.notification_color_id.exists' => __('That notification colour no longer exists.'),
        ];
    }

    /**
     * The template being edited, when there is one.
     */
    protected function tnaTemplate(): ?TnaTemplate
    {
        $template = $this->route('tna_template');

        return $template instanceof TnaTemplate ? $template : null;
    }
}
