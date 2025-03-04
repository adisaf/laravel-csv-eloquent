<?php

namespace Adisaf\CsvEloquent;

use Adisaf\CsvEloquent\Exceptions\CsvApiException;
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
                $response = $this->makeRequest('GET', '/api/');
                return $response;
            });
        } else {
            return $this->makeRequest('GET', '/api/');
        }
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
        $cacheKey = 'csv_api_data_' . $file . '_' . md5(json_encode($params));

        if (app()->bound('cache')) {
            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($file, $params) {
                $response = $this->makeRequest('GET', '/api/' . $file, $params);
                return $response;
            });
        } else {
            return $this->makeRequest('GET', '/api/' . $file, $params);
        }
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
        $cacheKey = 'csv_api_schema_' . $file;

        if (app()->bound('cache')) {
            return Cache::remember($cacheKey, $this->cacheTtl * 10, function () use ($file) {
                $response = $this->makeRequest('GET', '/api/' . $file . '/schema');
                return $response;
            });
        } else {
            return $this->makeRequest('GET', '/api/' . $file . '/schema');
        }
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
            // Ne pas modifier l'endpoint, utilisez-le tel quel
            $url = rtrim($this->baseUrl, '/') . $endpoint;

            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(30)
                ->retry(3, 1000)
                ->$method($url, $params);

            // Débogage de la réponse, à supprimer en production
            if (config('csv-eloquent.debug', false)) {
                Log::debug('CSV API Response', [
                    'url' => $url,
                    'status' => $response->status(),
                    'data' => $response->json()
                ]);
            }

            if ($response->failed()) {
                if (app()->bound('log')) {
                    Log::error('Échec de la requête API CSV', [
                        'url' => $url,
                        'status' => $response->status(),
                        'response' => $response->json() ?? $response->body(),
                    ]);
                } else {
                    error_log('Échec de la requête API CSV: ' . $response->status() . ' pour ' . $url);
                }

                throw new CsvApiException(
                    'Échec de la requête API CSV: ' . $response->status(),
                    $response->status()
                );
            }

            return $response->json();
        } catch (\Exception $e) {
            if (!$e instanceof CsvApiException) {
                if (app()->bound('log')) {
                    Log::error('Exception lors de la requête API CSV', [
                        'exception' => $e->getMessage(),
                        'endpoint' => $endpoint,
                    ]);
                } else {
                    error_log('Exception lors de la requête API CSV: ' . $e->getMessage() . ' pour ' . $endpoint);
                }

                throw new CsvApiException(
                    'Exception lors de la requête API CSV: ' . $e->getMessage(),
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
     * @param string $file
     *
     * @return void
     */
    public function clearCache($file = null)
    {
        if (!app()->bound('cache')) {
            return;
        }

        if ($file) {
            Cache::forget('csv_api_schema_' . $file);
            // Pour les clés avec pattern, on ne peut pas utiliser Cache::forget directement
            // Il faudrait implémenter une méthode plus complexe pour effacer par pattern
            Cache::flush(); // Alternative plus drastique mais fonctionnelle
        } else {
            // Vide tous les caches liés à l'API CSV
            $keys = ['csv_api_files'];
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }
}
