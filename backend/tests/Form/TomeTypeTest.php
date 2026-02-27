<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Dto\Input\TomeInput;
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
            'read' => true,
            'title' => 'Le Retour du Héros',
        ];

        $input = new TomeInput();
        $form = $this->factory->create(TomeType::class, $input);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertSame(5, $input->number);
        self::assertTrue($input->bought);
        self::assertFalse($input->downloaded);
        self::assertTrue($input->onNas);
        self::assertTrue($input->read);
        self::assertSame('978-2-505-00123-4', $input->isbn);
        self::assertSame('Le Retour du Héros', $input->title);
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
            'read' => false,
            'title' => '',
        ];

        $input = new TomeInput();
        $form = $this->factory->create(TomeType::class, $input);
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

        self::assertTrue($form->has('bought'));
        self::assertTrue($form->has('downloaded'));
        self::assertTrue($form->has('isbn'));
        self::assertTrue($form->has('number'));
        self::assertTrue($form->has('onNas'));
        self::assertTrue($form->has('read'));
        self::assertTrue($form->has('title'));
    }

    /**
     * Teste que le formulaire pré-remplit les données existantes.
     */
    public function testFormPrefillsExistingData(): void
    {
        $input = new TomeInput();
        $input->number = 10;
        $input->bought = true;
        $input->downloaded = true;
        $input->onNas = true;
        $input->read = true;
        $input->isbn = '123-456';
        $input->title = 'Existing Title';

        $form = $this->factory->create(TomeType::class, $input);

        $view = $form->createView();

        self::assertSame('10', $view->children['number']->vars['value']);
        self::assertTrue($view->children['bought']->vars['checked']);
        self::assertTrue($view->children['downloaded']->vars['checked']);
        self::assertTrue($view->children['onNas']->vars['checked']);
        self::assertTrue($view->children['read']->vars['checked']);
        self::assertSame('123-456', $view->children['isbn']->vars['value']);
        self::assertSame('Existing Title', $view->children['title']->vars['value']);
    }

    /**
     * Teste que le data_class est correctement configuré.
     */
    public function testDataClassIsCorrect(): void
    {
        $form = $this->factory->create(TomeType::class);

        self::assertSame(TomeInput::class, $form->getConfig()->getDataClass());
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
        self::assertFalse($form->get('read')->isRequired());
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
        $input = new TomeInput();
        $form = $this->factory->create(TomeType::class, $input);
        $form->submit([
            'number' => 1,
            // Booléens non soumis = false
        ]);

        self::assertTrue($form->isValid());
        self::assertFalse($input->bought);
        self::assertFalse($input->downloaded);
        self::assertFalse($input->onNas);
        self::assertFalse($input->read);
    }
}
