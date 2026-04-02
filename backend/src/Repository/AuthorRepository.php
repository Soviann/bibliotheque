<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    /**
     * Cache mémoire des auteurs créés mais pas encore flushés (clé = nom en minuscule).
     *
     * @var array<string, Author>
     */
    private array $pendingAuthors = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }

    /**
     * Retourne les auteurs suivis avec leurs séries pré-chargées (évite N+1).
     *
     * @return list<Author>
     */
    public function findFollowed(): array
    {
        /** @var list<Author> $result */
        $result = $this->createQueryBuilder('a')
            ->leftJoin('a.comicSeries', 'cs')
            ->addSelect('cs')
            ->where('a.followedForNewSeries = true')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Trouve ou crée un auteur par son nom.
     */
    public function findOrCreate(string $name): Author
    {
        $name = \trim($name);
        $key = self::normalizeKey($name);

        if (isset($this->pendingAuthors[$key])) {
            return $this->pendingAuthors[$key];
        }

        $author = $this->findOneBy(['name' => $name]);

        if (null === $author) {
            $author = new Author();
            $author->setName($name);
            $this->getEntityManager()->persist($author);
            $this->pendingAuthors[$key] = $author;
        }

        return $author;
    }

    /**
     * Trouve ou crée plusieurs auteurs à partir d'une liste de noms.
     *
     * @param string[] $names
     *
     * @return Author[]
     */
    public function findOrCreateMultiple(array $names): array
    {
        $authors = [];

        foreach ($names as $name) {
            $name = \trim($name);
            if ('' !== $name) {
                $authors[] = $this->findOrCreate($name);
            }
        }

        return $authors;
    }

    /**
     * Normalise une clé pour la rendre insensible aux accents et à la casse.
     *
     * Correspond au comportement de la collation utf8mb4_unicode_ci de MariaDB.
     */
    private static function normalizeKey(string $name): string
    {
        $key = \mb_strtolower($name);

        // Décompose les caractères accentués (é → e + accent combinant), puis supprime les accents
        $decomposed = \Normalizer::normalize($key, \Normalizer::NFD);

        if (false === $decomposed) {
            return $key;
        }

        return \preg_replace('/\p{Mn}/u', '', $decomposed) ?? $key;
    }
}
