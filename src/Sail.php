<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static self setBaseTemplate(string $stub)
 * @method static self addService(string $service, ?string $stub = null, ?bool $isPersistent = null, ?bool $isDefault = null, ?bool $isDependency = null, ?Closure $env = null, ?Closure $preInstallCallback = null)
 * @method static self addNetwork(array $network)
 * @method static self addPreInstallCallback(Closure $closure)
 * @method static self addPostPublishCallback(Closure $closure)
 * @method static string baseTemplate()
 * @method static array availableServices(bool $isDefault = false)
 * @method static array networks()
 * @method static string stub(string $service)
 * @method static bool isPersistent(string $service)
 * @method static bool isDependency(string $service)
 * @method static string configureEnv(string $environment, array $services)
 * @method static self runPreInstallCallbacks(mixed $command, array $services, string $appService = 'laravel.test')
 * @method static self runPostPublishCallbacks(mixed $command)
 */
class Sail extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return Services::class;
    }
}
