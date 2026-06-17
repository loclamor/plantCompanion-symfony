<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que la gestion des photos exige une authentification.
 */
class PhotoControllerTest extends WebTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function protectedRoutesProvider(): array
    {
        return [
            '/photo/new' => ['/photo/new'],
            '/photo/upload-multiple' => ['/photo/upload-multiple'],
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
