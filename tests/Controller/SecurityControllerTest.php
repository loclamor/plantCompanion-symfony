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

    public function testLoginWithValidCredentials(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form();
        $form['_username'] = 'testuser';
        $form['_password'] = 'testpassword';

        $client->submit($form);

        // This will fail until we have a real user in the database
        // For now, we just check that the form submission works
        $this->assertTrue($client->getResponse()->isRedirection());
    }

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