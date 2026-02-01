<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Symfony\CodeQuality\Rector\Class_\ControllerMethodInjectionToConstructorRector;
use Rector\Symfony\CodeQuality\Rector\Class_\InlineClassRoutePrefixRector;
use Rector\Symfony\Set\SymfonySetList;

/*
 * Configuration Rector pour le projet Bibliothèque.
 *
 * Rector est un outil de refactoring automatique PHP. Cette configuration
 * est volontairement conservatrice : elle applique uniquement les règles
 * qui améliorent le code sans risque de régression.
 *
 * Usage :
 * - Dry-run (voir les changements) : ddev exec vendor/bin/rector process --dry-run
 * - Appliquer les changements :      ddev exec vendor/bin/rector process
 * - Sur un fichier spécifique :      ddev exec vendor/bin/rector process src/MonFichier.php
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        // Migrations Doctrine : générées automatiquement, ne pas modifier
        __DIR__.'/src/Migrations',

        // DataFixtures : patterns spécifiques acceptables
        __DIR__.'/src/DataFixtures',

        // #[Override] ajoute du bruit visuel sans valeur significative
        AddOverrideAttributeToOverriddenMethodsRector::class,

        // L'injection par action (autowiring dans les paramètres de méthode)
        // est un pattern Symfony valide et souvent préférable pour les contrôleurs
        ControllerMethodInjectionToConstructorRector::class,

        // Conserver les préfixes de route sur la classe pour la lisibilité
        InlineClassRoutePrefixRector::class,
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withSets([
        SymfonySetList::SYMFONY_74,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        // Note : SYMFONY_CONSTRUCTOR_INJECTION volontairement omis
        // L'injection par action (autowiring dans les paramètres) est un pattern
        // valide en Symfony et souvent préférable pour les contrôleurs légers.
    ])
    ->withImportNames(removeUnusedImports: true);
