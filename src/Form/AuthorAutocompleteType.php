<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Author;
use App\Repository\AuthorRepository;
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
        // Écoute PRE_SUBMIT pour créer les auteurs qui n'existent pas
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
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
                $author = $this->authorRepository->findOneBy(['name' => $name]);

                if (null === $author) {
                    // Crée le nouvel auteur
                    $author = new Author();
                    $author->setName($name);
                    $this->authorRepository->getEntityManager()->persist($author);
                    $this->authorRepository->getEntityManager()->flush();
                }

                $newData[] = (string) $author->getId();
            }

            $event->setData($newData);
        });
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
