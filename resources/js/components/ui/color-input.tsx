import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** A colour is valid to submit only in the form the server stores it in. */
const HEX = /^#[0-9A-F]{6}$/;

/**
 * A `#RRGGBB` colour, picked from the OS picker or typed as hex.
 *
 * **Only the text field carries `name`.** The `<input type="color">` beside it
 * is an unnamed visual control that writes into the text field's state. Every
 * form here is an uncontrolled `<Form {...submit}>` reading `name=` off native
 * elements, so two named inputs would submit the field twice and the last one
 * would win — a bug that stays invisible until the two disagree. This is the
 * same contract `combobox.tsx` meets with its hidden input; ARCHITECTURE.md
 * §8.5 states it for every compound control.
 *
 * Typing is normalised to uppercase as it goes, because uppercase is what the
 * database stores and what `Rule::unique` compares — the request normalises it
 * again server-side, since a client is never the place a constraint is enforced.
 *
 * **The stored colour is theme-blind, and deliberately so.** A hex chosen in
 * light mode is the same hex in dark mode; nothing re-evaluates it for contrast.
 * That is the accepted cost of arbitrary colour over a daisyUI semantic token,
 * and the swatch preview is what lets someone see the choice before saving it.
 * See documentation/settings.md §3.4.
 */
export default function ColorInput({
    id,
    name,
    defaultValue = '#3B82F6',
    required = false,
    invalid = false,
    className,
}: {
    id: string;
    name: string;
    defaultValue?: string;
    required?: boolean;
    invalid?: boolean;
    className?: string;
}) {
    const [value, setValue] = useState(defaultValue.toUpperCase());

    // The OS picker rejects anything that is not a full hex, so a half-typed
    // value keeps the last complete colour rather than throwing a DOM error.
    const [swatch, setSwatch] = useState(
        HEX.test(defaultValue.toUpperCase())
            ? defaultValue.toUpperCase()
            : '#3B82F6',
    );

    const apply = (next: string) => {
        const normalized = next.toUpperCase();

        setValue(normalized);

        if (HEX.test(normalized)) {
            setSwatch(normalized);
        }
    };

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <input
                type="color"
                value={swatch}
                onChange={(event) => apply(event.target.value)}
                aria-label="Pick a colour"
                className="h-9 w-12 shrink-0 cursor-pointer rounded-field border border-base-300 bg-base-100 p-1"
            />

            <Input
                id={id}
                name={name}
                value={value}
                onChange={(event) => apply(event.target.value)}
                // Blur is where a pasted `ff0000` gains its `#`, matching what
                // the request does, so the field never shows one thing and
                // submits another.
                onBlur={() =>
                    apply(
                        value !== '' && !value.startsWith('#')
                            ? `#${value}`
                            : value,
                    )
                }
                required={required}
                maxLength={7}
                autoComplete="off"
                spellCheck={false}
                placeholder="#FF0000"
                aria-invalid={invalid || undefined}
                className="font-mono"
            />
        </div>
    );
}
