<?php

declare(strict_types=1);

namespace App\Form\Flow\Step;

use App\Enum\ComicStatus;
use App\Form\TomeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Étape 5 : Statut et collection de tomes.
 *
 * @extends AbstractType<\App\Dto\Input\ComicSeriesInput>
 */
class TomesStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('latestPublishedIssue', IntegerType::class, [
                'label' => 'Dernier numéro paru',
                'required' => false,
            ])
            ->add('status', EnumType::class, [
                'class' => ComicStatus::class,
                'choice_label' => static fn (ComicStatus $status): string => $status->getLabel(),
                'label' => 'Statut',
            ])
            ->add('tomes', CollectionType::class, [
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'entry_options' => ['label' => false],
                'entry_type' => TomeType::class,
                'label' => false,
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
