<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VegetableControllerTest extends WebTestCase
{
    public function testIndexRouteRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/vegetable');

        // Should redirect to login page
        $this->assertTrue($client->getResponse()->isRedirection());
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function testNewRouteRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/vegetable/new');

        // Should redirect to login page
        $this->assertTrue($client->getResponse()->isRedirection());
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function testShowRouteRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/vegetable/1');

        // Should redirect to login page
        $this->assertTrue($client->getResponse()->isRedirection());
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    // Note: These tests will need to be extended with actual authenticated user tests
    // when you have a test user setup in your test database
}