<?php

namespace App\Tests\Controller\Api;

use App\Entity\Photo;
use App\Tests\DatabaseTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests fonctionnels de l'API photos (upload multipart, photo par défaut,
 * suppression) consommée par la fiche plante du SPA.
 */
class VegetablePhotoApiTest extends DatabaseTestCase
{
    /** @var string[] chemins absolus à nettoyer */
    private array $createdFiles = [];

    private function makePngUpload(string $name): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img').'.png';
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $tmp);
        imagedestroy($img);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    private function cleanupVegetablePhotos(int $vegetableId): void
    {
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $this->em->clear();
        foreach ($this->em->getRepository(Photo::class)->findBy(['vegetable' => $vegetableId]) as $photo) {
            $relative = $photo->getRelativePath();
            if (null !== $relative) {
                @unlink($projectDir.'/'.$relative);
            }
        }
    }

    public function testUploadThenSetDefaultThenDelete(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $vegetableId = $vegetable->getId();

        $this->client->loginUser($alice);

        // Upload de 2 photos en une requête multipart.
        $this->client->request(
            'POST',
            '/api/vegetables/'.$vegetableId.'/photos',
            files: ['images' => [$this->makePngUpload('a.png'), $this->makePngUpload('b.png')]],
        );
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $data['photos']);

        // Définir la première comme photo par défaut.
        $firstId = $data['photos'][0]['id'];
        $this->client->request('PUT', '/api/photos/'.$firstId.'/default');
        $this->assertResponseIsSuccessful();
        $afterDefault = json_decode($this->client->getResponse()->getContent(), true);
        $default = array_values(array_filter($afterDefault['photos'], static fn ($p) => $p['isDefault']));
        $this->assertCount(1, $default);
        $this->assertSame($firstId, $default[0]['id']);

        // Suppression d'une photo.
        $this->client->request('DELETE', '/api/photos/'.$firstId);
        $this->assertResponseIsSuccessful();
        $afterDelete = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $afterDelete['photos']);

        $this->cleanupVegetablePhotos($vegetableId);
    }

    public function testRejectsNonImage(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $this->client->loginUser($alice);

        $tmp = tempnam(sys_get_temp_dir(), 'txt').'.txt';
        file_put_contents($tmp, 'pas une image');
        $upload = new UploadedFile($tmp, 'note.txt', 'text/plain', null, true);

        $this->client->request('POST', '/api/vegetables/'.$vegetable->getId().'/photos', files: ['images' => [$upload]]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testImportPhotoWithIntervention(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $vegetableId = $vegetable->getId();
        $this->client->loginUser($alice);

        $this->client->request(
            'POST',
            '/api/photos/import',
            parameters: [
                'vegetable' => $vegetableId,
                'date' => '2024-05-01',
                'typeAction' => 'observation',
                'title' => 'Fleurs',
                'comment' => 'Belle floraison',
            ],
            files: ['image' => $this->makePngUpload('imp.png')],
        );
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotNull($data['photoId']);
        $this->assertNotNull($data['actionId']);

        // Photo rattachée à la plante + action créée et liée.
        $this->em->clear();
        $photos = $this->em->getRepository(\App\Entity\Photo::class)->findBy(['vegetable' => $vegetableId]);
        $this->assertCount(1, $photos);
        $this->assertSame($data['actionId'], $photos[0]->getAction()?->getId());

        $this->cleanupVegetablePhotos($vegetableId);
    }

    public function testImportPhotoWithoutInterventionSansAction(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $vegetableId = $vegetable->getId();
        $this->client->loginUser($alice);

        $this->client->request(
            'POST',
            '/api/photos/import',
            parameters: ['vegetable' => $vegetableId, 'typeAction' => 'none'],
            files: ['image' => $this->makePngUpload('imp.png')],
        );
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNull($data['actionId']);

        $this->cleanupVegetablePhotos($vegetableId);
    }

    public function testCannotUploadToOthersVegetable(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $vegetable = $this->createVegetable($alice, 'Tomate Alice');

        $this->client->loginUser($bob);
        $this->client->request(
            'POST',
            '/api/vegetables/'.$vegetable->getId().'/photos',
            files: ['images' => [$this->makePngUpload('x.png')]],
        );
        $this->assertResponseStatusCodeSame(403);
    }
}
