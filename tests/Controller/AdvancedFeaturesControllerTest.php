<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que le calendrier et l'impression exigent une authentification.
 */
class AdvancedFeaturesControllerTest extends WebTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function protectedRoutesProvider(): array
    {
        return [
            'calendrier' => ['/calendar/fructification'],
            'impression' => ['/print/bytype'],
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
