<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class Services
{
    /**
     * The services registered with their stubs, persistence, and callbacks.
     *
     * @var array<int|string, string|array{stub: ?string, persistent: ?bool, default: ?bool, dependency: ?bool, env: array<string, string>|Closure|null, callback: ?Closure}>
     */
    protected array $services = [
        'mysql' => [
            'persistent' => true,
            'default' => true,
        ],
        'pgsql' => [
            'persistent' => true,
        ],
        'mariadb' => [
            'persistent' => true,
        ],
        'mongodb' => [
            'persistent' => true,
            'env' => [
                'MONGODB_URI' => 'mongodb://mongodb:27017',
                'MONGODB_DATABASE' => 'laravel',
            ],
        ],
        'redis' => [
            'persistent' => true,
            'default' => true,
            'env' => [
                'REDIS_HOST' => 'redis',
            ],
        ],
        'valkey' => [
            'persistent' => true,
            'env' => [
                'REDIS_HOST' => 'valkey',
            ],
        ],
        'memcached' => [
            'env' => [
                'MEMCACHED_HOST' => 'memcached',
            ],
        ],
        'meilisearch' => [
            'persistent' => true,
            'env' => [
                'SCOUT_DRIVER' => 'meilisearch',
                'MEILISEARCH_HOST' => 'http://meilisearch:7700',
                'MEILISEARCH_NO_ANALYTICS' => 'false',
            ],
        ],
        'typesense' => [
            'persistent' => true,
            'env' => [
                'SCOUT_DRIVER' => 'typesense',
                'TYPESENSE_HOST' => 'typesense',
                'TYPESENSE_PORT' => '8108',
                'TYPESENSE_PROTOCOL' => 'http',
                'TYPESENSE_API_KEY' => 'xyz',
            ],
        ],
        'minio' => [
            'persistent' => true,
        ],
        'mailpit' => [
            'default' => true,
            'env' => [
                'MAIL_MAILER' => 'smtp',
                'MAIL_HOST' => 'mailpit',
                'MAIL_PORT' => '1025',
            ],
        ],
        'selenium' => [
            'default' => true,
        ],
        'soketi' => [
            'env' => [
                'BROADCAST_DRIVER' => 'pusher',
                'PUSHER_APP_ID' => 'app-id',
                'PUSHER_APP_KEY' => 'app-key',
                'PUSHER_APP_SECRET' => 'app-secret',
                'PUSHER_HOST' => 'soketi',
                'PUSHER_PORT' => '6001',
                'PUSHER_SCHEME' => 'http',
                'VITE_PUSHER_HOST' => 'localhost',
            ],
        ],
    ];

    /**
     * The networks services communicating on
     *
     * @var array<string, array<string, string|bool>>
     */
    protected array $networks = [
        'sail' => [
            'driver' => 'bridge',
        ]
    ];

    /**
     * Path to the base docker compose template
     *
     * @var string
     */
    protected string $composeStub = __DIR__ . '/../stubs/docker-compose.stub';

    /**
     * Callbacks to be run after all services are configured
     *
     * @var Closure[]
     */
    protected array $preInstallCallbacks = [];

    /**
     * Callbacks to be run during the publish command
     *
     * @var Closure[]
     */
    protected array $postPublishCallbacks = [];

    public function __construct()
    {
        $uncommentDbVars = function (string $environment): string {
            $defaults = [
                '# DB_HOST=127.0.0.1',
                '# DB_PORT=3306',
                '# DB_DATABASE=laravel',
                '# DB_USERNAME=root',
                '# DB_PASSWORD=',
            ];
            foreach ($defaults as $default) {
                $environment = str_replace($default, substr($default, 2), $environment);
            }
            return $environment;
        };

        $setDbCredentials = function (string $environment): string {
            $environment = str_replace('DB_USERNAME=root', "DB_USERNAME=sail", $environment);
            return preg_replace("/DB_PASSWORD=(.*)/", "DB_PASSWORD=password", $environment);
        };

        $this->services['mysql']['env'] = function (string $environment) use ($uncommentDbVars, $setDbCredentials): string {
            $environment = $uncommentDbVars($environment);
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=mysql", $environment);
            return $setDbCredentials($environment);
        };

        $this->services['pgsql']['env'] = function (string $environment) use ($uncommentDbVars, $setDbCredentials): string {
            $environment = $uncommentDbVars($environment);
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=pgsql', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=pgsql", $environment);
            $environment = str_replace('DB_PORT=3306', "DB_PORT=5432", $environment);
            return $setDbCredentials($environment);
        };

        $this->services['mariadb']['env'] = function (string $environment) use ($uncommentDbVars, $setDbCredentials): string {
            $environment = $uncommentDbVars($environment);
            if (config()->has('database.connections.mariadb')) {
                $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mariadb', $environment);
            }
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=mariadb", $environment);
            return $setDbCredentials($environment);
        };
    }

    /**
     * Set the base Docker Compose template containing a php service named as 'APP_SERVICE'
     *
     * @param string $stub Path to the base Docker Compose stub
     * @return $this
     */
    public function setBaseTemplate(string $stub): self
    {
        $this->composeStub = $stub;

        return $this;
    }

    /**
     * Get the path to the base Docker Compose template
     *
     * @return string
     */
    public function baseTemplate(): string
    {
        return $this->composeStub;
    }

    /**
     * Register a new service with its Docker Compose stub.
     *
     * @param string $service
     * @param string|null $stub
     * @param bool|null $isPersistent
     * @param bool|null $isDefault
     * @param bool|null $isDependency
     * @param array<string, string>|Closure|null $env
     * @param Closure|null $preInstallCallback
     * @return self
     */
    public function addService(string   $service,
                               ?string  $stub = null,
                               ?bool    $isPersistent = null,
                               ?bool    $isDefault = null,
                               ?bool    $isDependency = null,
                               array|Closure|null $env = null,
                               ?Closure $preInstallCallback = null): self
    {
        $this->services[$service] = [
            'stub' => $stub ?? $this->services[$service]['stub'] ?? null,
            'persistent' => $isPersistent ?? $this->services[$service]['persistent'] ?? null,
            'default' => $isDefault ?? $this->services[$service]['default'] ?? null,
            'dependency' => $isDependency ?? $this->services[$service]['dependency'] ?? null,
            'env' => $env ?? $this->services[$service]['env'] ?? null,
            'callback' => $preInstallCallback ?? $this->services[$service]['callback'] ?? null,
        ];

        return $this;
    }

    /**
     * @param array<string, string|bool> $network
     * @return $this
     */
    public function addNetwork(array $network): self
    {
        $this->networks = array_merge($this->networks, $network);

        return $this;
    }

    /**
     * Add a callback to the pipeline executed during the installation command.
     *
     * @param Closure $callback
     * @return $this
     */
    public function addPreInstallCallback(Closure $callback): self
    {
        $this->preInstallCallbacks[] = $callback;

        return $this;
    }

    /**
     * Add a callback to the pipeline executed during the publish command.
     *
     * @param Closure $callback
     * @return $this
     */
    public function addPostPublishCallback(Closure $callback): self
    {
        $this->postPublishCallbacks[] = $callback;

        return $this;
    }

    /**
     * Get all available services, including defaults.
     *
     * @param bool $isDefault If true, returns only default services
     * @return array
     */
    public function availableServices(bool $isDefault = false): array
    {
        $services = [];
        foreach ($this->services as $key => $value) {
            $services[] = is_string($value) ? $value : $key;
        }

        if ($isDefault) {
            $defaults = [];
            foreach ($this->services as $key => $value) {
                if (is_array($value) && ($value['default'] ?? false)) {
                    $defaults[] = $key;
                }
            }
            return $defaults;
        }

        return $services;
    }

    /**
     * Get the list of networks defined for the docker-compose file
     *
     * @return array
     */
    public function networks(): array
    {
        return $this->networks;
    }

    /**
     * Get the stub path for a given service.
     *
     * @param string $service
     * @return string
     */
    public function stub(string $service): string
    {
        return $this->services[$service]['stub'] ?? __DIR__ . '/../stubs/'.$service.'.stub';
    }

    /**
     * Check if a service requires a persistent volume.
     *
     * @param string $service
     * @return bool
     */
    public function isPersistent(string $service): bool
    {
        return $this->services[$service]['persistent'] ?? false;
    }

    /**
     * Check if a service is required by APP_SERVICE
     *
     * @param string $service
     * @return bool
     */
    public function isDependency(string $service): bool
    {
        return $this->services[$service]['dependency'] ?? true;
    }

    /**
     * Add or replace environment variables.
     *
     * @param string $environment
     * @param array $services
     * @return string
     */
    public function configureEnv(string $environment, array $services): string
    {
        foreach ($services as $service) {
            if (!isset($this->services[$service]) || !is_array($this->services[$service])) {
                continue;
            }

            $envConfig = $this->services[$service]['env'] ?? null;

            if ($envConfig instanceof Closure) {
                $environment = $envConfig($environment);
                continue;
            }

            if (!is_array($envConfig)) {
                continue;
            }

            $lines = Str::of($environment)->rtrim()->explode("\n")->all();
            $newGroups = [];

            foreach ($envConfig as $key => $value) {
                $line = "$key=$value";
                $prefix = strtok($key, '_');

                if (Str::contains($environment, "$key=")) {
                    $lines = collect($lines)->map(fn($l) => Str::startsWith($l, "$key=") ? $line : $l)->all();
                } elseif (collect($lines)->first(fn($l) => Str::startsWith($l, "$prefix"))) {
                    $pos = collect($lines)->search(fn($l) => Str::startsWith($l, "$prefix")) + 1;
                    array_splice($lines, $pos, 0, $line);
                } else {
                    $newGroups[$prefix][] = $line;
                }
            }

            $environment = implode("\n", $lines);
            if ($newGroups) {
                $environment .= "\n\n" . collect($newGroups)->flatten()->implode("\n") . "\n";
            }
        }

        return rtrim($environment);
    }

    /**
     * Execute callbacks set for the installation command.
     *
     * @param Command $command
     * @param array $services
     * @param string $appService
     * @return $this
     */
    public function runPreInstallCallbacks(Command $command, array $services, string $appService = 'laravel.test'): self
    {
        foreach ($services as $service) {
            if (isset($this->services[$service]) && is_array($this->services[$service]) && ($this->services[$service]['callback'] ?? null) !== null) {
                $this->services[$service]['callback']($command, $services, $appService);
            }
        }
        foreach ($this->preInstallCallbacks as $callback) {
            $callback($command, $services, $appService);
        }

        return $this;
    }

    /**
     * Execute callbacks set for the publish command
     *
     * @param Command $command
     * @return $this
     */
    public function runPostPublishCallbacks(Command $command): self
    {
        foreach ($this->postPublishCallbacks as $callback) {
            $callback($command);
        }

        return $this;
    }
}
