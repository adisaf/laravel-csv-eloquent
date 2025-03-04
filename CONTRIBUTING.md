# Guide de contribution

Merci de votre intérêt pour contribuer à Laravel CSV Eloquent ! Votre aide est précieuse pour améliorer ce package.

## Processus de contribution

1. **Forker le projet** sur GitHub.
2. **Créer une branche** pour votre fonctionnalité ou correction.
   ```bash
   git checkout -b feature/nom-de-votre-fonctionnalite
   ```
   ou
   ```bash
   git checkout -b fix/correction-bug
   ```
3. **Implémenter** vos modifications.
4. **Lancer les tests** pour vous assurer que tout fonctionne.
   ```bash
   composer test
   ```
5. **Vérifier le style de code** selon les standards Laravel.
   ```bash
   composer lint
   ```
6. **Exécuter l'analyse statique** avec PHPStan.
   ```bash
   composer stan
   ```
7. **Soumettre une Pull Request** avec une description claire de vos modifications.

## Standards de code

Ce projet suit les standards PSR-12 et les conventions de Laravel. Veuillez vous assurer que votre code est formaté avec
Laravel Pint :

```bash
composer pint
```

## Tests

Tous les nouveaux codes doivent être couverts par des tests. Nous utilisons PHPUnit pour les tests :

```bash
composer test
```

## Guide de style pour les Pull Requests

- Utilisez un titre clair et descriptif
- Incluez des références aux issues pertinentes (#123)
- Décrivez en détail les modifications apportées
- Mentionnez les étapes de test pour vérifier vos modifications
- Documentez les nouvelles fonctionnalités ou les changements de comportement

## Documentation

Si vous ajoutez de nouvelles fonctionnalités, assurez-vous de mettre à jour la documentation dans le README.md ou la
documentation correspondante.

## Questions ?

Si vous avez des questions sur le processus de contribution, n'hésitez pas à ouvrir une issue sur GitHub.

Merci pour votre contribution !
