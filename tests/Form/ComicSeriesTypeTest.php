<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\ComicSeries;
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
 * (AuthorAutocompleteType, DropzoneType) qui nécessitent le conteneur.
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
            'isWishlist' => false,
            'latestPublishedIssue' => 10,
            'latestPublishedIssueComplete' => false,
            'publishedDate' => '2023-01-15',
            'publisher' => 'Dupuis',
            'status' => 'buying',
            'title' => 'Test Series',
            'type' => 'bd',
        ];

        $comic = new ComicSeries();
        $form = $this->formFactory->create(ComicSeriesType::class, $comic);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());

        self::assertSame('Test Series', $comic->getTitle());
        self::assertSame(ComicType::BD, $comic->getType());
        self::assertSame(ComicStatus::BUYING, $comic->getStatus());
        self::assertTrue($comic->isOneShot());
        self::assertFalse($comic->isWishlist());
        self::assertSame(10, $comic->getLatestPublishedIssue());
        self::assertSame('Dupuis', $comic->getPublisher());
        self::assertSame('Une description de la série', $comic->getDescription());
        self::assertSame('https://example.com/cover.jpg', $comic->getCoverUrl());
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
        self::assertTrue($form->has('isWishlist'));
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
        self::assertFalse($form->get('isWishlist')->isRequired());
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

        self::assertSame(ComicSeries::class, $form->getConfig()->getDataClass());
    }

    /**
     * Teste les valeurs par défaut d'une nouvelle série.
     */
    public function testNewSeriesHasDefaultValues(): void
    {
        $comic = new ComicSeries();
        $form = $this->formFactory->create(ComicSeriesType::class, $comic);

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
        $comic = new ComicSeries();
        $comic->setTitle('Existing Title');
        $comic->setType(ComicType::MANGA);
        $comic->setStatus(ComicStatus::FINISHED);
        $comic->setLatestPublishedIssue(25);
        $comic->setPublisher('Kana');

        $form = $this->formFactory->create(ComicSeriesType::class, $comic);
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
