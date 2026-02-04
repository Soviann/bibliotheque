<?php

declare(strict_types=1);

namespace App\Form\Flow\Step;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * Étape 4 : Couverture (URL ou fichier uploadé).
 *
 * @extends AbstractType<\App\Dto\Input\ComicSeriesInput>
 */
class CoverStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('coverFile', DropzoneType::class, [
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                    'placeholder' => 'Glissez une image ou cliquez pour parcourir',
                ],
                'label' => 'Image de couverture',
                'required' => false,
            ])
            ->add('coverUrl', UrlType::class, [
                'label' => 'URL de la couverture',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
        ]);
    }
}
