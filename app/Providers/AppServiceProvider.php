<?php

namespace App\Providers;

use App\Faker\MexicanSpanishProvider;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The fake() helper resolves Faker\Generator under a locale-suffixed key, so
     * binding that key is what lets APP_FAKER_LOCALE=es_MX resolve to a locale
     * FakerPHP does not actually ship.
     */
    public function register(): void
    {
        $this->app->singleton(Generator::class.':es_MX', function (): Generator {
            $faker = FakerFactory::create('es_ES');
            $faker->addProvider(new MexicanSpanishProvider($faker));

            return $faker;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
