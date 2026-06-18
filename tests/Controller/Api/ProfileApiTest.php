<?php

namespace App\Tests\Controller\Api;

use App\Tests\DatabaseTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileApiTest extends DatabaseTestCase
{
    private function json(string $method, string $uri, array $payload): array
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload));
        $content = $this->client->getResponse()->getContent();

        return '' !== $content ? (json_decode($content, true) ?? []) : [];
    }

    public function testRequiresAuth(): void
    {
        $this->client->request('PUT', '/api/me/password', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testWrongCurrentPasswordRejected(): void
    {
        $alice = $this->createUser('alice', 'secret123');
        $this->client->loginUser($alice);

        $data = $this->json('PUT', '/api/me/password', ['currentPassword' => 'wrong', 'newPassword' => 'newsecret']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('currentPassword', $data['errors']);
    }

    public function testTooShortNewPasswordRejected(): void
    {
        $alice = $this->createUser('alice', 'secret123');
        $this->client->loginUser($alice);

        $data = $this->json('PUT', '/api/me/password', ['currentPassword' => 'secret123', 'newPassword' => '123']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('newPassword', $data['errors']);
    }

    public function testPasswordChanged(): void
    {
        $alice = $this->createUser('alice', 'secret123');
        $this->client->loginUser($alice);

        $this->json('PUT', '/api/me/password', ['currentPassword' => 'secret123', 'newPassword' => 'brandnewpass']);
        $this->assertResponseIsSuccessful();

        // Le nouveau mot de passe est bien actif.
        $this->em->clear();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $reloaded = $this->em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['name' => 'alice']);
        $this->assertTrue($hasher->isPasswordValid($reloaded, 'brandnewpass'));
    }
}
