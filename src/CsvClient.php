<?php

namespace Adisaf\CsvEloquent;

use Adisaf\CsvEloquent\Exceptions\CsvApiException;
use Adisaf\CsvEloquent\Helpers\Formatter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CsvClient
{
    /**
     * URL de base de l'API CSV.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Nom d'utilisateur pour l'authentification basic.
     *
     * @var string
     */
    protected $username;

    /**
     * Mot de passe pour l'authentification basic.
     *
     * @var string
     */
    protected $password;

    /**
     * Durée de mise en cache des réponses en secondes.
     *
     * @var int
     */
    protected $cacheTtl;

    /**
     * Crée une nouvelle instance CsvClient.
     *
     * @return void
     */
    public function __construct()
    {
        $this->baseUrl = config('csv-eloquent.api_url');
        $this->username = config('csv-eloquent.username');
        $this->password = config('csv-eloquent.password');
        $this->cacheTtl = config('csv-eloquent.cache_ttl', 60);
    }

    /**
     * Obtient la liste des fichiers CSV disponibles.
     *
     * @return array
     *
     * @throws \Adisaf\CsvEloquent\Exceptions\CsvApiException
     */
    public function getFiles()
    {
        $cacheKey = 'csv_api_files';

        if (app()->bound('cache')) {
            return Cache::remember($cacheKey, $this->cacheTtl, function () {
                return $this->makeRequest('GET', '/api/');
            });
        }

        return $this->makeRequest('GET', '/api/');
    }

    /**
     * Obtient les données d'un fichier CSV.
     *
     * @param string $file
     *
     * @return array
     *
     * @throws \Adisaf\CsvEloquent\Exceptions\CsvApiException
     */
    public function getData($file, array $params = [])
    {
        // Vérification du nom de fichier
        $originalFile = $file;

        // Enlever l'extension .csv du nom de fichier s'il est présent
        $file = str_replace('.csv', '', $file);

        // Débogage du nom de fichier
        if (config('csv-eloquent.debug', false)) {
            Log::debug("Nom de fichier original: {$originalFile}, Utilisé pour l'API: {$file}");
        }

        // Transformation des paramètres pour l'API externe
        if (isset($params['pagination'])) {
            // Garder une copie des paramètres d'origine pour débogage
            $originalParams = $params['pagination'];

            // Conversion 'limit' en 'pageSize'
            if (isset($params['pagination']['limit'])) {
                $params['pagination']['pageSize'] = $params['pagination']['limit'];
                unset($params['pagination']['limit']);
            }

            // Conversion 'start' (offset) en numéro de page
            if (isset($params['pagination']['start'])) {
                if (isset($params['pagination']['pageSize']) && $params['pagination']['pageSize'] > 0) {
                    $params['pagination']['page'] = floor($params['pagination']['start'] / $params['pagination']['pageSize']) + 1;
                } else {
                    $params['pagination']['page'] = 1;
                }
                unset($params['pagination']['start']);
            }

            // S'assurer que page est au moins 1
            if (isset($params['pagination']['page']) && $params['pagination']['page'] < 1) {
                $params['pagination']['page'] = 1;
            }

            // Assurer que withCount est toujours présent pour obtenir le total
            $params['pagination']['withCount'] = true;

            if (config('csv-eloquent.debug', false)) {
                Log::debug('Paramètres de pagination transformés:', [
                    'original' => $originalParams,
                    'transformed' => $params['pagination'],
                ]);
            }
        }

        // Formatage des dates et des paramètres between
        Formatter::formatDateTime($params);
        Formatter::transformBetween($params);

        // Génération de la clé de cache
        $cacheKey = 'csv_api_data_'.$file.'_'.md5(json_encode($params));

        if (app()->bound('cache')) {
            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($file, $params) {
                $response = $this->makeRequest('GET', '/api/'.$file, $params);

                // Loguer la réponse de l'API pour le débogage
                if (config('csv-eloquent.debug', false)) {
                    Log::debug("Réponse de l'API pour {$file}:", [
                        'meta' => $response['meta'] ?? 'Aucune métadonnée',
                        'total_records' => isset($response['meta']['pagination']['total']) ?
                            $response['meta']['pagination']['total'] :
                            (isset($response['meta']['pagination']['totalRecords']) ?
                                $response['meta']['pagination']['totalRecords'] : 'Non disponible'),
                    ]);
                }

                // Normaliser la structure des métadonnées de pagination
                $response = $this->normalizeResponse($response);

                return $response;
            });
        }

        $response = $this->makeRequest('GET', '/api/'.$file, $params);

        return $this->normalizeResponse($response);
    }

    /**
     * Normalise la structure de la réponse pour assurer la compatibilité.
     *
     * @return array
     */
    protected function normalizeResponse(array $response)
    {
        // Vérifier si nous avons des métadonnées de pagination
        if (isset($response['meta']) && isset($response['meta']['pagination'])) {
            $pagination = $response['meta']['pagination'];

            // Assurer une structure complète avec tous les champs nécessaires
            $normalizedPagination = [
                'current_page' => isset($pagination['page']) ? (int) $pagination['page'] : 1,
                'per_page' => isset($pagination['pageSize']) ? (int) $pagination['pageSize'] : 15,
                'last_page' => isset($pagination['pageCount']) ? (int) $pagination['pageCount'] : 1,
                'total' => isset($pagination['total']) ? (int) $pagination['total'] : 0,
                'totalRecords' => isset($pagination['total']) ? (int) $pagination['total'] : 0,
            ];

            // Calculer from/to pour la compatibilité complète avec Laravel
            $normalizedPagination['from'] = ($normalizedPagination['current_page'] - 1) * $normalizedPagination['per_page'] + 1;
            $normalizedPagination['to'] = min(
                $normalizedPagination['from'] + $normalizedPagination['per_page'] - 1,
                $normalizedPagination['total']
            );

            // Remplacer la structure originale par notre structure normalisée
            $response['meta']['pagination'] = $normalizedPagination;

            // Pour une compatibilité maximale, dupliquer certaines valeurs au niveau racine
            // Nova les recherche parfois ici
            if (! isset($response['total'])) {
                $response['total'] = $normalizedPagination['total'];
            }
        }

        return $response;
    }

    /**
     * Obtient le schéma d'un fichier CSV.
     *
     * @param string $file
     *
     * @return array
     *
     * @throws \Adisaf\CsvEloquent\Exceptions\CsvApiException
     */
    public function getSchema($file)
    {
        // Enlever l'extension .csv du nom de fichier s'il est présent
        $file = str_replace('.csv', '', $file);

        $cacheKey = 'csv_api_schema_'.$file;

        if (app()->bound('cache')) {
            return Cache::remember($cacheKey, $this->cacheTtl * 10, function () use ($file) {
                return $this->makeRequest('GET', '/api/'.$file.'/schema');
            });
        }

        return $this->makeRequest('GET', '/api/'.$file.'/schema');
    }

    /**
     * Effectue une requête HTTP vers l'API CSV.
     *
     * @param string $method
     * @param string $endpoint
     *
     * @return array
     *
     * @throws \Adisaf\CsvEloquent\Exceptions\CsvApiException
     */
    protected function makeRequest($method, $endpoint, array $params = [])
    {
        try {
            // Assurer que l'endpoint est correctement formaté
            if (config('csv-eloquent.debug', false)) {
                Log::debug("Endpoint API: {$endpoint}");
                if (! empty($params)) {
                    Log::debug('Paramètres de requête:', $params);
                }
            }

            $url = rtrim($this->baseUrl, '/').$endpoint;

            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(30)
                ->retry(3, 1000)
                ->$method($url, $params);

            // Débogage de la réponse
            if (config('csv-eloquent.debug', false)) {
                Log::debug('Statut API: '.$response->status());
            }

            if ($response->failed()) {
                if (app()->bound('log')) {
                    Log::error('Échec de la requête API CSV', [
                        'url' => $url,
                        'status' => $response->status(),
                        'response' => $response->json() ?? $response->body(),
                    ]);
                }

                throw new CsvApiException(
                    'Échec de la requête API CSV: '.$response->status(),
                    $response->status()
                );
            }

            return $response->json();
        } catch (\Exception $e) {
            if (! $e instanceof CsvApiException) {
                if (app()->bound('log')) {
                    Log::error('Exception lors de la requête API CSV', [
                        'exception' => $e->getMessage(),
                        'endpoint' => $endpoint,
                    ]);
                }

                throw new CsvApiException(
                    'Exception lors de la requête API CSV: '.$e->getMessage(),
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    /**
     * Vide le cache pour un fichier CSV spécifique.
     *
     * @param string|null $file
     *
     * @return void
     */
    public function clearCache($file = null)
    {
        if (! app()->bound('cache')) {
            return;
        }

        if ($file) {
            Cache::forget('csv_api_schema_'.$file);
            // Pour les clés avec pattern, nous utilisons une approche plus précise
            $cachePattern = 'csv_api_data_'.$file.'_';

            // Cette méthode de suppression dépend de l'implémentation du cache
            // et peut ne pas fonctionner avec tous les drivers
            if (method_exists(Cache::getStore(), 'getPrefix')) {
                $prefix = Cache::getStore()->getPrefix();
                Cache::getStore()->getRedis()->eval(
                    "local keys = redis.call('keys', ARGV[1]) for i=1,#keys,5000 do redis.call('del', unpack(redis.call('mget', unpack(keys, i, math.min(i+4999, #keys))))) end return keys",
                    0,
                    $prefix.$cachePattern.'*'
                );
            } else {
                // Si le driver ne supporte pas cette méthode, on vide tout le cache
                Cache::flush();
            }
        } else {
            // Vide tous les caches liés à l'API CSV
            Cache::flush();
        }
    }
}
