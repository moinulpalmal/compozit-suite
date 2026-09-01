import { Form } from '@inertiajs/react';
import { Link2, Link2Off } from 'lucide-react';
import BqsLinkController from '@/actions/App/Http/Controllers/Merchandising/BqsLinkController';
import { Button } from '@/components/ui/button';
import Combobox from '@/components/ui/combobox';
import type { BqsColourLink } from '@/types';

type Props = {
    orderId: number;
    link: BqsColourLink;
    /** False for a reader without `merchandising.purchase-orders.update`. */
    canLink: boolean;
};

/**
 * Shows, and lets someone set, the BQS row one purchase-order colour was planned by.
 *
 * **One control per colour, not per line item.** A pack is one colour in five sizes and
 * all five always belong to the same plan row, so this appears on the pack header —
 * four decisions on the reference document rather than sixty.
 *
 * It exists because colour matching is strict equality while Walmart truncates the
 * colour column to fifteen characters: `BALLAD BLUE` arrives as `LTBLUE-BALLAD B` and
 * cannot match, so about half of every order reaches a person here. The decision is
 * remembered as a rule, which is why the button says so — a user who did not know that
 * would reasonably expect to repeat it on the next order.
 *
 * **Candidates are the same style and the same buyer only.** A colour the plan never
 * had — `TEAL-ICY MORN` — is offered nothing, rather than an invitation to attach it to
 * an unrelated row and manufacture a link Production would later read as fact.
 */
export default function BqsLinkControl({ orderId, link, canLink }: Props) {
    const linked = link.bqs_row_id !== null;

    if (!canLink) {
        return linked ? (
            <LinkedBadge link={link} />
        ) : (
            <UnlinkedNote link={link} />
        );
    }

    return (
        <Form
            {...BqsLinkController.update.form(orderId)}
            options={{ preserveScroll: true }}
            className="flex flex-wrap items-center gap-2"
        >
            {({ processing }) => (
                <>
                    <input
                        type="hidden"
                        name="vendor_stock"
                        value={link.vendor_stock ?? ''}
                    />
                    <input
                        type="hidden"
                        name="color"
                        value={link.color ?? ''}
                    />

                    {linked ? (
                        <>
                            <LinkedBadge link={link} />

                            {/* Clearing is the same request with an empty value, so
                                the server has one code path rather than two. */}
                            <input type="hidden" name="bqs_row_id" value="" />

                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                disabled={processing}
                                data-test="unlink-bqs"
                            >
                                <Link2Off /> Unlink
                            </Button>
                        </>
                    ) : link.candidates.length === 0 ? (
                        <UnlinkedNote link={link} />
                    ) : (
                        <>
                            <span className="text-xs text-base-content/60">
                                No BQS row matched this colour:
                            </span>

                            <Combobox
                                name="bqs_row_id"
                                options={link.candidates}
                                placeholder="Choose the planned colour"
                                required
                                className="min-w-64"
                                data-test="bqs-row-picker"
                            />

                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                                data-test="link-bqs"
                            >
                                <Link2 />
                                {processing ? 'Linking…' : 'Link'}
                            </Button>
                        </>
                    )}
                </>
            )}
        </Form>
    );
}

/** What this colour is linked to, and who decided it. */
function LinkedBadge({ link }: { link: BqsColourLink }) {
    return (
        <span className="inline-flex items-center gap-2" data-test="bqs-linked">
            <span className="badge badge-sm badge-success">
                <Link2 className="size-3" /> {link.label}
            </span>

            {/* `manual` is not decoration: the matcher will never overwrite it. */}
            <span className="badge badge-ghost badge-xs">
                {link.source === 'manual' ? 'linked by hand' : 'matched'}
            </span>
        </span>
    );
}

/**
 * A colour the plan does not contain.
 *
 * Deliberately not phrased as an error. `TEAL-ICY MORN` is on the reference order and
 * absent from its BQS, and unlinked is the correct permanent answer — the order is
 * real, the plan simply never had that colourway.
 */
function UnlinkedNote({ link }: { link: BqsColourLink }) {
    return (
        <span className="text-xs text-base-content/60" data-test="bqs-unlinked">
            {link.candidates.length === 0
                ? 'No BQS row exists for this style, so there is nothing to link.'
                : 'Not linked to a BQS row.'}
        </span>
    );
}
