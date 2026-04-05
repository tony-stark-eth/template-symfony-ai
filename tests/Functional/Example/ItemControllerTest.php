<?php

declare(strict_types=1);

namespace App\Tests\Functional\Example;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class ItemControllerTest extends WebTestCase
{
    public function testProtectedRouteRedirectsToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/items');

        self::assertResponseRedirects('/login');
    }

    public function testLoginPageReturns200(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }
}
