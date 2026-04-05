<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Namespace is arbitrary but should be different from App\Migrations
    // as migrations classes should NOT be autoloaded
    $container->extension('doctrine_migrations', [
        'migrations_paths' => [
            'DoctrineMigrations' => '%kernel.project_dir%/migrations',
        ],
        'enable_profiler' => false,
    ]);
};
