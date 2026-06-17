<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que les CRUD de paramétrage (Type, Group, Lieu, PorteGreffe)
 * exigent une authentification : toute route doit rediriger vers le login.
 */
class ParametrageControllerTest extends WebTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function protectedRoutesProvider(): array
    {
        $routes = [];
        foreach (['/type', '/group', '/lieu', '/porte-greffe'] as $base) {
            $routes[$base.' (index)'] = [$base];
            $routes[$base.' (new)'] = [$base.'/new'];
            $routes[$base.' (show)'] = [$base.'/1'];
        }

        return $routes;
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
