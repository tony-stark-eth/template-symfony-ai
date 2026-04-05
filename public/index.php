<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    /** @var string $appEnv */
    $appEnv = $context['APP_ENV'];

    return new Kernel($appEnv, (bool) $context['APP_DEBUG']);
};
