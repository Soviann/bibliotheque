<?php

declare(strict_types=1);

namespace App\Tests\Form\Flow;

use App\Dto\Input\ComicSeriesInput;
use App\Form\Flow\ComicSeriesFlowType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Flow\DataStorage\NullDataStorage;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Tests pour le formulaire multi-étapes ComicSeriesFlowType.
 */
#[CoversClass(ComicSeriesFlowType::class)]
class ComicSeriesFlowTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = static::getContainer()->get(FormFactoryInterface::class);
    }

    /**
     * Teste que le flow peut être créé.
     */
    public function testFlowCanBeCreated(): void
    {
        $input = new ComicSeriesInput();
        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertInstanceOf(FormFlowInterface::class, $flow);
    }

    /**
     * Teste que le flow commence à l'étape "format".
     */
    public function testFlowStartsAtFormatStep(): void
    {
        $input = new ComicSeriesInput();
        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);
        \assert($flow instanceof FormFlowInterface);

        self::assertSame('format', $flow->getCursor()->getCurrentStep());
    }

    /**
     * Teste que l'étape format contient les champs type, isOneShot et status.
     */
    public function testFormatStepHasTypeOneShotAndStatusFields(): void
    {
        $input = new ComicSeriesInput();
        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        // Les champs sont dans le sous-formulaire de l'étape courante
        self::assertTrue($flow->get('format')->has('type'));
        self::assertTrue($flow->get('format')->has('isOneShot'));
        self::assertTrue($flow->get('format')->has('status'));
    }

    /**
     * Teste que pour une série normale, l'étape identification_series est affichée.
     */
    public function testSeriesPathShowsIdentificationSeriesStep(): void
    {
        $input = new ComicSeriesInput();
        $input->isOneShot = false;

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);
        \assert($flow instanceof FormFlowInterface);

        // Passe à l'étape suivante
        $flow->moveNext();

        self::assertSame('identification_series', $flow->getCursor()->getCurrentStep());
    }

    /**
     * Teste que pour un one-shot, l'étape identification_oneshot est affichée.
     */
    public function testOneShotPathShowsIdentificationOneShotStep(): void
    {
        $input = new ComicSeriesInput();
        $input->isOneShot = true;

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);
        \assert($flow instanceof FormFlowInterface);

        // Passe à l'étape suivante
        $flow->moveNext();

        self::assertSame('identification_oneshot', $flow->getCursor()->getCurrentStep());
    }

    /**
     * Teste que l'étape identification_series contient le champ title.
     */
    public function testIdentificationSeriesStepHasTitleField(): void
    {
        $input = new ComicSeriesInput();
        $input->isOneShot = false;
        $input->currentStep = 'identification_series';

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertTrue($flow->get('identification_series')->has('title'));
    }

    /**
     * Teste que l'étape details contient les champs auteurs, éditeur, etc.
     */
    public function testDetailsStepHasExpectedFields(): void
    {
        $input = new ComicSeriesInput();
        $input->currentStep = 'details';

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertTrue($flow->get('details')->has('authors'));
        self::assertTrue($flow->get('details')->has('publisher'));
        self::assertTrue($flow->get('details')->has('publishedDate'));
        self::assertTrue($flow->get('details')->has('description'));
    }

    /**
     * Teste que l'étape cover contient les champs de couverture.
     */
    public function testCoverStepHasCoverFields(): void
    {
        $input = new ComicSeriesInput();
        $input->currentStep = 'cover';

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertTrue($flow->get('cover')->has('coverUrl'));
        self::assertTrue($flow->get('cover')->has('coverFile'));
    }

    /**
     * Teste que l'étape tomes contient les champs tomes et latestPublishedIssue.
     */
    public function testTomesStepHasTomesAndLatestPublishedFields(): void
    {
        $input = new ComicSeriesInput();
        $input->currentStep = 'tomes';

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertTrue($flow->get('tomes')->has('tomes'));
        self::assertTrue($flow->get('tomes')->has('latestPublishedIssue'));
    }

    /**
     * Teste que le flow a le bouton "Suivant" à l'étape 1.
     */
    public function testFlowHasNextButtonAtFirstStep(): void
    {
        $input = new ComicSeriesInput();
        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        // À l'étape 1, seul le bouton "Suivant" est présent (pas de "Précédent")
        self::assertTrue($flow->has('next'));
        self::assertFalse($flow->has('previous'));
    }

    /**
     * Teste que le bouton Enregistrer n'est PAS présent à l'étape 1 (format).
     *
     * L'utilisateur ne doit pas pouvoir sauvegarder tant qu'il n'a pas au moins
     * renseigné les informations d'identification (titre).
     */
    public function testFinishButtonNotPresentAtFormatStep(): void
    {
        $input = new ComicSeriesInput();
        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        // À l'étape format, le bouton finish ne doit pas être présent
        self::assertFalse(
            $flow->has('finish'),
            'Le bouton Enregistrer ne devrait pas être présent à l\'étape format'
        );
    }

    /**
     * Teste que le bouton Enregistrer EST présent à l'étape 2 (identification_series).
     */
    public function testFinishButtonPresentAtIdentificationSeriesStep(): void
    {
        $input = new ComicSeriesInput();
        $input->isOneShot = false;
        $input->currentStep = 'identification_series';

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertTrue(
            $flow->has('finish'),
            'Le bouton Enregistrer devrait être présent à l\'étape identification_series'
        );
    }

    /**
     * Teste que le bouton Enregistrer EST présent à l'étape 2 (identification_oneshot).
     */
    public function testFinishButtonPresentAtIdentificationOneShotStep(): void
    {
        $input = new ComicSeriesInput();
        $input->isOneShot = true;
        $input->currentStep = 'identification_oneshot';

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertTrue(
            $flow->has('finish'),
            'Le bouton Enregistrer devrait être présent à l\'étape identification_oneshot'
        );
    }

    /**
     * Teste que le bouton Enregistrer EST présent à l'étape 3 (details).
     */
    public function testFinishButtonPresentAtDetailsStep(): void
    {
        $input = new ComicSeriesInput();
        $input->currentStep = 'details';

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertTrue(
            $flow->has('finish'),
            'Le bouton Enregistrer devrait être présent à l\'étape details'
        );
    }

    /**
     * Teste que le bouton Enregistrer EST présent à l'étape 4 (cover).
     */
    public function testFinishButtonPresentAtCoverStep(): void
    {
        $input = new ComicSeriesInput();
        $input->currentStep = 'cover';

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertTrue(
            $flow->has('finish'),
            'Le bouton Enregistrer devrait être présent à l\'étape cover'
        );
    }

    /**
     * Teste que le bouton Enregistrer EST présent à l'étape 5 (tomes).
     */
    public function testFinishButtonPresentAtTomesStep(): void
    {
        $input = new ComicSeriesInput();
        $input->currentStep = 'tomes';

        $flow = $this->formFactory->create(ComicSeriesFlowType::class, $input, [
            'data_storage' => new NullDataStorage(),
        ]);

        self::assertTrue(
            $flow->has('finish'),
            'Le bouton Enregistrer devrait être présent à l\'étape tomes'
        );
    }
}
