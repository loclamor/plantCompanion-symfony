<?php

namespace App\Form;

use App\Entity\PorteGreffe;
use App\Entity\Type;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PorteGreffeType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        $builder
            ->add('name')
            ->add('type', EntityType::class, [
                'class' => Type::class,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $repo) => $repo->createQueryBuilder('e')
                    ->andWhere('e.utilisateur = :user')
                    ->setParameter('user', $user)
                    ->orderBy('e.name', 'ASC'),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PorteGreffe::class,
        ]);
    }
}
