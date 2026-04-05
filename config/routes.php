<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    // Methods with the #[Route] attribute are automatically imported.
    // See also https://symfony.com/doc/current/routing.html
    $routes->import('routing.controllers');
};
