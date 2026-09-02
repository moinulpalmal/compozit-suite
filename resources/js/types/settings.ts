import type { ListFilters } from '@/types/ui';

/**
 * Settings module types — master data. Re-exported from `@/types`.
 */

export type NotificationColorListItem = {
    id: number;
    name: string;
    /** Uppercase `#RRGGBB`. The server stores and compares it in this form. */
    color_code: string;
    /** How many days a thing coloured this way is kept. */
    retention_days: number;
    /** `'A'` active, `'I'` retired from the pickers. Not a soft delete. */
    status: string;
};

export type NotificationColorFilters = ListFilters & {
    filter: {
        name: string;
        color_code: string;
        status: string;
    };
};

/** One milestone of a TNA template, in days after the BQS date. */
export type TnaTemplateMilestone = {
    milestone: string;
    label: string;
    offset_days: number;
};

/**
 * One rung of a template's urgency ladder.
 *
 * `max_days_remaining` is the inclusive upper bound in days until the planned
 * date: negative means overdue, and `null` is the single catch-all rung.
 */
export type TnaTemplateColor = {
    notification_color_id: number;
    max_days_remaining: number | null;
    name: string;
    color_code: string;
};

export type TnaTemplateListItem = {
    id: number;
    name: string;
    /** Inclusive at both ends. A lead time of 263 matches 241–300. */
    lead_time_from: number;
    lead_time_to: number;
    /** `'A'` active, `'I'` retired. Only active templates match an order. */
    status: string;
    milestones: TnaTemplateMilestone[];
    colors: TnaTemplateColor[];
};

export type TnaTemplateFilters = ListFilters & {
    filter: {
        name: string;
        status: string;
    };
};

/** A milestone the template register may schedule, for the form's inputs. */
export type MilestoneOption = {
    value: string;
    label: string;
};

/** A notification colour a ladder rung may use. */
export type ColorOption = {
    value: number;
    label: string;
    color_code: string;
    status: string;
};
