import { useCallback, useId, useRef, useState } from 'react';

/** Which of the two save buttons was pressed. */
export type SaveIntent = 'add-another' | 'close';

/**
 * The behaviour half of the form-modal standard — ARCHITECTURE.md §8.10.
 *
 * Pair it with `components/shared/form-dialog-footer.tsx`, which renders the
 * three buttons and calls `setIntent` from each one's `onClick`:
 *
 * ```tsx
 * const close = useCallback(() => setOpen(false), []);
 * const { formKey, formProps, setIntent } = useFormDialog(close);
 *
 * <Form key={formKey} {...submit} {...formProps} options={{ preserveScroll: true }}>
 * ```
 *
 * With those in place the contract holds:
 *
 * - **Save & add another** — on success only, the form clears and the panel stays
 *   open for the next record.
 * - **Save & close** — on success only, the form clears and the panel closes.
 * - Either one, on failure — the error shows and the panel stays open, untouched.
 *
 * **Clearing is a remount, not `reset()`.** `resetOnSuccess` and the slot's
 * `reset()` write `el.value` straight onto the named DOM nodes, which is wrong for
 * every control in this application that is React-controlled behind its `name` —
 * `combobox.tsx` submits through a hidden input and `color-input.tsx` through a
 * controlled text field, so React would repaint its own state over the write and
 * the visible control would disagree with what is submitted. It also cannot clear
 * a file input at all. Bumping `key` unmounts and remounts the subtree instead:
 * every `defaultValue` re-seeds, every `useState` inside re-initialises, every file
 * input empties. It is the same mechanism `DialogContent` uses when it closes,
 * driven by a key rather than by closing.
 *
 * Note what "clear" therefore means: **back to the seed, not empty.** On a create
 * modal the seed is blank; on an edit modal it is the row the server holds.
 *
 * **The intent never reaches the server.** `<Form>` builds its payload with
 * `new FormData(element, submitter)`, so a `name`d submit button would post an
 * extra field that every form request would then need a rule to ignore. It lives
 * in a ref, written by the button's `onClick`, which fires before submit — on a
 * click *and* on Enter, since implicit submission activates the button too.
 *
 * `close` is a callback rather than state owned here, so the same hook serves the
 * dialogs that own their `open` and the two whose pages own it.
 */
export function useFormDialog(close: () => void): {
    /**
     * The remount key. Passed to `<Form>` **explicitly**, never through the
     * spread below: React 19 warns when a `key` arrives inside a props object,
     * and that warning would fail `assertNoJavaScriptErrors()` in the browser
     * suite.
     */
    formKey: number;
    formProps: {
        id: string;
        onSuccess: () => void;
        onError: (errors: Record<string, string>) => void;
    };
    setIntent: (intent: SaveIntent) => void;
} {
    const [formKey, setFormKey] = useState(0);
    const formId = useId();

    // A ref, not state: it is read inside `onSuccess` and nothing renders from
    // it, so re-rendering on every click would be pure churn.
    const intent = useRef<SaveIntent>('close');

    const setIntent = useCallback((next: SaveIntent) => {
        intent.current = next;
    }, []);

    const onSuccess = useCallback(() => {
        if (intent.current === 'add-another') {
            setFormKey((current) => current + 1);

            return;
        }

        close();
    }, [close]);

    const onError = useCallback(
        (errors: Record<string, string>) => {
            focusFirstError(formId, errors);
        },
        [formId],
    );

    return {
        formKey,
        formProps: { id: formId, onSuccess, onError },
        setIntent,
    };
}

/**
 * Move focus to the first control the server rejected, and scroll it into view.
 *
 * Hand-rolled per form until now — `delete-user.tsx` and `settings/security.tsx`
 * each keep their own ref for it. On a modal the size of the user form the message
 * alone is not enough: it can be several fields above the fold, and the reader is
 * left looking at a panel that simply did not close.
 *
 * The form is found by `id` rather than by ref because `<Form>` overwrites the
 * `ref` it is given with its own; unknown props reach the `<form>` element, so an
 * `id` is the one handle a caller can keep.
 *
 * **`#id` is tried before `name`, and the order matters.** A `Combobox` carries
 * the caller's `id` on its trigger `<button>` while its `name` belongs to a hidden
 * input, which cannot take focus. A nested key from a repeater
 * (`colors.0.max_days_remaining`) matches neither, and that is fine — the message
 * still renders; do not synthesise ids to make it match.
 */
function focusFirstError(formId: string, errors: Record<string, string>): void {
    const field = Object.keys(errors)[0];

    if (field === undefined || typeof document === 'undefined') {
        return;
    }

    const form = document.getElementById(formId);

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const byId = form.querySelector<HTMLElement>(`#${CSS.escape(field)}`);
    const named = form.elements.namedItem(field);

    const target =
        byId ??
        (named instanceof HTMLElement
            ? named
            : named instanceof RadioNodeList
              ? (named[0] ?? null)
              : null);

    // A `Combobox` with no `id` falls through to its hidden input, which cannot
    // take focus — better to leave focus alone than to move it nowhere.
    if (
        target === null ||
        (target instanceof HTMLInputElement && target.type === 'hidden')
    ) {
        return;
    }

    target.focus({ preventScroll: true });
    target.scrollIntoView({ block: 'center', behavior: 'smooth' });
}
