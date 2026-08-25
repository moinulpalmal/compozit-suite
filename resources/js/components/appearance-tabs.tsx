import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { useThemeSetting } from '@/hooks/use-appearance';
import type { Theme } from '@/lib/themes';
import { cn } from '@/lib/utils';

const tabs: { value: Theme; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
];

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { theme, updateTheme } = useThemeSetting();

    return (
        <div className={cn('join', className)} {...props}>
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    aria-pressed={theme === value}
                    onClick={() => updateTheme(value)}
                    className={cn(
                        'btn join-item btn-sm',
                        theme === value ? 'btn-primary' : 'btn-ghost',
                    )}
                >
                    <Icon className="size-4" />
                    {label}
                </button>
            ))}
        </div>
    );
}
