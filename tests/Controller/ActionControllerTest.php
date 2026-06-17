<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que le CRUD des interventions exige une authentification.
 */
class ActionControllerTest extends WebTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function protectedRoutesProvider(): array
    {
        return [
            '/action (index)' => ['/action'],
            '/action (new)' => ['/action/new'],
            '/action (show)' => ['/action/1'],
        ];
    }

    /**
     * @dataProvider protectedRoutesProvider
     */
    public function testRouteRequiresAuthentication(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $this->assertTrue($client->getResponse()->isRedirection());
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }
}
