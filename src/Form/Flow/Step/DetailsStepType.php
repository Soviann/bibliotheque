<?php

declare(strict_types=1);

namespace App\Form\Flow\Step;

use App\Form\AuthorAutocompleteType;
use App\Form\DataTransformer\AuthorToInputTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Étape 3 : Détails (auteurs, éditeur, date, description).
 *
 * @extends AbstractType<\App\Dto\Input\ComicSeriesInput>
 */
class DetailsStepType extends AbstractType
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
            ->add('description', TextareaType::class, [
                'attr' => ['rows' => 4],
                'label' => 'Description',
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
        ;

        $builder->get('authors')->addModelTransformer($this->authorTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
        ]);
    }
}
