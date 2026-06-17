<?php

namespace App\Form;

use App\Entity\Group;
use App\Entity\Lieu;
use App\Entity\PorteGreffe;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VegetableType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        // Limite les choix des relations aux entités possédées par l'utilisateur courant.
        $ownedByUser = static fn (EntityRepository $repo) => $repo->createQueryBuilder('e')
            ->andWhere('e.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('e.name', 'ASC');

        $builder
            ->add('name')
            ->add('creationDate', null, [
                'widget' => 'single_text',
            ])
            ->add('addDate', null, [
                'widget' => 'single_text',
            ])
            ->add('typeOrigine')
            ->add('nomLatin')
            ->add('rusticite')
            ->add('moisFructiDebut')
            ->add('moisFructiFin')
            ->add('moisFleurDebut')
            ->add('moisFleurFin')
            ->add('pFleur')
            ->add('pFructi')
            ->add('type', EntityType::class, [
                'class' => Type::class,
                'choice_label' => 'name',
                'query_builder' => $ownedByUser,
            ])
            ->add('group', EntityType::class, [
                'class' => Group::class,
                'choice_label' => 'name',
                'query_builder' => $ownedByUser,
            ])
            ->add('parent', EntityType::class, [
                'class' => Vegetable::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— Aucun —',
                'query_builder' => $ownedByUser,
            ])
            ->add('porteGreffe', EntityType::class, [
                'class' => PorteGreffe::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— Aucun —',
                'query_builder' => $ownedByUser,
            ])
            ->add('lieuOrigine', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— Aucun —',
                'query_builder' => $ownedByUser,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vegetable::class,
        ]);
    }
}
