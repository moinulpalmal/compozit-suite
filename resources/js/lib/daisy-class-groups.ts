/**
 * daisyUI class groups for tailwind-merge.
 *
 * Tailwind utilities already win over daisyUI on cascade layer order
 * (`@layer utilities` beats `@layer components`), so `cn('btn', 'h-7')` behaves
 * correctly without any of this. What tailwind-merge cannot resolve on its own is
 * two daisyUI classes from the *same* family landing in one string — `btn-primary
 * btn-ghost` is decided by daisyUI's stylesheet order, not by class order. Grouping
 * them here makes the later class win, as everywhere else.
 *
 * Base classes (`btn`, `input`, `card`, `menu`, `modal`, `alert`, ...) are
 * deliberately not registered: they must never be stripped, only their modifiers
 * deduplicated. Unregistered classes pass through untouched.
 *
 * @see https://daisyui.com/components/
 */
const COLORS = [
    'neutral',
    'primary',
    'secondary',
    'accent',
    'info',
    'success',
    'warning',
    'error',
] as const;

const SIZES = ['xs', 'sm', 'md', 'lg', 'xl'] as const;

const withPrefix = (prefix: string, values: readonly string[]): string[] =>
    values.map((value) => `${prefix}-${value}`);

export const daisyClassGroups = {
    'daisy-btn-color': [...withPrefix('btn', COLORS), 'btn-ghost', 'btn-link'],
    'daisy-btn-style': ['btn-outline', 'btn-dash', 'btn-soft'],
    'daisy-btn-size': withPrefix('btn', SIZES),
    'daisy-btn-shape': ['btn-square', 'btn-circle', 'btn-wide', 'btn-block'],

    'daisy-input-color': [...withPrefix('input', COLORS), 'input-ghost'],
    'daisy-input-size': withPrefix('input', SIZES),

    'daisy-select-color': [...withPrefix('select', COLORS), 'select-ghost'],
    'daisy-select-size': withPrefix('select', SIZES),

    'daisy-textarea-color': [
        ...withPrefix('textarea', COLORS),
        'textarea-ghost',
    ],
    'daisy-textarea-size': withPrefix('textarea', SIZES),

    'daisy-alert-color': [
        'alert-info',
        'alert-success',
        'alert-warning',
        'alert-error',
    ],
    'daisy-alert-style': ['alert-outline', 'alert-dash', 'alert-soft'],
    'daisy-alert-dir': ['alert-vertical', 'alert-horizontal'],

    'daisy-badge-color': [...withPrefix('badge', COLORS), 'badge-ghost'],
    'daisy-badge-style': ['badge-outline', 'badge-dash', 'badge-soft'],
    'daisy-badge-size': withPrefix('badge', SIZES),

    'daisy-card-size': withPrefix('card', SIZES),
    'daisy-card-style': ['card-border', 'card-dash', 'card-side'],

    'daisy-checkbox-color': withPrefix('checkbox', COLORS),
    'daisy-checkbox-size': withPrefix('checkbox', SIZES),

    'daisy-toggle-color': withPrefix('toggle', COLORS),
    'daisy-toggle-size': withPrefix('toggle', SIZES),

    'daisy-menu-size': withPrefix('menu', SIZES),
    'daisy-menu-dir': ['menu-horizontal', 'menu-vertical'],

    'daisy-modal-pos': [
        'modal-top',
        'modal-middle',
        'modal-bottom',
        'modal-start',
        'modal-end',
    ],

    'daisy-tooltip-color': withPrefix('tooltip', COLORS),
    'daisy-tooltip-pos': [
        'tooltip-top',
        'tooltip-bottom',
        'tooltip-left',
        'tooltip-right',
    ],

    'daisy-loading-kind': [
        'loading-spinner',
        'loading-dots',
        'loading-ring',
        'loading-ball',
        'loading-bars',
        'loading-infinity',
    ],
    'daisy-loading-size': withPrefix('loading', SIZES),

    'daisy-divider-color': withPrefix('divider', COLORS),
    'daisy-divider-dir': ['divider-horizontal', 'divider-vertical'],

    'daisy-tabs-style': ['tabs-box', 'tabs-border', 'tabs-lift'],
    'daisy-tabs-size': withPrefix('tabs', SIZES),

    'daisy-avatar-state': ['avatar-online', 'avatar-offline'],

    'daisy-join-dir': ['join-horizontal', 'join-vertical'],
} as const;

export type DaisyClassGroupId = keyof typeof daisyClassGroups;
