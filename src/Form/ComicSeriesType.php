<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\Input\ComicSeriesInput;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Form\DataTransformer\AuthorToInputTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * Formulaire pour le DTO ComicSeriesInput.
 *
 * @extends AbstractType<ComicSeriesInput>
 */
class ComicSeriesType extends AbstractType
{
    public function __construct(
        private readonly AuthorToInputTransformer $authorTransformer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('authors', AuthorAutocompleteType::class, [
                'label' => 'Auteur(s)',
                'required' => false,
            ])
            ->add('coverFile', DropzoneType::class, [
                'attr' => [
                    'data-controller' => 'symfony--ux-dropzone--dropzone',
                    'placeholder' => 'Glissez une image ou cliquez pour parcourir',
                ],
                'label' => 'Image de couverture',
                'required' => false,
            ])
            ->add('coverUrl', UrlType::class, [
                'label' => 'URL de la couverture (externe)',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'attr' => ['rows' => 4],
                'label' => 'Description',
                'required' => false,
            ])
            ->add('isOneShot', CheckboxType::class, [
                'label' => 'One-shot (tome unique)',
                'required' => false,
            ])
            ->add('isWishlist', CheckboxType::class, [
                'label' => 'Dans la liste de souhaits',
                'required' => false,
            ])
            ->add('latestPublishedIssue', IntegerType::class, [
                'attr' => ['min' => 0],
                'label' => 'Dernier tome paru',
                'required' => false,
            ])
            ->add('latestPublishedIssueComplete', CheckboxType::class, [
                'label' => 'Série terminée',
                'required' => false,
            ])
            ->add('publishedDate', TextType::class, [
                'label' => 'Date de publication',
                'required' => false,
            ])
            ->add('publisher', TextType::class, [
                'label' => 'Éditeur',
                'required' => false,
            ])
            ->add('status', EnumType::class, [
                'class' => ComicStatus::class,
                'choice_label' => static fn (ComicStatus $status): string => $status->getLabel(),
                'label' => 'Statut',
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre de la série',
            ])
            ->add('tomes', CollectionType::class, [
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'entry_options' => ['label' => false],
                'entry_type' => TomeType::class,
                'label' => 'Tomes',
                'prototype' => true,
                'required' => false,
            ])
            ->add('type', EnumType::class, [
                'class' => ComicType::class,
                'choice_label' => static fn (ComicType $type): string => $type->getLabel(),
                'label' => 'Type',
            ])
        ;

        // Transformer pour l'autocomplete des auteurs (Entity ↔ DTO)
        $builder->get('authors')->addModelTransformer($this->authorTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ComicSeriesInput::class,
        ]);
    }
}
