<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Enables validator auto-mapping support.
    // For instance, basic validation constraints will be inferred from Doctrine's metadata.
    // $container->extension('framework', ['validation' => ['auto_mapping' => ['App\Entity\\' => []]]]);

    if ($container->env() === 'test') {
        $container->extension('framework', [
            'validation' => [
                'not_compromised_password' => false,
            ],
        ]);
    }
};
