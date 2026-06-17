<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseTestCase;

class AuthenticationTest extends DatabaseTestCase
{
    public function testLoginWithValidCredentials(): void
    {
        $this->createUser('alice', 'secret123');

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form();
        $form['_username'] = 'alice';
        $form['_password'] = 'secret123';
        $this->client->submit($form);

        // Authentification réussie → redirection (pas de retour à /login).
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteNotSame('app_login');
    }

    public function testLoginWithInvalidCredentialsStaysOnLogin(): void
    {
        $this->createUser('bob', 'secret123');

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form();
        $form['_username'] = 'bob';
        $form['_password'] = 'wrong';
        $this->client->submit($form);

        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    private function assertRouteNotSame(string $route): void
    {
        $request = $this->client->getRequest();
        $this->assertNotSame($route, $request->attributes->get('_route'));
    }
}
