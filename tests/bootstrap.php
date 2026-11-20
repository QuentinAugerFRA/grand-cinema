<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
// Même fuseau horaire que l'application (voir src/Kernel.php)
date_default_timezone_set('Europe/Paris');

// La base de test est recréée avant chaque lancement des tests
$connexion = new PDO($_ENV['DATABASE_DSN'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']);
$connexion->exec(file_get_contents(dirname(__DIR__) . '/base-de-donnees/schema.sql'));
$connexion->exec(file_get_contents(dirname(__DIR__) . '/base-de-donnees/donnees.sql'));
