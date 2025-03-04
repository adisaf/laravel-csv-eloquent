# Tests d'intégration manuels

Ce dossier contient des tests d'intégration manuels pour vérifier le bon fonctionnement du module CSV Eloquent
avec des données réelles. Ces tests ne sont pas inclus dans le CI/CD GitHub Actions.

## Configuration préalable

Avant d'exécuter ces tests, vous devez configurer votre environnement :

1. Assurez-vous que l'API CSV est accessible et contient les fichiers CSV suivants :
    - `paiements.csv`
    - `transferts.csv`

2. Configurez les variables d'environnement suivantes :
   ```bash
   export CSV_API_URL=https://votre-api-csv.exemple.com
   export CSV_API_USERNAME=votre_utilisateur
   export CSV_API_PASSWORD=votre_mot_de_passe
   ```

3. Vérifiez la structure des fichiers CSV pour qu'elle corresponde aux modèles. Si nécessaire, modifiez les mappages de
   colonnes dans les modèles de test.

## Structure attendue des fichiers CSV

### paiements.csv

```
id,montant,statut,reference,date_paiement,created_at,updated_at,deleted_at
1,1500.00,Y,PAY-123456,2025-01-15,2025-01-15 10:30:00,2025-01-15 10:30:00,
2,2000.50,Y,PAY-654321,2025-01-16,2025-01-16 11:45:00,2025-01-16 11:45:00,
3,500.75,P,PAY-987654,2025-01-17,2025-01-17 09:15:00,2025-01-17 09:15:00,
...
```

### transferts.csv

```
id,montant,statut,reference_paiement,date_transfert,compte_destinataire,compte_emetteur,created_at,updated_at,deleted_at
1,1500.00,completed,PAY-123456,2025-01-15,DEST-001,SRC-001,2025-01-15 11:00:00,2025-01-15 11:00:00,
2,2000.50,completed,PAY-654321,2025-01-16,DEST-002,SRC-002,2025-01-16 12:15:00,2025-01-16 12:15:00,
3,500.75,pending,PAY-987654,2025-01-17,DEST-003,SRC-003,2025-01-17 09:45:00,2025-01-17 09:45:00,
...
```

## Exécution des tests

Pour exécuter ces tests manuels, utilisez la commande suivante :

```bash
php vendor/bin/phpunit tests/Manual/IntegrationTest.php
```

## Points testés

Ce test d'intégration vérifie plusieurs aspects du module CSV Eloquent :

1. **Chargement des modèles** : Vérifie que les modèles Payment et Transfer peuvent être chargés et interrogés.
2. **Filtrage** : Teste les clauses where sur les deux modèles.
3. **Tri** : Vérifie que les résultats peuvent être triés correctement.
4. **Pagination** : Teste la pagination des résultats.
5. **Relations simulées** : Simule les relations entre paiements et transferts.
6. **Fonctionnalités avancées** : Teste le groupement et l'agrégation.

## Interprétation des résultats

Les résultats du test sont affichés de manière détaillée dans la console. Pour chaque section du test,
vous verrez un résumé des données testées. Si un problème survient, le test échouera avec un message
explicatif.

Les tests qui passent confirment que le module fonctionne correctement avec votre API CSV et que
l'intégration entre les modèles fonctionne comme prévu.
