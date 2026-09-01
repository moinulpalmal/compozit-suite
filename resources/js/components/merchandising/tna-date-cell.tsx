import type { TnaMilestoneCell } from '@/types';

/**
 * One planned date, in the colour its template says it deserves.
 *
 * **The cell decides nothing about urgency.** `TnaCalculator` resolves the colour
 * from the matched template's ladder and sends a hex; this renders it. Any rule
 * applied here as well — "and red if it is really late" — would be a second
 * implementation of the ladder that drifts from the one in Settings, and the board
 * would disagree with the register that configures it.
 *
 * An empty cell is a real state and renders as an em dash: either no template
 * matched, or the matched one does not schedule this milestone.
 */
export default function TnaDateCell({ cell }: { cell: TnaMilestoneCell }) {
    if (cell.date === null) {
        return (
            <span className="text-base-content/30" aria-label="Not scheduled">
                —
            </span>
        );
    }

    const days = cell.days_remaining;

    if (cell.color === null) {
        return (
            <span className="tabular-nums" data-test="tna-date">
                {cell.date}
            </span>
        );
    }

    return (
        <span
            className="inline-flex items-center rounded px-2 py-0.5 font-medium tabular-nums"
            style={{
                backgroundColor: cell.color.color_code,
                color: readableTextOn(cell.color.color_code),
            }}
            /* The colour alone is not an accessible signal — a screen reader and a
               colour-blind reader both get the same sentence a sighted one infers. */
            title={`${cell.color.name} — ${describeDays(days)}`}
            data-test="tna-date"
        >
            {cell.date}
        </span>
    );
}

/**
 * Black or white, whichever stays legible on the given background.
 *
 * This is not decoration. The colours come from a register a user fills in freely,
 * and `#E2FD17` — one of the four already defined — is bright enough that white text
 * on it is unreadable. Fixing the foreground would fail on the dark colours instead,
 * so it is computed per colour.
 *
 * Uses the WCAG relative-luminance formula rather than a naive average: the eye is
 * far more sensitive to green than to blue, and averaging misjudges exactly the
 * saturated colours a status palette is full of.
 */
function readableTextOn(hex: string): string {
    const value = hex.replace('#', '');

    if (!/^[0-9a-f]{6}$/i.test(value)) {
        return '#000000';
    }

    const channels = [0, 2, 4].map((offset) => {
        const channel = parseInt(value.slice(offset, offset + 2), 16) / 255;

        return channel <= 0.03928
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });

    const luminance =
        0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];

    return luminance > 0.179 ? '#000000' : '#FFFFFF';
}

/** How far off the date is, as a sentence rather than a signed number. */
function describeDays(days: number | null): string {
    if (days === null) {
        return 'no date';
    }

    if (days < 0) {
        return `${Math.abs(days)} ${Math.abs(days) === 1 ? 'day' : 'days'} overdue`;
    }

    if (days === 0) {
        return 'due today';
    }

    return `in ${days} ${days === 1 ? 'day' : 'days'}`;
}
