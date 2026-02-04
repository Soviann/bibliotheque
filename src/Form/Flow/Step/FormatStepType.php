<?php

declare(strict_types=1);

namespace App\Form\Flow\Step;

use App\Enum\ComicType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Étape 1 : Type et format de la série.
 *
 * @extends AbstractType<\App\Dto\Input\ComicSeriesInput>
 */
class FormatStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('isOneShot', CheckboxType::class, [
                'label' => 'One-shot (tome unique)',
                'required' => false,
            ])
            ->add('type', EnumType::class, [
                'class' => ComicType::class,
                'choice_label' => static fn (ComicType $type): string => $type->getLabel(),
                'label' => 'Type',
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
