<?php

namespace App\Form;

use App\Entity\Bookmark;
use App\Entity\Folder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookmarkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, [
                'label' => 'Adresse du lien',
                'attr' => ['placeholder' => 'https://exemple.fr', 'maxlength' => 2048, 'autofocus' => true],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Un titre pour le retrouver facilement', 'maxlength' => 255],
            ])
            ->add('folder', EntityType::class, [
                'class' => Folder::class,
                'choices' => $options['folder_choices'],
                'choice_label' => static fn (Folder $folder) => $folder->getPath(),
                'label' => 'Dossier',
                'placeholder' => 'Sans dossier',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bookmark::class,
            'csrf_token_id' => 'bookmark_create',
            'folder_choices' => [],
        ]);
        $resolver->setAllowedTypes('folder_choices', 'array');
    }
}
