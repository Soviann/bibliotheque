<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Dto\Input\ComicSeriesInput;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Form\ComicSeriesType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Tests fonctionnels pour ComicSeriesType.
 *
 * Note: Ce test utilise KernelTestCase car ComicSeriesType dépend de services
 * (AuthorAutocompleteType, DropzoneType, AuthorToInputTransformer) qui nécessitent le conteneur.
 */
#[CoversClass(ComicSeriesType::class)]
class ComicSeriesTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = static::getContainer()->get(FormFactoryInterface::class);
    }

    /**
     * Teste la soumission de données valides.
     */
    public function testSubmitValidData(): void
    {
        $formData = [
            'coverUrl' => 'https://example.com/cover.jpg',
            'description' => 'Une description de la série',
            'isOneShot' => true,
            'latestPublishedIssue' => 10,
            'latestPublishedIssueComplete' => false,
            'publishedDate' => '2023-01-15',
            'publisher' => 'Dupuis',
            'status' => 'buying',
            'title' => 'Test Series',
            'type' => 'bd',
        ];

        $input = new ComicSeriesInput();
        $form = $this->formFactory->create(ComicSeriesType::class, $input);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());

        self::assertSame('Test Series', $input->title);
        self::assertSame(ComicType::BD, $input->type);
        self::assertSame(ComicStatus::BUYING, $input->status);
        self::assertTrue($input->isOneShot);
        self::assertSame(10, $input->latestPublishedIssue);
        self::assertSame('Dupuis', $input->publisher);
        self::assertSame('Une description de la série', $input->description);
        self::assertSame('https://example.com/cover.jpg', $input->coverUrl);
    }

    /**
     * Teste que les champs du formulaire existent.
     */
    public function testFormHasExpectedFields(): void
    {
        $form = $this->formFactory->create(ComicSeriesType::class);

        self::assertTrue($form->has('authors'));
        self::assertTrue($form->has('coverFile'));
        self::assertTrue($form->has('coverUrl'));
        self::assertTrue($form->has('description'));
        self::assertTrue($form->has('isOneShot'));
        self::assertTrue($form->has('latestPublishedIssue'));
        self::assertTrue($form->has('latestPublishedIssueComplete'));
        self::assertTrue($form->has('publishedDate'));
        self::assertTrue($form->has('publisher'));
        self::assertTrue($form->has('status'));
        self::assertTrue($form->has('title'));
        self::assertTrue($form->has('tomes'));
        self::assertTrue($form->has('type'));
    }

    /**
     * Teste que title est requis.
     */
    public function testTitleIsRequired(): void
    {
        $form = $this->formFactory->create(ComicSeriesType::class);

        self::assertTrue($form->get('title')->isRequired());
    }

    /**
     * Teste que les champs optionnels ne sont pas requis.
     */
    public function testOptionalFieldsAreNotRequired(): void
    {
        $form = $this->formFactory->create(ComicSeriesType::class);

        self::assertFalse($form->get('authors')->isRequired());
        self::assertFalse($form->get('coverFile')->isRequired());
        self::assertFalse($form->get('coverUrl')->isRequired());
        self::assertFalse($form->get('description')->isRequired());
        self::assertFalse($form->get('isOneShot')->isRequired());
        self::assertFalse($form->get('latestPublishedIssue')->isRequired());
        self::assertFalse($form->get('latestPublishedIssueComplete')->isRequired());
        self::assertFalse($form->get('publishedDate')->isRequired());
        self::assertFalse($form->get('publisher')->isRequired());
        self::assertFalse($form->get('tomes')->isRequired());
    }

    /**
     * Teste que le data_class est correctement configuré.
     */
    public function testDataClassIsCorrect(): void
    {
        $form = $this->formFactory->create(ComicSeriesType::class);

        self::assertSame(ComicSeriesInput::class, $form->getConfig()->getDataClass());
    }

    /**
     * Teste les valeurs par défaut d'une nouvelle série.
     */
    public function testNewSeriesHasDefaultValues(): void
    {
        $input = new ComicSeriesInput();
        $form = $this->formFactory->create(ComicSeriesType::class, $input);

        $view = $form->createView();

        // Vérifie les valeurs par défaut
        self::assertSame('buying', $view->children['status']->vars['value']);
        self::assertSame('bd', $view->children['type']->vars['value']);
    }

    /**
     * Teste le pré-remplissage des données existantes.
     */
    public function testFormPrefillsExistingData(): void
    {
        $input = new ComicSeriesInput();
        $input->title = 'Existing Title';
        $input->type = ComicType::MANGA;
        $input->status = ComicStatus::FINISHED;
        $input->latestPublishedIssue = 25;
        $input->publisher = 'Kana';

        $form = $this->formFactory->create(ComicSeriesType::class, $input);
        $view = $form->createView();

        self::assertSame('Existing Title', $view->children['title']->vars['value']);
        self::assertSame('manga', $view->children['type']->vars['value']);
        self::assertSame('finished', $view->children['status']->vars['value']);
        self::assertSame('25', $view->children['latestPublishedIssue']->vars['value']);
        self::assertSame('Kana', $view->children['publisher']->vars['value']);
    }

    /**
     * Teste l'ajout de tomes via la collection.
     */
    public function testTomesCollectionAllowsAddAndDelete(): void
    {
        $form = $this->formFactory->create(ComicSeriesType::class);
        $tomesConfig = $form->get('tomes')->getConfig();

        self::assertTrue($tomesConfig->getOption('allow_add'));
        self::assertTrue($tomesConfig->getOption('allow_delete'));
    }

    /**
     * Teste les différents types d'enum.
     */
    public function testEnumFieldsHaveCorrectChoices(): void
    {
        $form = $this->formFactory->create(ComicSeriesType::class);

        $typeChoices = $form->get('type')->getConfig()->getOption('class');
        $statusChoices = $form->get('status')->getConfig()->getOption('class');

        self::assertSame(ComicType::class, $typeChoices);
        self::assertSame(ComicStatus::class, $statusChoices);
    }
}
