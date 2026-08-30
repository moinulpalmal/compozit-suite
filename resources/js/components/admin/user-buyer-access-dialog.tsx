import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import Combobox from '@/components/ui/combobox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { options as buyerOptionsRoute } from '@/routes/admin/buyers';
import type { UserListItem } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Grant a user access to buyers — the only place this is edited.
 *
 * There is no buyer-access page; access lives beside the user it belongs to, the
 * way roles do (ARCHITECTURE.md §9.2).
 *
 * **The checkbox and the picker are mutually exclusive**, and the server agrees:
 * granting all-access detaches every row, so a user who has it holds no
 * individual grants to fall back on. Ticking it therefore disables the picker
 * rather than quietly ignoring what is in it.
 *
 * The picker is `searchUrl`-backed: buyers outgrow being shipped to the browser
 * whole, and the chips keep their labels because `Combobox` folds remote results
 * in with the options it was rendered with.
 */
export default function UserBuyerAccessDialog({
    submit,
    user,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    user: UserListItem;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [allBuyers, setAllBuyers] = useState(user.all_buyer_access ?? false);

    const held = user.buyers ?? [];

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent>
                <DialogTitle>Buyer access for {user.name}</DialogTitle>
                <DialogDescription>
                    Which buyers this user can see. Every buyer-owned record —
                    orders, tech packs, bookings — is filtered by this.
                </DialogDescription>

                <Form
                    {...submit}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="mt-4 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Unchecked checkboxes submit nothing, so a hidden
                                0 backs it — the same shape `approval_authority`
                                uses on the user form. */}
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="hidden"
                                    name="all_buyer_access"
                                    value="0"
                                />
                                <Checkbox
                                    name="all_buyer_access"
                                    value="1"
                                    defaultChecked={allBuyers}
                                    onChange={(event) =>
                                        setAllBuyers(event.target.checked)
                                    }
                                    data-test="all-buyer-access"
                                />
                                <span>
                                    All buyers
                                    <span className="block text-xs text-base-content/60">
                                        Includes every buyer added later, with
                                        nothing to update.
                                    </span>
                                </span>
                            </label>

                            <InputError message={errors.all_buyer_access} />

                            <div className="grid gap-1.5">
                                <Label htmlFor="buyers">Buyers</Label>
                                <Combobox
                                    id="buyers"
                                    /* Bare name: the component emits one
                                       hidden `buyers[]` input per selection,
                                       so passing the brackets here would send
                                       `buyers[][]`. */
                                    name="buyers"
                                    multiple
                                    disabled={allBuyers}
                                    defaultValue={held.map(
                                        (buyer) => buyer.value,
                                    )}
                                    /* The wire carries `hint: null` for a
                                       buyer with no code; `ComboboxOption`
                                       wants it absent. */
                                    options={held.map((buyer) => ({
                                        ...buyer,
                                        hint: buyer.hint ?? undefined,
                                    }))}
                                    searchUrl={buyerOptionsRoute.url()}
                                    placeholder="Search buyers…"
                                    data-test="buyer-select"
                                />
                                <p className="text-xs text-base-content/60">
                                    {allBuyers
                                        ? 'Not used while All buyers is ticked — the individual grants are cleared.'
                                        : 'With none selected this user sees no buyer-owned records at all.'}
                                </p>
                                <InputError
                                    message={
                                        errors.buyers ?? errors['buyers.0']
                                    }
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="save-buyer-access"
                                >
                                    Save access
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
