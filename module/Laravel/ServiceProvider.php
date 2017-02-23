<?php

namespace Dropcart\Laravel;

use Dropcart\Client;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

class ServiceProvider extends LaravelServiceProvider
{
	/**
	 * Indicates if loading of the provider if deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * What to do on boot.
	 *
	 */
	public function boot()
	{
		$source = realpath(__DIR__ . '/dropcart.php');

		// Check if the application is a Laravel OR Lumen instance to properly merge the configuration file.
		if ($this->app instanceof LaravelApplication && $this->app->runningInConsole())
		{
			$this->publishes([$source => config_path('dropcart.php')]);
		}
		elseif ($this->app instanceof LumenApplication)
		{
			$this->app->configure('dropcart');
		}

		$this->mergeConfigFrom($source, 'dropcart');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton('dropcart', function (Container $app) {
			Client::setEndpoint($app->config->get('dropcart.endpoint'));
			$client = Client::instance();
			$client->auth($app->config->get('dropcart.key'), $app->config->get('dropcart.secret'), $app->config->get('dropcart.country'));

			return $client;
		});

		$this->app->alias('dropcart', Client::class);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return [
			'dropcart',
		];
	}

}