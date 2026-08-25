import { Check } from 'lucide-react';
import { useThemeSetting } from '@/hooks/use-appearance';
import type { ThemeOption } from '@/lib/themes';
import { cn } from '@/lib/utils';

type ThemePickerProps = {
    themes: ThemeOption[];
};

/**
 * Every daisyUI theme, previewed live.
 *
 * Each swatch carries its own `data-theme`, so daisyUI's nested theme support
 * renders the real surface and brand colours without a single hard-coded value.
 */
export default function ThemePicker({ themes }: ThemePickerProps) {
    const { theme: current, updateTheme } = useThemeSetting();

    const groups = [
        { label: 'Light', options: themes.filter((item) => !item.isDark) },
        { label: 'Dark', options: themes.filter((item) => item.isDark) },
    ];

    return (
        <div className="space-y-8">
            {groups.map((group) => (
                <section key={group.label} className="space-y-3">
                    <h3 className="text-xs font-semibold tracking-wide text-base-content/60 uppercase">
                        {group.label}
                    </h3>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {group.options.map((option) => (
                            <ThemeSwatch
                                key={option.value}
                                option={option}
                                isSelected={current === option.value}
                                onSelect={() => updateTheme(option.value)}
                            />
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}

type ThemeSwatchProps = {
    option: ThemeOption;
    isSelected: boolean;
    onSelect: () => void;
};

function ThemeSwatch({ option, isSelected, onSelect }: ThemeSwatchProps) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={isSelected}
            className={cn(
                'cursor-pointer rounded-box border-2 p-1 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
                isSelected
                    ? 'border-primary'
                    : 'border-base-300 hover:border-base-content/30',
            )}
        >
            <div
                data-theme={option.value}
                className="flex flex-col gap-2 rounded-field bg-base-100 p-3 text-left"
            >
                <div className="flex items-center gap-1">
                    <span className="size-4 rounded-sm bg-primary" />
                    <span className="size-4 rounded-sm bg-secondary" />
                    <span className="size-4 rounded-sm bg-accent" />
                    <span className="size-4 rounded-sm bg-neutral" />

                    {isSelected && (
                        <Check className="ml-auto size-4 text-base-content" />
                    )}
                </div>

                <span className="truncate text-sm font-medium text-base-content">
                    {option.label}
                </span>
            </div>
        </button>
    );
}
