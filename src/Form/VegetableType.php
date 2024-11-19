<?php

namespace App\Form;

use App\Entity\Group;
use App\Entity\Lieu;
use App\Entity\Photo;
use App\Entity\PorteGreffe;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VegetableType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
                'choice_label' => 'id',
            ])
            ->add('group', EntityType::class, [
                'class' => Group::class,
                'choice_label' => 'id',
            ])
            ->add('parent', EntityType::class, [
                'class' => Vegetable::class,
                'choice_label' => 'id',
            ])
            ->add('porteGreffe', EntityType::class, [
                'class' => PorteGreffe::class,
                'choice_label' => 'id',
            ])
            ->add('lieuOrigine', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'id',
            ])
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => 'id',
            ])
            ->add('defaultPhoto', EntityType::class, [
                'class' => Photo::class,
                'choice_label' => 'id',
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
