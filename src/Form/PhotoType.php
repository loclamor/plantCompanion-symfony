<?php

namespace App\Form;

use App\Entity\Action;
use App\Entity\Photo;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;
use Vich\UploaderBundle\Form\Type\VichImageType;

class PhotoType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        $ownedByUser = static fn (EntityRepository $repo) => $repo->createQueryBuilder('e')
            ->andWhere('e.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('e.name', 'ASC');

        $builder
            ->add('imageFile', VichImageType::class, [
                'label' => 'Photo',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri' => false,
                'constraints' => [new NotNull(message: 'Veuillez sélectionner un fichier.')],
            ])
            ->add('vegetable', EntityType::class, [
                'class' => Vegetable::class,
                'choice_label' => 'name',
                'query_builder' => $ownedByUser,
            ])
            ->add('action', EntityType::class, [
                'class' => Action::class,
                'choice_label' => 'title',
                'required' => false,
                'placeholder' => '— Aucune —',
                'query_builder' => static fn (EntityRepository $repo) => $repo->createQueryBuilder('e')
                    ->andWhere('e.utilisateur = :user')
                    ->setParameter('user', $user)
                    ->orderBy('e.date', 'DESC'),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Photo::class,
        ]);
    }
}
