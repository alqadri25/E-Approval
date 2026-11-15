<?php

namespace App\Providers;

use App\Contracts\SheetRepositoryInterface;
use App\Repositories\SheetRepository;
use App\Services\GoogleClientService;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleClientService::class, function () {
            return new GoogleClientService();
        });

        $this->app->singleton(SheetRepositoryInterface::class, function ($app) {
            return new SheetRepository($app->make(GoogleClientService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. superadmin gate
        Gate::define('superadmin', function (User $user) {
            return $user->role === 'superadmin';
        });

        // 2. admin gate (superadmin & admin)
        Gate::define('admin', function (User $user) {
            return in_array($user->role, ['superadmin', 'admin']);
        });

        // 3. hr gate (superadmin, admin, hr)
        Gate::define('hr', function (User $user) {
            return in_array($user->role, ['superadmin', 'admin', 'hr']);
        });

        // 4. division-head gate
        Gate::define('div-head', function (User $user) {
            return in_array($user->role, ['superadmin', 'admin', 'division_head']);
        });

        // 5. department-head gate
        Gate::define('dept-head', function (User $user) {
            return in_array($user->role, ['superadmin', 'admin', 'department_head', 'division_head']);
        });

        // 6. section-head gate
        Gate::define('section-head', function (User $user) {
            return in_array($user->role, ['superadmin', 'admin', 'section_head']);
        });

        // 7. foreman gate
        Gate::define('foreman', function (User $user) {
            return in_array($user->role, ['superadmin', 'admin', 'foreman', 'ketua']);
        });

        // 8. General department access check
        Gate::define('access-department', function (User $user, ?string $departemen) {
            return $user->canAccessDepartment($departemen);
        });

        // 9. Sign Document Gate
        Gate::define('sign-document', function (User $user, $document, string $targetLevel) {
            if ($user->role === 'superadmin') {
                return true;
            }

            $docDept = is_array($document) ? ($document['departemen'] ?? null) : ($document->departemen ?? null);
            if ($docDept && !$user->canAccessDepartment($docDept)) {
                return false;
            }

            return match ($targetLevel) {
                'foreman' => in_array($user->role, ['foreman', 'ketua']),
                'section_head' => $user->role === 'section_head',
                'dept_head' => in_array($user->role, ['department_head', 'division_head']),
                default => false,
            };
        });
    }
}
