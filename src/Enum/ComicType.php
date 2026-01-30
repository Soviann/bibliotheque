<?php

namespace App\Enum;

enum ComicType: string
{
    case BD = 'bd';
    case COMICS = 'comics';
    case LIVRE = 'livre';
    case MANGA = 'manga';

    public function getLabel(): string
    {
        return match ($this) {
            self::BD => 'BD',
            self::COMICS => 'Comics',
            self::LIVRE => 'Livre',
            self::MANGA => 'Manga',
        };
    }
}
