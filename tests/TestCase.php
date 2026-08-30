<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create the application, refusing to run against a real database.
     *
     * `RefreshDatabase` runs `migrate:fresh` — it drops every table. It is
     * therefore safe only against a throwaway database, and this asserts that
     * before it gets the chance: the framework calls `createApplication()` from
     * `refreshApplication()`, which runs *before* `setUpTraits()` boots the
     * trait, so a wrong connection is caught while there is still a database to
     * protect.
     *
     * The guard exists because the suite has already destroyed the development
     * database once. A cached `bootstrap/cache/config.php` makes `phpunit.xml`
     * inert — `LoadConfiguration` never reads the environment — so the sqlite
     * connection it declares silently becomes MySQL. `tests/Pest.php` removes
     * that file before anything boots; this is the backstop for every other way
     * the connection could be wrong, including an `.env` override or a future
     * bootstrapper.
     *
     * It is deliberately strict about what `phpunit.xml` declares. Moving the
     * suite onto a real database has to be a conscious edit of this method, not
     * a config change that quietly re-arms the hazard.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(sprintf(
                'Refusing to run tests on connection [%s] (database "%s"). RefreshDatabase drops '
                .'every table, and phpunit.xml declares sqlite / :memory:. Something is overriding '
                .'it — usually a cached config, so run `php artisan config:clear`.',
                $connection,
                is_string($database) ? $database : 'unknown',
            ));
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
