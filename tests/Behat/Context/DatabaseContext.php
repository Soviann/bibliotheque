<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Entity\User;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Contexte pour la gestion de la base de données de test.
 */
final readonly class DatabaseContext implements Context
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Réinitialise la base de données avant chaque scénario.
     *
     * @BeforeScenario
     */
    public function resetDatabase(BeforeScenarioScope $scope): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        // Supprime et recrée le schéma
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Crée l'utilisateur de test
        $this->createTestUser();

        $this->entityManager->clear();
    }

    /**
     * Crée un utilisateur de test (ou vérifie qu'il existe déjà).
     *
     * @Given un utilisateur :email avec le mot de passe :password existe
     */
    public function unUtilisateurExiste(string $email, string $password): void
    {
        // Vérifie si l'utilisateur existe déjà (créé par le hook @BeforeScenario)
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null !== $existingUser) {
            return;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Crée un auteur.
     *
     * @Given un auteur :name existe
     */
    public function unAuteurExiste(string $name): void
    {
        $author = new Author();
        $author->setName($name);

        $this->entityManager->persist($author);
        $this->entityManager->flush();
    }

    /**
     * Crée une série BD simple.
     *
     * @Given une série BD :title existe
     */
    public function uneSerieBdExiste(string $title): void
    {
        $comic = new ComicSeries();
        $comic->setTitle($title);
        $comic->setType(ComicType::BD);
        $comic->setStatus(ComicStatus::BUYING);

        $this->entityManager->persist($comic);
        $this->entityManager->flush();
    }

    /**
     * Crée une série manga.
     *
     * @Given une série manga :title existe
     */
    public function uneSerieMangaExiste(string $title): void
    {
        $comic = new ComicSeries();
        $comic->setTitle($title);
        $comic->setType(ComicType::MANGA);
        $comic->setStatus(ComicStatus::BUYING);

        $this->entityManager->persist($comic);
        $this->entityManager->flush();
    }

    /**
     * Crée une série comics.
     *
     * @Given une série comics :title existe
     */
    public function uneSerieComicsExiste(string $title): void
    {
        $comic = new ComicSeries();
        $comic->setTitle($title);
        $comic->setType(ComicType::COMICS);
        $comic->setStatus(ComicStatus::BUYING);

        $this->entityManager->persist($comic);
        $this->entityManager->flush();
    }

    /**
     * Crée une série dans la wishlist.
     *
     * @Given une série wishlist :title existe
     */
    public function uneSerieWishlistExiste(string $title): void
    {
        $comic = new ComicSeries();
        // isWishlist est calculé automatiquement à partir du statut
        $comic->setStatus(ComicStatus::WISHLIST);
        $comic->setTitle($title);
        $comic->setType(ComicType::BD);

        $this->entityManager->persist($comic);
        $this->entityManager->flush();
    }

    /**
     * Crée une série avec un statut spécifique.
     *
     * @Given une série :title avec le statut :status existe
     */
    public function uneSerieAvecStatutExiste(string $title, string $status): void
    {
        $statusEnum = match ($status) {
            'En cours d\'achat', 'buying' => ComicStatus::BUYING,
            'Terminée', 'finished' => ComicStatus::FINISHED,
            'Arrêtée', 'stopped' => ComicStatus::STOPPED,
            'wishlist' => ComicStatus::WISHLIST,
            default => throw new \InvalidArgumentException(\sprintf('Statut inconnu: %s', $status)),
        };

        $comic = new ComicSeries();
        // isWishlist est calculé automatiquement à partir du statut
        $comic->setStatus($statusEnum);
        $comic->setTitle($title);
        $comic->setType(ComicType::BD);

        $this->entityManager->persist($comic);
        $this->entityManager->flush();
    }

    /**
     * Crée un one-shot.
     *
     * @Given un one-shot :title existe
     */
    public function unOneShotExiste(string $title): void
    {
        $comic = new ComicSeries();
        $comic->setIsOneShot(true);
        $comic->setStatus(ComicStatus::BUYING);
        $comic->setTitle($title);
        $comic->setType(ComicType::BD);

        $this->entityManager->persist($comic);
        $this->entityManager->flush();
    }

    /**
     * Crée une série avec des tomes sur le NAS.
     *
     * @Given une série :title avec des tomes sur le NAS existe
     */
    public function uneSerieAvecTomesSurNasExiste(string $title): void
    {
        $comic = new ComicSeries();
        $comic->setStatus(ComicStatus::BUYING);
        $comic->setTitle($title);
        $comic->setType(ComicType::BD);

        $tome = new Tome();
        $tome->setBought(true);
        $tome->setNumber(1);
        $tome->setOnNas(true);
        $comic->addTome($tome);

        $this->entityManager->persist($comic);
        $this->entityManager->flush();
    }

    /**
     * Crée une série avec des tomes (non sur le NAS).
     *
     * @Given une série :title avec des tomes existe
     */
    public function uneSerieAvecTomesExiste(string $title): void
    {
        $comic = new ComicSeries();
        $comic->setStatus(ComicStatus::BUYING);
        $comic->setTitle($title);
        $comic->setType(ComicType::BD);

        $tome = new Tome();
        $tome->setBought(true);
        $tome->setNumber(1);
        $tome->setOnNas(false);
        $comic->addTome($tome);

        $this->entityManager->persist($comic);
        $this->entityManager->flush();
    }

    /**
     * Crée l'utilisateur de test par défaut.
     */
    private function createTestUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $user->setRoles(['ROLE_USER']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
