<?php

namespace Tests;

use GraphQL\Error\Debug;
use GraphQL\Error\DebugFlag;
use GraphQL\Type\Schema;
use Hawk\LighthouseExtended\LighthouseExtendedServiceProvider;
use Hawk\LighthouseExtended\Traits\TestGraphql;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\Pagination\PaginationServiceProvider;
use Nuwave\Lighthouse\Support\AppVersion;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Tester\CommandTester;

class TestCase extends Orchestra
{
    use TestGraphql,
        MakesGraphQLRequests,
        MocksResolvers,
        UsesTestSchema;

    /**
     * A dummy query type definition that is added to tests by default.
     */
    public const PLACEHOLDER_QUERY = /** @lang GraphQL */ <<<'GRAPHQL'
type Query {
  foo: Int
}

GRAPHQL;

    public function setUp(): void
    {
        parent::setUp();

        if ($this->schema === null) {
            $this->schema = self::PLACEHOLDER_QUERY;
        }

        $this->setUpTestSchema();

        //$this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->withFactories(__DIR__.'/database/factories');

        // We have to use this instead of --realpath as long as Laravel 5.5 is supported
        $this->app->setBasePath(__DIR__);
        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            LighthouseServiceProvider::class,
            LighthouseExtendedServiceProvider::class,
            PermissionServiceProvider::class,
            PaginationServiceProvider::class,
            //SoftDeletesServiceProvider::class,
            //ValidationServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make(ConfigRepository::class);

        $config->set('lighthouse.namespaces', [
            'models' => [
                'Tests\\Utils\\Models',
                'Tests\\Utils\\ModelsSecondary',
            ],
            'queries' => [
                'Tests\\Utils\\Queries',
                'Tests\\Utils\\QueriesSecondary',
            ],
            'mutations' => [
                'Tests\\Utils\\Mutations',
                'Tests\\Utils\\MutationsSecondary',
            ],
            'subscriptions' => [
                'Tests\\Utils\\Subscriptions',
            ],
            'interfaces' => [
                'Tests\\Utils\\Interfaces',
                'Tests\\Utils\\InterfacesSecondary',
            ],
            'scalars' => [
                'Tests\\Utils\\Scalars',
                'Tests\\Utils\\ScalarsSecondary',
            ],
            'unions' => [
                'Tests\\Utils\\Unions',
                'Tests\\Utils\\UnionsSecondary',
            ],
            'directives' => [
                'Tests\\Utils\\Directives',
            ],
            'validators' => [
                'Tests\\Utils\\Validators',
            ],
        ]);

        $config->set('app.debug', true);
        $config->set('lighthouse.debug',
            DebugFlag::INCLUDE_DEBUG_MESSAGE
            | DebugFlag::INCLUDE_TRACE
            | DebugFlag::RETHROW_INTERNAL_EXCEPTIONS
            | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS
        );

        $config->set('lighthouse.guard', null);

        $config->set('lighthouse.subscriptions', [
            'version' => 1,
            'storage' => 'array',
            'broadcaster' => 'log',
        ]);

        $config->set('database.redis.default', [
            'url' => env('LIGHTHOUSE_TEST_REDIS_URL'),
            'host' => env('LIGHTHOUSE_TEST_REDIS_HOST', 'redis'),
            'password' => env('LIGHTHOUSE_TEST_REDIS_PASSWORD'),
            'port' => env('LIGHTHOUSE_TEST_REDIS_PORT', '6379'),
            'database' => env('LIGHTHOUSE_TEST_REDIS_DB', '0'),
        ]);

        $config->set('database.redis.options', [
            'prefix' => 'lighthouse-test-',
        ]);

        $config->set('database.connections.mysql', [
            'driver' => 'mysql',
            'database' => env('LIGHTHOUSE_TEST_DB_DATABASE', 'test'),
            'username' => env('LIGHTHOUSE_TEST_DB_USERNAME', 'root'),
            'password' => env('LIGHTHOUSE_TEST_DB_PASSWORD', ''),
            'host' => env('LIGHTHOUSE_TEST_DB_HOST', 'mysql'),
            'port' => env('LIGHTHOUSE_TEST_DB_PORT', '3306'),
            'unix_socket' => env('LIGHTHOUSE_TEST_DB_UNIX_SOCKET', null),
        ]);

        // Defaults to "algolia", which is not needed in our test setup
        $config->set('scout.driver', null);
    }

    /**
     * Convenience method to get a default Query, sometimes needed
     * because the Schema is invalid without it.
     *
     * @return string
     * @deprecated
     */
    protected function placeholderQuery(): string
    {
        return '
        type Query {
            foo: Int
        }
        ';
    }

    /**
     * Rethrow all errors that are not handled by GraphQL.
     *
     * This makes debugging the tests much simpler as Exceptions
     * are fully dumped to the console when making requests.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function resolveApplicationExceptionHandler($app): void
    {
        $app->singleton(ExceptionHandler::class, function () {
            if (AppVersion::atLeast(7.0)) {
                return new Laravel7ExceptionHandler();
            }

            return new PreLaravel7ExceptionHandler();
        });
    }

    /**
     * Build an executable schema from a SDL string, adding on a default Query type.
     */
    protected function buildSchemaWithPlaceholderQuery(string $schema): Schema
    {
        return $this->buildSchema(
            $schema.self::PLACEHOLDER_QUERY
        );
    }

    /**
     * Build an executable schema from an SDL string.
     */
    protected function buildSchema(string $schema): Schema
    {
        $this->schema = $schema;

        return $this->app
            ->make(GraphQL::class)
            ->prepSchema();
    }

    /**
     * Get a fully qualified reference to a method that is defined on the test class.
     */
    protected function qualifyTestResolver(string $method = 'resolve'): string
    {
        return addslashes(static::class).'@'.$method;
    }

    /**
     * Construct a command tester.
     */
    protected function commandTester(Command $command): CommandTester
    {
        $command->setLaravel($this->app);

        return new CommandTester($command);
    }
}
