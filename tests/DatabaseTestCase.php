<?php

namespace App\Tests;

use App\Entity\Group;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base des tests fonctionnels qui ont besoin d'une base : recrée le schéma sur
 * la base SQLite de test (isolée, jamais le MySQL legacy) avant chaque test.
 */
abstract class DatabaseTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function createUser(string $name, string $password = 'password'): Utilisateur
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new Utilisateur();
        $user->setName($name);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createVegetable(Utilisateur $user, string $name = 'Tomate'): Vegetable
    {
        $type = (new Type())->setName('Légume')->setUtilisateur($user);
        $group = (new Group())->setName('Jardin')->setUtilisateur($user);
        $this->em->persist($type);
        $this->em->persist($group);

        $vegetable = (new Vegetable())
            ->setName($name)
            ->setType($type)
            ->setGroup($group)
            ->setUtilisateur($user)
            ->setCreationDate(new \DateTime())
            ->setAddDate(new \DateTime());

        $this->em->persist($vegetable);
        $this->em->flush();

        return $vegetable;
    }
}
