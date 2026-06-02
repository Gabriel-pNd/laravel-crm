<?php

namespace Webkul\NeuroFlow\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\NeuroFlow\Application\Actions\GetRealtimeOperationState;
use Webkul\NeuroFlow\Application\Presenters\RealtimeOperationPresenter;
use Webkul\NeuroFlow\Domain\Contracts\ActiveClinicMemberships;
use Webkul\NeuroFlow\Domain\Contracts\RealtimeOperationReadModel;
use Webkul\NeuroFlow\Http\Middleware\ResolveActiveClinic;
use Webkul\NeuroFlow\Infrastructure\Supabase\SupabaseActiveClinicMemberships;
use Webkul\NeuroFlow\Infrastructure\Supabase\SupabaseRealtimeOperationReadModel;

class NeuroFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/neuroflow.php', 'neuroflow');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->app->bind(RealtimeOperationReadModel::class, SupabaseRealtimeOperationReadModel::class);
        $this->app->bind(ActiveClinicMemberships::class, SupabaseActiveClinicMemberships::class);
        $this->app->bind(GetRealtimeOperationState::class);
        $this->app->bind(RealtimeOperationPresenter::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('neuroflow.active_clinic', ResolveActiveClinic::class);

        Route::middleware(['web', 'admin_locale', 'user', 'neuroflow.active_clinic'])
            ->prefix(config('app.admin_path'))
            ->group(dirname(__DIR__).'/Routes/web.php');

        Route::middleware(['web', 'admin_locale', 'user', 'neuroflow.active_clinic'])
            ->prefix(config('app.admin_path').'/neuroflow/api')
            ->group(dirname(__DIR__).'/Routes/api.php');

        $this->loadViewsFrom(dirname(__DIR__).'/Resources/views', 'neuroflow');
        $this->loadTranslationsFrom(dirname(__DIR__).'/Resources/lang', 'neuroflow');
    }
}
