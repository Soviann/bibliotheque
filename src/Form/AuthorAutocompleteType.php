<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * Champ d'autocomplétion pour les auteurs avec création à la volée.
 */
#[AsEntityAutocompleteField]
class AuthorAutocompleteType extends AbstractType
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $authorRepository = $this->authorRepository;

        // PRE_SUBMIT : crée les auteurs et convertit les noms en IDs
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            static function (FormEvent $event) use ($authorRepository): void {
                $data = $event->getData();

                if (!\is_array($data)) {
                    return;
                }

                $newData = [];
                foreach ($data as $value) {
                    // Si c'est un ID numérique, l'auteur existe déjà
                    if (\is_numeric($value)) {
                        $newData[] = $value;
                        continue;
                    }

                    // Sinon, c'est un nouveau nom d'auteur à créer
                    $name = \trim($value);
                    if ('' === $name) {
                        continue;
                    }

                    // Vérifie si l'auteur existe déjà par son nom
                    $author = $authorRepository->findOneBy(['name' => $name]);

                    if (null === $author) {
                        // Crée le nouvel auteur
                        $author = new Author();
                        $author->setName($name);
                        $authorRepository->getEntityManager()->persist($author);
                        $authorRepository->getEntityManager()->flush();
                    }

                    $newData[] = (string) $author->getId();
                }

                $event->setData($newData);
            },
            1000000 // Priorité très haute
        );

        // SUBMIT : convertit les IDs en entités Author
        $builder->addEventListener(
            FormEvents::SUBMIT,
            static function (FormEvent $event) use ($authorRepository): void {
                $data = $event->getData();

                // Si les données sont déjà une collection d'entités, ne rien faire
                if ($data instanceof ArrayCollection || (\is_array($data) && isset($data[0]) && $data[0] instanceof Author)) {
                    return;
                }

                if (!\is_array($data) && !$data instanceof \Traversable) {
                    $event->setData(new ArrayCollection());

                    return;
                }

                $authors = new ArrayCollection();
                foreach ($data as $item) {
                    if ($item instanceof Author) {
                        $authors->add($item);
                        continue;
                    }

                    if (\is_numeric($item)) {
                        $author = $authorRepository->find((int) $item);
                        if (null !== $author) {
                            $authors->add($author);
                        }
                    }
                }

                $event->setData($authors);
            },
            -1000 // Priorité basse pour s'exécuter après la transformation
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Author::class,
            'choice_label' => 'name',
            'multiple' => true,
            'placeholder' => 'Rechercher un auteur...',
            'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository
                ->createQueryBuilder('a')
                ->orderBy('a.name', 'ASC'),
            'required' => false,
            'tom_select_options' => [
                'create' => true,
                'createOnBlur' => true,
            ],
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
