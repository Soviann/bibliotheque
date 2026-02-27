<?php

declare(strict_types=1);

use App\Kernel;

require_once \dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    // Force l'environnement test pour le virtualhost test.bibliotheque.ddev.site
    // utilisé par les tests Behat avec Selenium/Chrome
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (\str_starts_with($host, 'test.')) {
        $context['APP_ENV'] = 'test';
        $context['APP_DEBUG'] = true;
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
