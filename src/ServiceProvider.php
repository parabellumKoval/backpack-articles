<?php

namespace Backpack\Articles;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    const CONFIG_PATH = __DIR__ . '/config/articles.php';

    public function boot()
    {
      // Register articles namespace for review cards
      \Illuminate\Support\Facades\View::addNamespace('articles', __DIR__.'/resources/views');

      $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'articles');
    
	    // Migrations
	    $this->loadMigrationsFrom(__DIR__.'/database/migrations');
	    
	    // Routes
    	$this->loadRoutesFrom(__DIR__.'/routes/backpack/routes.php');
    	$this->loadRoutesFrom(__DIR__.'/routes/api/articles.php');
    
		  // Config
      $this->publishes([
        self::CONFIG_PATH => config_path('/backpack/articles.php'),
      ], 'config');
      
      $this->publishes([
          __DIR__.'/resources/views' => resource_path('views'),
      ], 'views');
  
      $this->publishes([
          __DIR__.'/database/migrations' => resource_path('database/migrations'),
      ], 'migrations');
  
      $this->publishes([
          __DIR__.'/routes/backpack/routes.php' => resource_path('/routes/backpack/articles/backpack.php'),
          __DIR__.'/routes/api/articles.php' => resource_path('/routes/backpack/articles/articles.php'),
      ], 'routes');

    }

    public function register()
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'articles'
        );

        $this->app->bind('article', function () {
            return new Article();
        });
    }
}
