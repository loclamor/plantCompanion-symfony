<?php

namespace App\Tests\Controller\Api;

use App\Entity\Photo;
use App\Tests\DatabaseTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests fonctionnels de l'API des interventions (actions).
 */
class ActionApiTest extends DatabaseTestCase
{
    private function json(string $method, string $uri, array $payload = null): array
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: null !== $payload ? json_encode($payload) : null,
        );
        $content = $this->client->getResponse()->getContent();

        return '' !== $content ? (json_decode($content, true) ?? []) : [];
    }

    public function testListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/actions');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateAndList(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/actions', [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'taille',
            'title' => 'Taille de printemps',
            'comment' => 'RAS',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Taille de printemps', $created['title']);
        $this->assertSame($vegetable->getId(), $created['vegetable']['id']);

        $list = $this->json('GET', '/api/actions');
        $this->assertCount(1, $list['items']);
    }

    public function testInvalidTypeReturns422(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/actions', [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'inexistant',
            'title' => 'X',
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('typeAction', $data['errors']);
    }

    public function testMissingTitleAndVegetableReturns422(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/actions', ['typeAction' => 'observation', 'title' => '']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('title', $data['errors']);
        $this->assertArrayHasKey('vegetable', $data['errors']);
    }

    public function testCannotAttachToOthersVegetable(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $vegetable = $this->createVegetable($alice, 'Tomate Alice');

        $this->client->loginUser($bob);
        $data = $this->json('POST', '/api/actions', [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'observation',
            'title' => 'Hack',
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('vegetable', $data['errors']);
    }

    public function testUpdateAndDelete(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/actions', [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'observation',
            'title' => 'Note',
        ]);
        $id = $created['id'];

        $updated = $this->json('PUT', '/api/actions/'.$id, [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'ceuillette',
            'title' => 'Récolte',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('ceuillette', $updated['typeAction']);

        $this->client->request('DELETE', '/api/actions/'.$id);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testBulkCreatesOneActionPerVegetable(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $v1 = $this->createVegetable($alice, 'Tomate');
        $v2 = $this->createVegetable($alice, 'Courgette');
        $vBob = $this->createVegetable($bob, 'Pomme Bob');

        $this->client->loginUser($alice);
        $res = $this->json('POST', '/api/actions/bulk', [
            'vegetables' => [$v1->getId(), $v2->getId(), $vBob->getId()], // celui de bob est ignoré
            'typeAction' => 'taille',
            'title' => 'Taille groupée',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(2, $res['created']);

        $list = $this->json('GET', '/api/actions');
        $this->assertCount(2, $list['items']);
    }

    public function testBulkRejectsInvalidSharedFields(): void
    {
        $alice = $this->createUser('alice');
        $v1 = $this->createVegetable($alice, 'Tomate');
        $this->client->loginUser($alice);

        $res = $this->json('POST', '/api/actions/bulk', [
            'vegetables' => [$v1->getId()],
            'typeAction' => 'inexistant',
            'title' => '',
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('title', $res['errors']);
        $this->assertArrayHasKey('typeAction', $res['errors']);
    }

    public function testBulkWithNoValidVegetableReturns422(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $vBob = $this->createVegetable($bob, 'Pomme Bob');
        $this->client->loginUser($alice);

        $res = $this->json('POST', '/api/actions/bulk', [
            'vegetables' => [$vBob->getId()],
            'typeAction' => 'observation',
            'title' => 'X',
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('vegetables', $res['errors']);
    }

    public function testUploadPhotoToAction(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/actions', [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'observation',
            'title' => 'Fleurs',
        ]);
        $actionId = $created['id'];

        $tmp = tempnam(sys_get_temp_dir(), 'img').'.png';
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $tmp);
        imagedestroy($img);
        $upload = new UploadedFile($tmp, 'a.png', 'image/png', null, true);

        $this->client->request('POST', '/api/actions/'.$actionId.'/photos', files: ['images' => [$upload]]);
        $this->assertResponseStatusCodeSame(201);

        // La photo est rattachée à l'action ET à la plante.
        $this->em->clear();
        $photos = $this->em->getRepository(Photo::class)->findBy(['vegetable' => $vegetable->getId()]);
        $this->assertCount(1, $photos);
        $this->assertSame($actionId, $photos[0]->getAction()?->getId());

        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $relative = $photos[0]->getRelativePath();
        if (null !== $relative) {
            @unlink($projectDir.'/'.$relative);
        }
    }
}
