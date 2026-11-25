<?php

namespace App\Form;

use App\Entity\Folder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FolderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du dossier',
                'attr' => ['placeholder' => 'Par exemple : Inspiration', 'maxlength' => 120, 'autofocus' => true],
            ])
            ->add('parent', EntityType::class, [
                'class' => Folder::class,
                'choices' => $options['folder_choices'],
                'choice_label' => static fn (Folder $folder) => $folder->getPath(),
                'label' => 'Placer dans',
                'placeholder' => 'À la racine',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Folder::class,
            'csrf_token_id' => 'folder_create',
            'folder_choices' => [],
        ]);
        $resolver->setAllowedTypes('folder_choices', 'array');
    }
}
