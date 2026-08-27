import { Trash2 } from 'lucide-react';
import ConfirmActionDialog from '@/components/admin/confirm-action-dialog';
import { Button } from '@/components/ui/button';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Icon button that asks before submitting a destructive Wayfinder form.
 *
 * The destructive preset over {@link ConfirmActionDialog} — reach for that one
 * directly when the action is not a deletion.
 */
export default function ConfirmDeleteDialog({
    submit,
    title,
    description,
    confirmLabel = 'Delete',
    disabled = false,
    testId,
}: {
    submit: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    confirmLabel?: string;
    disabled?: boolean;
    testId?: string;
}) {
    return (
        <ConfirmActionDialog
            submit={submit}
            title={title}
            description={description}
            confirmLabel={confirmLabel}
            confirmVariant="destructive"
            disabled={disabled}
            testId={testId}
        >
            <Button
                variant="ghost"
                size="icon"
                disabled={disabled}
                aria-label={title}
                data-test={testId}
            >
                <Trash2 className="text-error" />
            </Button>
        </ConfirmActionDialog>
    );
}
