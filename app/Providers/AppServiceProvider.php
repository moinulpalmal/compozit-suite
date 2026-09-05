<?php

namespace App\Providers;

use App\Models\Admin\Buyer;
use App\Models\Admin\Designation;
use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\Merchandising\BqsColourLink;
use App\Models\Merchandising\BqsImport;
use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsRowMonth;
use App\Models\Merchandising\BqsRowPackSize;
use App\Models\Merchandising\BqsSheet;
use App\Models\Merchandising\DocumentFile;
use App\Models\Merchandising\DocumentUpload;
use App\Models\Merchandising\PoImport;
use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use App\Models\Settings\NotificationColor;
use App\Models\Settings\TnaTemplate;
use App\Models\Settings\TnaTemplateColor;
use App\Models\Settings\TnaTemplateMilestone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Every model that may appear in a polymorphic column, by its stored alias.
     *
     * Aliases are kebab-case singular, matching the URL segment conventions in
     * ARCHITECTURE.md §6.2 rather than the class name — the whole point is that
     * the stored value does not track the class.
     *
     * **This map is also the audit browser's model allow-list.** The controller
     * ships `array_keys(Relation::morphMap())` to the front end and validates the
     * record-history request against it, so a client never names a class and the
     * list can never drift from what is actually auditable. The reference
     * implementation this was ported from maintained that list by hand and it had
     * drifted to 18 of 32 models.
     *
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    public const array MORPH_MAP = [
        'user' => User::class,

        'buyer' => Buyer::class,
        'designation' => Designation::class,
        'permission' => Permission::class,
        'role' => Role::class,

        'bqs-colour-link' => BqsColourLink::class,
        'bqs-import' => BqsImport::class,
        'bqs-row' => BqsRow::class,
        'bqs-row-month' => BqsRowMonth::class,
        'bqs-row-pack-size' => BqsRowPackSize::class,
        'bqs-sheet' => BqsSheet::class,
        'document-file' => DocumentFile::class,
        'document-upload' => DocumentUpload::class,
        'po-import' => PoImport::class,
        'po-line-item' => PoLineItem::class,
        'purchase-order' => PurchaseOrder::class,

        'notification-color' => NotificationColor::class,
        'tna-template' => TnaTemplate::class,
        'tna-template-color' => TnaTemplateColor::class,
        'tna-template-milestone' => TnaTemplateMilestone::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMorphMap();
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Store polymorphic types as short aliases rather than class names.
     *
     * `audit_logs.auditable_type` is the reason this exists: without a map, moving
     * or renaming a model orphans its entire history, and ARCHITECTURE.md §12
     * treats a module being renamed or re-scoped as an expected event rather than
     * an exceptional one.
     *
     * **`enforceMorphMap`, not `morphMap`.** The enforcing variant throws when an
     * unmapped model is used in a morph, so a model added later fails loudly on
     * its first audit instead of quietly writing a class name that the next rename
     * will orphan. Adding a model to {@see self::MORPH_MAP} is part of adding a
     * model.
     *
     * The map is global and therefore not only about audits:
     * `spatie/laravel-permission` writes `model_has_roles.model_type` through the
     * same `getMorphClass()`. Registering this without rewriting those rows drops
     * every user's roles — see
     * `database/migrations/2026_09_05_051424_add_morph_map_to_permission_tables.php`,
     * which is the other half of this method and must be run with it.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap(self::MORPH_MAP);
    }

    /**
     * Grant the super-admin role every ability.
     *
     * Returning null rather than false leaves every other check untouched, so
     * a normal user still falls through to policies and permission checks.
     *
     * @see Role::SUPER_ADMIN
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SUPER_ADMIN) ? true : null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults($this->passwordPolicy(...));
    }

    /**
     * The application's password policy, assembled from `config/auth.php`.
     *
     * There is no environment branch here, and that is the point. This used to
     * read `app()->isProduction() ? Password::min(12)->… : null`, which meant
     * `Password::default()` fell through to Laravel's bare `min(8)` everywhere
     * else — so the whole test suite exercised a policy the application never
     * enforces, and any test asserting on password strength proved nothing.
     *
     * @see config('auth.password_policy')
     */
    protected function passwordPolicy(): Password
    {
        /** @var array{min_length: int, mixed_case: bool, letters: bool, numbers: bool, symbols: bool, uncompromised: bool} $policy */
        $policy = config('auth.password_policy');

        $rule = Password::min($policy['min_length']);

        if ($policy['mixed_case']) {
            $rule->mixedCase();
        }

        if ($policy['letters']) {
            $rule->letters();
        }

        if ($policy['numbers']) {
            $rule->numbers();
        }

        if ($policy['symbols']) {
            $rule->symbols();
        }

        if ($policy['uncompromised']) {
            $rule->uncompromised();
        }

        return $rule;
    }
}
