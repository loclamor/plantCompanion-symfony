<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Please sign in');
    }

    // testLoginWithValidCredentials est désormais couvert par AuthenticationTest
    // (avec un utilisateur réel en base de test).

    public function testLogout(): void
    {
        $client = static::createClient();
        // We can't test logout without being logged in first
        // This is a placeholder for when we have a proper test setup
        $client->request('GET', '/logout');

        $this->assertTrue($client->getResponse()->isRedirection());
    }

    public function testRegistrationPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Register');
    }

    public function testProtectedRouteRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/vegetable');

        // Should redirect to login page
        $this->assertTrue($client->getResponse()->isRedirection());
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }
}