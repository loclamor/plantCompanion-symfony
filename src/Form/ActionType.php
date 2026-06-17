<?php

namespace App\Form;

use App\Entity\Action;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActionType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        $builder
            ->add('vegetable', EntityType::class, [
                'class' => Vegetable::class,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $repo) => $repo->createQueryBuilder('e')
                    ->andWhere('e.utilisateur = :user')
                    ->setParameter('user', $user)
                    ->orderBy('e.name', 'ASC'),
            ])
            ->add('date', null, [
                'widget' => 'single_text',
            ])
            ->add('typeAction', ChoiceType::class, [
                'label' => 'Type d\'intervention',
                'choices' => array_combine(Action::TYPES_ACTION, Action::TYPES_ACTION),
            ])
            ->add('title', null, [
                'label' => 'Titre',
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Action::class,
        ]);
    }
}
