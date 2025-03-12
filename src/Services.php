<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Console\Command;

class Services
{
    /**
     * The services registered with their stubs, persistence, and callbacks.
     *
     * @var array<int|string, string|array{stub: ?string, persistent: ?bool, default: ?bool, dependency: ?bool, env: ?Closure, callback: ?Closure}>
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
        ],
        'redis' => [
            'persistent' => true,
            'default' => true,
        ],
        'valkey' => [
            'persistent' => true,
        ],
        'memcached',
        'meilisearch' => [
            'persistent' => true,
        ],
        'typesense' => [
            'persistent' => true,
        ],
        'minio' => [
            'persistent' => true,
        ],
        'mailpit' => [
            'default' => true,
        ],
        'selenium' => [
            'default' => true,
        ],
        'soketi',
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

        $this->services['mongodb']['env'] = function (string $environment): string {
            $environment .= "\nMONGODB_URI=mongodb://mongodb:27017";
            $environment .= "\nMONGODB_DATABASE=laravel";
            return $environment;
        };

        $this->services['redis']['env'] = function (string $environment): string {
            return str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);
        };

        $this->services['valkey']['env'] = function (string $environment): string {
            return str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=valkey', $environment);
        };

        $this->services['memcached']['env'] = function (string $environment): string {
            return str_replace('MEMCACHED_HOST=127.0.0.1', 'MEMCACHED_HOST=memcached', $environment);
        };

        $this->services['meilisearch']['env'] = function (string $environment): string {
            $environment .= "\nSCOUT_DRIVER=meilisearch";
            $environment .= "\nMEILISEARCH_HOST=http://meilisearch:7700\n";
            $environment .= "\nMEILISEARCH_NO_ANALYTICS=false\n";
            return $environment;
        };

        $this->services['typesense']['env'] = function (string $environment): string {
            $environment .= "\nSCOUT_DRIVER=typesense";
            $environment .= "\nTYPESENSE_HOST=typesense";
            $environment .= "\nTYPESENSE_PORT=8108";
            $environment .= "\nTYPESENSE_PROTOCOL=http";
            $environment .= "\nTYPESENSE_API_KEY=xyz\n";
            return $environment;
        };

        $this->services['mailpit']['env'] = function (string $environment): string {
            $environment = preg_replace("/^MAIL_MAILER=(.*)/m", "MAIL_MAILER=smtp", $environment);
            $environment = preg_replace("/^MAIL_HOST=(.*)/m", "MAIL_HOST=mailpit", $environment);
            return preg_replace("/^MAIL_PORT=(.*)/m", "MAIL_PORT=1025", $environment);
        };

        $this->services['soketi']['env'] = function (string $environment): string {
            $environment = preg_replace("/^BROADCAST_DRIVER=(.*)/m", "BROADCAST_DRIVER=pusher", $environment);
            $environment = preg_replace("/^PUSHER_APP_ID=(.*)/m", "PUSHER_APP_ID=app-id", $environment);
            $environment = preg_replace("/^PUSHER_APP_KEY=(.*)/m", "PUSHER_APP_KEY=app-key", $environment);
            $environment = preg_replace("/^PUSHER_APP_SECRET=(.*)/m", "PUSHER_APP_SECRET=app-secret", $environment);
            $environment = preg_replace("/^PUSHER_HOST=(.*)/m", "PUSHER_HOST=soketi", $environment);
            $environment = preg_replace("/^PUSHER_PORT=(.*)/m", "PUSHER_PORT=6001", $environment);
            $environment = preg_replace("/^PUSHER_SCHEME=(.*)/m", "PUSHER_SCHEME=http", $environment);
            return preg_replace("/^VITE_PUSHER_HOST=(.*)/m", "VITE_PUSHER_HOST=localhost", $environment);
        };

        $this->postPublishCallbacks[] = function (Command $command) {
            file_put_contents(
                app()->basePath('docker-compose.yml'),
                str_replace(
                    [
                        './vendor/laravel/sail/runtimes/8.4',
                        './vendor/laravel/sail/runtimes/8.3',
                        './vendor/laravel/sail/runtimes/8.2',
                        './vendor/laravel/sail/runtimes/8.1',
                        './vendor/laravel/sail/runtimes/8.0',
                        './vendor/laravel/sail/database/mysql',
                        './vendor/laravel/sail/database/pgsql'
                    ],
                    [
                        './docker/8.4',
                        './docker/8.3',
                        './docker/8.2',
                        './docker/8.1',
                        './docker/8.0',
                        './docker/mysql',
                        './docker/pgsql'
                    ],
                    file_get_contents(app()->basePath('docker-compose.yml'))
                )
            );
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
     * @param Closure|null $env
     * @param Closure|null $preInstallCallback
     * @return self
     */
    public function addService(string   $service,
                               ?string  $stub = null,
                               ?bool    $isPersistent = null,
                               ?bool    $isDefault = null,
                               ?bool    $isDependency = null,
                               ?Closure $env = null,
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
            if (isset($this->services[$service]) && is_array($this->services[$service]) && ($this->services[$service]['env'] ?? null) !== null) {
                $environment = $this->services[$service]['env']($environment);
            }
        }

        return $environment;
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
