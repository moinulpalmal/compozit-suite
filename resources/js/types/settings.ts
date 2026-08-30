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
