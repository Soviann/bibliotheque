<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Author;
use App\Form\AuthorAutocompleteType;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Tests fonctionnels pour AuthorAutocompleteType.
 */
#[CoversClass(AuthorAutocompleteType::class)]
class AuthorAutocompleteTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;
    private EntityManagerInterface $em;
    private AuthorRepository $authorRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->authorRepository = $this->em->getRepository(Author::class);
    }

    /**
     * Teste que le formulaire est configuré pour multiple.
     */
    public function testFormIsMultiple(): void
    {
        $form = $this->formFactory->create(AuthorAutocompleteType::class);

        self::assertTrue($form->getConfig()->getOption('multiple'));
    }

    /**
     * Teste que le formulaire n'est pas requis par défaut.
     */
    public function testFormIsNotRequired(): void
    {
        $form = $this->formFactory->create(AuthorAutocompleteType::class);

        self::assertFalse($form->isRequired());
    }

    /**
     * Teste la soumission d'IDs d'auteurs existants.
     */
    public function testSubmitExistingAuthorIds(): void
    {
        // Créer un auteur
        $author = new Author();
        $author->setName('Existing Author Test '.\uniqid());
        $this->em->persist($author);
        $this->em->flush();

        $form = $this->formFactory->create(AuthorAutocompleteType::class);
        $form->submit([(string) $author->getId()]);

        self::assertTrue($form->isSynchronized());

        $data = $form->getData();
        self::assertCount(1, $data);
        self::assertSame($author->getId(), $data->first()->getId());

        // Nettoyer
        $this->em->remove($author);
        $this->em->flush();
    }

    /**
     * Teste la création d'un nouvel auteur via le formulaire.
     */
    public function testSubmitNewAuthorName(): void
    {
        $newAuthorName = 'New Form Author '.\uniqid();

        $form = $this->formFactory->create(AuthorAutocompleteType::class);
        $form->submit([$newAuthorName]);

        self::assertTrue($form->isSynchronized());

        $data = $form->getData();
        self::assertCount(1, $data);

        $createdAuthor = $data->first();
        self::assertSame($newAuthorName, $createdAuthor->getName());

        // Vérifier que l'auteur est bien en base
        $foundAuthor = $this->authorRepository->findOneBy(['name' => $newAuthorName]);
        self::assertNotNull($foundAuthor);

        // Nettoyer
        $this->em->remove($createdAuthor);
        $this->em->flush();
    }

    /**
     * Teste la soumission d'un mélange d'IDs et de nouveaux noms.
     */
    public function testSubmitMixedIdsAndNames(): void
    {
        // Créer un auteur existant
        $existingAuthor = new Author();
        $existingAuthor->setName('Existing Mixed Author '.\uniqid());
        $this->em->persist($existingAuthor);
        $this->em->flush();

        $newAuthorName = 'New Mixed Author '.\uniqid();

        $form = $this->formFactory->create(AuthorAutocompleteType::class);
        $form->submit([
            (string) $existingAuthor->getId(),
            $newAuthorName,
        ]);

        self::assertTrue($form->isSynchronized());

        $data = $form->getData();
        self::assertCount(2, $data);

        // Nettoyer
        foreach ($data as $author) {
            $this->em->remove($author);
        }
        $this->em->flush();
    }

    /**
     * Teste que les noms vides sont ignorés.
     */
    public function testEmptyNamesAreIgnored(): void
    {
        $validName = 'Valid Author '.\uniqid();

        $form = $this->formFactory->create(AuthorAutocompleteType::class);
        $form->submit(['', $validName, '   ']);

        self::assertTrue($form->isSynchronized());

        $data = $form->getData();
        self::assertCount(1, $data);
        self::assertSame($validName, $data->first()->getName());

        // Nettoyer
        $this->em->remove($data->first());
        $this->em->flush();
    }

    /**
     * Teste la soumission vide.
     */
    public function testSubmitEmpty(): void
    {
        $form = $this->formFactory->create(AuthorAutocompleteType::class);
        $form->submit([]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        $data = $form->getData();
        self::assertCount(0, $data);
    }

    /**
     * Teste que le nom existant n'est pas dupliqué.
     */
    public function testExistingAuthorNameNotDuplicated(): void
    {
        $authorName = 'Not Duplicated Author '.\uniqid();

        // Créer l'auteur
        $existingAuthor = new Author();
        $existingAuthor->setName($authorName);
        $this->em->persist($existingAuthor);
        $this->em->flush();

        $existingId = $existingAuthor->getId();

        // Soumettre le même nom
        $form = $this->formFactory->create(AuthorAutocompleteType::class);
        $form->submit([$authorName]);

        $data = $form->getData();
        self::assertCount(1, $data);
        self::assertSame($existingId, $data->first()->getId());

        // Vérifier qu'il n'y a qu'un seul auteur avec ce nom
        $allWithName = $this->authorRepository->findBy(['name' => $authorName]);
        self::assertCount(1, $allWithName);

        // Nettoyer
        $this->em->remove($existingAuthor);
        $this->em->flush();
    }

    /**
     * Teste que tom_select_options est configuré pour la création.
     */
    public function testTomSelectOptionsAreConfigured(): void
    {
        $form = $this->formFactory->create(AuthorAutocompleteType::class);

        $options = $form->getConfig()->getOption('tom_select_options');
        self::assertTrue($options['create']);
        self::assertTrue($options['createOnBlur']);
    }
}
