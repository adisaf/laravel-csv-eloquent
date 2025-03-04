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

Structure basée sur le schéma réel de l'API:

```
id,merchant_id,customer_id,otp_code,otp_expired_at,fee_id,payment_token,notify_token,transaction_id,merchant_transaction_id,currency,amount,fee_amount,merchant_amount,designation,notify_url,success_url,failed_url,notify_with_api_status,status,api_status,api_message,pay_at,details,lang,created_at,updated_at,client_name,client_surname,client_phone_number,client_email,merchant_customer_id,check_operator_status,sim_id,phone_number,country,carrier_name,client_ip_address,client_user_agent,value_date,sim_name,updated_strategy,notify_context_data,number_of_status_check_attempts,completed_at,refund_transaction_id,refund_at,channel
2327180,887,525565,,,36,1eddeba201f76f4a80800adccf5945f7abde790711884ed0837a3ea210ce6358,893be12d348047ecaf43898dc255c404,25740dd9f7744c79be842c981ef5ea35,37498445,XOF,3000,180.0,2820.0,Account deposit,https://bw-pay-gw.com/payments/postback/paymetrust,https://zjumapxjws.com/onpay/success/,https://zjumapxjws.com/onpay/fail/,200,Y,200,SUCCESS,2023-04-19T13:59:13,,fr,2023-04-19T13:57:30,2023-04-19T13:59:14,Prince,Gogbeu,,gogbeudesire202@gmail.com,5cb39fa1-dda9-4dda-bc0d-8d7359bdfda3,N,34,+2250759979902,CI,OM,172.71.131.175,Mozilla/5.0 (Linux; Android 10; Infinix X657 Build/QP1A.190711.020; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/109.0.5414.117 Mobile Safari/537.36,2023-04-20T00:00:00,OM_CI,AUTO,,-1,,,,,
...
```

### transferts.csv

Structure basée sur le schéma réel de l'API:

```
id,sender_id,receiver_id,fee_id,transaction_id,merchant_transaction_id,currency,amount,fee_amount,merchant_amount,designation,status,api_status,api_message,pay_at,details,created_at,updated_at,merchant_customer_id,sim_id,country,carrier_name,phone_number,notify_url,notify_with_api_status,sim_name,notify_token,updated_strategy,merchant_id,carrier_name_received,notify_context_data,creation_date
3414145,887,,34,fea82c83-0a9e-40b5-8c4e-1e94b40b0d24,109879954,XOF,50000,2750.0,52750.0,Account withdraw,Y,200,SUCCESS,,,2024-05-07T21:06:19,2024-05-07T21:06:20,,6,CI,WAVE_CI,+2250151606660,https://bw-pay-gw.com/payments/postback/paymetrust,,WAVE_PAYOUT_CI,155597ad5f0e4488882dbb27060b1d4b,AUTO,,,,,2024-05-07T00:00:00
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
