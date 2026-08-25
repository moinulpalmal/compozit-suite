<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Theme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppearanceUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Show the user's appearance settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/appearance', [
            'themes' => $this->themes(),
        ]);
    }

    /**
     * Update the user's theme.
     */
    public function update(AppearanceUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }

    /**
     * Every selectable theme, described for the picker.
     *
     * @return list<array{value: string, label: string, isDark: bool}>
     */
    private function themes(): array
    {
        return array_map(fn (Theme $theme): array => [
            'value' => $theme->value,
            'label' => $theme->label(),
            'isDark' => $theme->isDark(),
        ], Theme::themes());
    }
}
