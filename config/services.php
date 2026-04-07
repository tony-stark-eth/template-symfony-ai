<?php

declare(strict_types=1);

use App\Shared\AI\Command\AiSmokeTestCommand;
use App\Shared\AI\Platform\ModelFailoverPlatform;
use App\Shared\AI\Service\ModelDiscoveryService;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Default configuration for services in this file
    $services->defaults()
        ->autowire(true)      // Automatically injects dependencies in your services.
        ->autoconfigure(true); // Automatically registers your services as commands, event subscribers, etc.

    // Makes classes in src/ available to be used as services.
    // This creates a service per class whose id is the fully-qualified class name.
    $services->load('App\\', '../src/')
        ->exclude([
            '../src/Entity/',
            '../src/*/Entity/',
            '../src/Kernel.php',
        ]);

    // Register openrouter/free router in the model catalog (not included by default)
    $services->set('ai.platform.model_catalog.openrouter', ModelCatalog::class)
        ->arg('$additionalModels', [
            'openrouter/free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
        ]);

    // Model failover platform: openrouter/free -> specific :free models -> optional paid fallback -> exception
    // Wraps the OpenRouter platform with model-level failover (complements FailoverPlatform's platform-level failover)
    $services->set('ai.platform.openrouter.failover', ModelFailoverPlatform::class)
        ->arg('$innerPlatform', service('ai.platform.openrouter'))
        ->arg('$fallbackModels', [
            'minimax/minimax-m2.5:free',
            'z-ai/glm-4.5-air:free',
            'openai/gpt-oss-120b:free',
            'qwen/qwen3.6-plus:free',
            'nvidia/nemotron-3-super-120b-a12b:free',
        ])
        ->arg('$paidFallbackModel', '%env(string:OPENROUTER_PAID_FALLBACK_MODEL)%');

    // Smoke test command uses the failover-wrapped platform
    $services->set(AiSmokeTestCommand::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    // Wire OPENROUTER_BLOCKED_MODELS env var for ModelDiscoveryService
    $services->set(ModelDiscoveryService::class)
        ->arg('$blockedModels', '%env(string:OPENROUTER_BLOCKED_MODELS)%');
};
