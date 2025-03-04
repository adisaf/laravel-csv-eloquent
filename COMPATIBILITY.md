# Compatibilité avec Laravel

Ce document détaille la compatibilité du package `paymetrust/laravel-csv-eloquent` avec les différentes versions de
Laravel et PHP.

## Versions supportées

| Version de Laravel | Version de PHP minimale requise | État du support |
|--------------------|---------------------------------|-----------------|
| 8.x                | PHP 8.0                         | ✅ Supporté      |
| 9.x                | PHP 8.0                         | ✅ Supporté      |
| 10.x               | PHP 8.1                         | ✅ Supporté      |
| 11.x               | PHP 8.2                         | ✅ Supporté      |
| 12.x               | PHP 8.2                         | ✅ Supporté      |

## Détails de compatibilité

### Laravel 8.x

- Tous les tests passent sur PHP 8.0, 8.1
- Supporte toutes les fonctionnalités de base du package

### Laravel 9.x

- Tous les tests passent sur PHP 8.0, 8.1, 8.2
- Supporte toutes les fonctionnalités de base du package

### Laravel 10.x

- Tous les tests passent sur PHP 8.1, 8.2, 8.3
- Supporte toutes les fonctionnalités de base du package
- Utilisez la dernière version du package pour une compatibilité optimale

### Laravel 11.x

- Tous les tests passent sur PHP 8.2, 8.3
- Supporte toutes les fonctionnalités de base du package
- Utilisez la dernière version du package pour une compatibilité optimale

### Laravel 12.x

- Tous les tests passent sur PHP 8.2, 8.3
- Supporte toutes les fonctionnalités de base du package
- Utilisez la dernière version du package pour une compatibilité optimale

## Notes importantes

- Pour les projets Laravel 12.x, assurez-vous d'utiliser PHP 8.2 ou supérieur
- Lorsque vous utilisez des fonctionnalités spécifiques à certaines versions de Laravel, consultez la documentation de
  Laravel
- Des adaptations mineures peuvent être nécessaires selon les changements dans les versions majeures de Laravel

## Mises à jour et maintenance

Notre équipe s'engage à maintenir la compatibilité avec les nouvelles versions de Laravel dans les délais suivants :

- Compatibilité avec les versions mineures : dans les 2 semaines suivant la sortie
- Compatibilité avec les versions majeures : dans les 4 semaines suivant la sortie

Pour signaler des problèmes de compatibilité ou obtenir de l'aide, veuillez ouvrir un ticket dans le dépôt GitHub du
projet.
