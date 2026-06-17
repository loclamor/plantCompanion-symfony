<?php

namespace App\Form;

use App\Entity\Group;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        /** @var Group|null $current */
        $current = $builder->getData();
        $excludeId = $current?->getId();

        $builder
            ->add('name')
            ->add('parent', EntityType::class, [
                'class' => Group::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— Aucun —',
                'query_builder' => function (EntityRepository $repo) use ($user, $excludeId) {
                    $qb = $repo->createQueryBuilder('e')
                        ->andWhere('e.utilisateur = :user')
                        ->setParameter('user', $user)
                        ->orderBy('e.name', 'ASC');
                    if (null !== $excludeId) {
                        $qb->andWhere('e.id != :self')->setParameter('self', $excludeId);
                    }

                    return $qb;
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Group::class,
        ]);
    }
}
