<?php

namespace App\Providers;

use App\Models\Article;
use Exception;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'App\Http\Controllers';

    public function boot(Router $router)
    {
        $this->app->setLocale($this->app['currentLocale']->determine());

        $this->registerMacros($router);

        parent::boot($router);
    }

    public function map(Router $router)
    {
        $this->mapBackRoutes($router);
        $this->mapFrontRoutes($router);
    }

    protected function mapBackRoutes(Router $router)
    {
        $router->group(
            [
                'namespace' => $this->namespace.'\Back',
                'prefix' => 'blender',
            ],
            function ($router) {
                $router->group(['middelware' => 'auth:back'], function ($router) {
                    require app_path('Http/Routes/back.php');
                });

                require app_path('Http/Routes/back.auth.php');
            }
        );
    }

    protected function mapFrontRoutes(Router $router)
    {
        $router->group(
            [
                'namespace' => $this->namespace.'\Front',
                'middleware' => 'sanitizeInput',
            ],
            function ($router) {
                if (count(config('app.locales')) === 1) {
                    $this->requireFrontRoutes();
                    return;
                }

                $router->get('/', function () {
                    return redirect(config('app.locales')[0]);
                });

                $router->group(['prefix' => app()->getLocale()], function () {
                    $this->requireFrontRoutes();
                });
            }
        );
    }

    protected function requireFrontRoutes()
    {
        try {
            require app_path('Http/Routes/front.php');
            require app_path('Http/Routes/front.auth.php');
        } catch (Exception $exception) {
            logger()->warning('Front routes weren\'t included.');
        }
    }

    protected function registerMacros(Router $router)
    {
        $router->macro('redirect', function ($url, $action) use ($router) {
            $router->get($url, function () use ($action) {
                return redirect()->action($action);
            });
        });

        $router->macro('module', function ($slug, $className, $sortable = false) use ($router) {
            if ($sortable) {
                $router->patch("{$slug}/changeOrder", "{$className}Controller@changeOrder");
            }

            $router->resource($slug, "{$className}Controller");
        });

        $router->macro('articleList', function ($technicalNamePrefix, $action) use ($router) {

            $articles = Article::getWithTechnicalNameLike($technicalNamePrefix);

            $router->get(app()->getLocale().'/'.fragment_slug("navigation.{$technicalNamePrefix}"),  function () use ($articles) {
                return redirect(route("{$articles->first()->technical_name}"));
            })->name($technicalNamePrefix);

            $articles->map(function ($article) use ($technicalNamePrefix, $action, $router) {
                $router->get(app()->getLocale().'/'.fragment_slug("navigation.{$technicalNamePrefix}").'/'.$article->url, $action)->name("{$article->technical_name}");
            });
        });

        $this->app['paginateroute']->registerMacros();
    }
}
