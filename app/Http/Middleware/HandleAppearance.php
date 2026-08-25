<?php

namespace App\Http\Middleware;

use App\Enums\Theme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $theme = Theme::forRequest($request);

        View::share('theme', $theme->value);
        // `system` cannot be resolved server side; the inline script corrects it before paint.
        View::share('resolvedTheme', $theme->resolve()->value);
        View::share('themeIsDark', $theme->isDark());
        View::share('darkThemes', array_map(
            fn (Theme $dark): string => $dark->value,
            Theme::darkThemes(),
        ));

        return $next($request);
    }
}
