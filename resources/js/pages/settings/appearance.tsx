import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import ThemePicker from '@/components/theme-picker';
import type { ThemeOption } from '@/lib/themes';
import { edit as editAppearance } from '@/routes/appearance';

type AppearanceProps = {
    themes: ThemeOption[];
};

export default function Appearance({ themes }: AppearanceProps) {
    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <div className="space-y-8">
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Appearance settings"
                        description="Update the appearance settings for your account"
                    />
                    <AppearanceTabs />
                </div>

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Theme"
                        description="Pick any of daisyUI's themes. Your choice is saved to your account."
                    />
                    <ThemePicker themes={themes} />
                </div>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
