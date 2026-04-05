<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'asset_mapper' => [
            'paths' => ['assets/'],
            'missing_import_mode' => 'strict',
        ],
    ]);

    if ($container->env() === 'prod') {
        $container->extension('framework', [
            'asset_mapper' => [
                'missing_import_mode' => 'warn',
            ],
        ]);
    }
};
