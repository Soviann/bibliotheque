<?php

declare(strict_types=1);

namespace App\Form\Flow;

use App\Dto\Input\ComicSeriesInput;
use App\Form\Flow\Step\CoverStepType;
use App\Form\Flow\Step\DetailsStepType;
use App\Form\Flow\Step\FormatStepType;
use App\Form\Flow\Step\IdentificationOneShotStepType;
use App\Form\Flow\Step\IdentificationSeriesStepType;
use App\Form\Flow\Step\TomesStepType;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\FormFlowCursor;
use Symfony\Component\Form\Flow\Type\FinishFlowType;
use Symfony\Component\Form\Flow\Type\NextFlowType;
use Symfony\Component\Form\Flow\Type\PreviousFlowType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire multi-étapes pour la création/édition d'une série.
 */
class ComicSeriesFlowType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder
            // Étape 1 : Type et format (one-shot ou série)
            ->addStep('format', FormatStepType::class)

            // Étape 2a : Identification pour série (skipped si one-shot)
            ->addStep(
                'identification_series',
                IdentificationSeriesStepType::class,
                [],
                static fn (ComicSeriesInput $data): bool => $data->isOneShot
            )

            // Étape 2b : Identification pour one-shot (skipped si série)
            ->addStep(
                'identification_oneshot',
                IdentificationOneShotStepType::class,
                [],
                static fn (ComicSeriesInput $data): bool => !$data->isOneShot
            )

            // Étape 3 : Détails (auteurs, éditeur, description)
            ->addStep('details', DetailsStepType::class)

            // Étape 4 : Couverture
            ->addStep('cover', CoverStepType::class)

            // Étape 5 : Tomes et statut
            ->addStep('tomes', TomesStepType::class)

            // Boutons de navigation avec labels français
            ->add('previous', PreviousFlowType::class, [
                'label' => 'Précédent',
            ])
            ->add('next', NextFlowType::class, [
                'label' => 'Suivant',
            ])
            ->add('finish', FinishFlowType::class, [
                // Permettre la sauvegarde dès l'étape 2 (identification), pas seulement à la dernière étape
                'include_if' => static fn (FormFlowCursor $cursor): bool => 'format' !== $cursor->getCurrentStep(),
                'label' => 'Enregistrer',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ComicSeriesInput::class,
            'step_property_path' => 'currentStep',
            // Valider uniquement l'étape courante, pas le groupe 'Default'
            'validation_groups' => static fn (\Symfony\Component\Form\Flow\FormFlowInterface $flow): array => [$flow->getCursor()->getCurrentStep()],
        ]);
    }
}
