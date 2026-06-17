<?php

namespace App\Tests\Controller;

use App\Entity\Photo;
use App\Tests\DatabaseTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoUploadTest extends DatabaseTestCase
{
    public function testUploadPhotoStoresFileAndEntity(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');

        // Génère une petite image PNG temporaire à uploader.
        $tmp = tempnam(sys_get_temp_dir(), 'img').'.png';
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $tmp);
        imagedestroy($img);

        $upload = new UploadedFile($tmp, 'tomate.png', 'image/png', null, true);

        $this->client->loginUser($alice);
        $crawler = $this->client->request('GET', '/photo/new?vegetable='.$vegetable->getId());
        $form = $crawler->selectButton('Téléverser')->form();
        $form['photo[imageFile][file]']->upload($upload);
        $this->client->submit($form);

        $this->assertResponseRedirects();

        $this->em->clear();
        $photos = $this->em->getRepository(Photo::class)->findBy(['vegetable' => $vegetable->getId()]);
        $this->assertCount(1, $photos);

        $photo = $photos[0];
        $relative = $photo->getRelativePath();
        $this->assertNotNull($relative);

        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $absolute = $projectDir.'/'.$relative;
        $this->assertFileExists($absolute, 'Le fichier uploadé doit exister sur le disque.');

        // Nettoyage du fichier créé sous ./uploads.
        @unlink($absolute);
    }
}
