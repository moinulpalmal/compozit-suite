import { Store } from 'lucide-react';

/**
 * The empty state a buyer-scoped list shows when the viewer has no buyers.
 *
 * Zero buyers is a legitimate state — a new hire whose access has not been
 * assigned yet (ARCHITECTURE.md §9.2). Rendering a plain empty table for it is
 * what turns "you have no access" into "there is no data", which is the same
 * screen for two very different problems and generates a support ticket every
 * time.
 *
 * Buyer-owned lists render this **instead of** their table when the signed-in
 * user has no buyer access, not merely when the current page is empty: an
 * ordinary "no rows match these filters" message still belongs on a filtered
 * list whose viewer does have access.
 */
export default function NoBuyerAccess({
    resource = 'records',
}: {
    /** What the list would have shown — "orders", "tech packs". */
    resource?: string;
}) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-box border border-base-300/70 px-6 py-12 text-center">
            <Store className="size-8 text-base-content/40" />

            <p className="font-medium">No buyers assigned</p>

            <p className="max-w-prose text-sm text-base-content/60">
                Your account has not been granted access to any buyer yet, so
                there are no {resource} to show. An administrator can grant it
                from the Users screen.
            </p>
        </div>
    );
}
