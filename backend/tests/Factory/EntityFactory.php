<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Entity\User;
use App\Enum\ComicStatus;
use App\Enum\ComicType;

/**
 * Fabrique d'entités pour les tests.
 */
final class EntityFactory
{
    public static function createAuthor(string $name = 'Test Author'): Author
    {
        $author = new Author();
        $author->setName($name);

        return $author;
    }

    public static function createComicSeries(
        string $title = 'Test Series',
        ComicStatus $status = ComicStatus::BUYING,
        ComicType $type = ComicType::BD,
    ): ComicSeries {
        $comic = new ComicSeries();
        $comic->setTitle($title);
        $comic->setStatus($status);
        $comic->setType($type);

        return $comic;
    }

    public static function createTome(
        int $number = 1,
        bool $bought = false,
        bool $downloaded = false,
        bool $onNas = false,
        bool $read = false,
    ): Tome {
        $tome = new Tome();
        $tome->setNumber($number);
        $tome->setBought($bought);
        $tome->setDownloaded($downloaded);
        $tome->setOnNas($onNas);
        $tome->setRead($read);

        return $tome;
    }

    public static function createUser(
        string $email = 'test@example.com',
        ?string $googleId = 'test-google-id',
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setGoogleId($googleId);
        $user->setRoles(['ROLE_USER']);

        return $user;
    }
}
