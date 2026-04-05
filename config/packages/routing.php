<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Configure how to generate URLs in non-HTTP contexts, such as CLI commands.
    // See https://symfony.com/doc/current/routing.html#generating-urls-in-commands
    $container->extension('framework', [
        'router' => [
            'default_uri' => '%env(DEFAULT_URI)%',
        ],
    ]);

    if ($container->env() === 'prod') {
        $container->extension('framework', [
            'router' => [
                'strict_requirements' => null,
            ],
        ]);
    }
};
