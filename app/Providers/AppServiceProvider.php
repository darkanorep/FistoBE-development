<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Http\Resources\ChargingResource;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Builder::macro('whereLike', function ($attributes, ?string $searchTerm) {
            if ($searchTerm === null) {
                return $this;
            }

            $this->where(function (Builder $query) use ($attributes, $searchTerm) {
                foreach (Arr::wrap($attributes) as $attribute) {
                    $query->when(
                        str_contains($attribute, '.'),
                        function (Builder $query) use ($attribute, $searchTerm) {
                            [$relationName, $relationAttribute] = explode('.', $attribute);

                            $query->orWhereHas($relationName, function (Builder $query) use ($relationAttribute, $searchTerm) {
                                $query->where($relationAttribute, 'LIKE', "%{$searchTerm}%");
                            });
                        },
                        function (Builder $query) use ($attribute, $searchTerm) {
                            $query->orWhere($attribute, 'LIKE', "%{$searchTerm}%");
                        }
                    );
                }
            });

            return $this;
        });


//        ChargingResource::withoutWrapping();
//        Collection::macro('paginateme', function($perPage, $total = null, $page = null, $pageName = 'page') {
//            $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);
//            return new LengthAwarePaginator(
//                $this->forPage($page, $perPage),
//                $total ?: $this->count(),
//                $perPage,
//                $page,
//                [
//                    'path' => LengthAwarePaginator::resolveCurrentPath(),
//                    'pageName' => $pageName,
//                ]
//            );
//        });
    }
}
