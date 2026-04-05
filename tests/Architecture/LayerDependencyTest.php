<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class LayerDependencyTest
{
    public function testSharedDoesNotDependOnDomains(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Shared'))
            ->excluding(
                Selector::inNamespace('App\Shared\Controller'),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Example'),
                Selector::inNamespace('App\User'),
            );
    }

    public function testEntitiesDoNotDependOnControllers(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::classname('/.*\\\\Entity\\\\.*/', true))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname('/.*\\\\Controller\\\\.*/', true));
    }
}
