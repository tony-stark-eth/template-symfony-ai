<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'notifier' => [
            'chatter_transports' => [
                'default' => '%env(NOTIFIER_CHATTER_DSN)%',
            ],
        ],
    ]);
};
