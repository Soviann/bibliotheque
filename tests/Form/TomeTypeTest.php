<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Tome;
use App\Form\TomeType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * Tests unitaires pour TomeType.
 */
#[CoversClass(TomeType::class)]
class TomeTypeTest extends TypeTestCase
{
    /**
     * Teste la soumission de données valides.
     */
    public function testSubmitValidData(): void
    {
        $formData = [
            'bought' => true,
            'downloaded' => false,
            'isbn' => '978-2-505-00123-4',
            'number' => 5,
            'onNas' => true,
            'title' => 'Le Retour du Héros',
        ];

        $tome = new Tome();
        $form = $this->factory->create(TomeType::class, $tome);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertSame(5, $tome->getNumber());
        self::assertTrue($tome->isBought());
        self::assertFalse($tome->isDownloaded());
        self::assertTrue($tome->isOnNas());
        self::assertSame('978-2-505-00123-4', $tome->getIsbn());
        self::assertSame('Le Retour du Héros', $tome->getTitle());
    }

    /**
     * Teste que les champs optionnels peuvent être vides.
     */
    public function testOptionalFieldsCanBeEmpty(): void
    {
        $formData = [
            'bought' => false,
            'downloaded' => false,
            'isbn' => '',
            'number' => 1,
            'onNas' => false,
            'title' => '',
        ];

        $tome = new Tome();
        $form = $this->factory->create(TomeType::class, $tome);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
    }

    /**
     * Teste que les champs du formulaire existent.
     */
    public function testFormHasExpectedFields(): void
    {
        $form = $this->factory->create(TomeType::class);

        self::assertTrue($form->has('number'));
        self::assertTrue($form->has('bought'));
        self::assertTrue($form->has('downloaded'));
        self::assertTrue($form->has('onNas'));
        self::assertTrue($form->has('isbn'));
        self::assertTrue($form->has('title'));
    }

    /**
     * Teste que le formulaire pré-remplit les données existantes.
     */
    public function testFormPrefillsExistingData(): void
    {
        $tome = new Tome();
        $tome->setNumber(10);
        $tome->setBought(true);
        $tome->setDownloaded(true);
        $tome->setOnNas(true);
        $tome->setIsbn('123-456');
        $tome->setTitle('Existing Title');

        $form = $this->factory->create(TomeType::class, $tome);

        $view = $form->createView();

        self::assertSame('10', $view->children['number']->vars['value']);
        self::assertTrue($view->children['bought']->vars['checked']);
        self::assertTrue($view->children['downloaded']->vars['checked']);
        self::assertTrue($view->children['onNas']->vars['checked']);
        self::assertSame('123-456', $view->children['isbn']->vars['value']);
        self::assertSame('Existing Title', $view->children['title']->vars['value']);
    }

    /**
     * Teste que le data_class est correctement configuré.
     */
    public function testDataClassIsCorrect(): void
    {
        $form = $this->factory->create(TomeType::class);

        self::assertSame(Tome::class, $form->getConfig()->getDataClass());
    }

    /**
     * Teste que number est requis.
     */
    public function testNumberIsRequired(): void
    {
        $form = $this->factory->create(TomeType::class);

        self::assertTrue($form->get('number')->isRequired());
    }

    /**
     * Teste que les booléens ne sont pas requis.
     */
    public function testBooleansAreNotRequired(): void
    {
        $form = $this->factory->create(TomeType::class);

        self::assertFalse($form->get('bought')->isRequired());
        self::assertFalse($form->get('downloaded')->isRequired());
        self::assertFalse($form->get('onNas')->isRequired());
    }

    /**
     * Teste que isbn et title ne sont pas requis.
     */
    public function testIsbnAndTitleAreNotRequired(): void
    {
        $form = $this->factory->create(TomeType::class);

        self::assertFalse($form->get('isbn')->isRequired());
        self::assertFalse($form->get('title')->isRequired());
    }

    /**
     * Teste les valeurs par défaut des booléens.
     */
    public function testBooleanDefaultValues(): void
    {
        $tome = new Tome();
        $form = $this->factory->create(TomeType::class, $tome);
        $form->submit([
            'number' => 1,
            // Booléens non soumis = false
        ]);

        self::assertTrue($form->isValid());
        self::assertFalse($tome->isBought());
        self::assertFalse($tome->isDownloaded());
        self::assertFalse($tome->isOnNas());
    }
}
