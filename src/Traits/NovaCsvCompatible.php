<?php

namespace Adisaf\CsvEloquent\Traits;

use Illuminate\Support\Facades\Log;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\TrashedStatus;

trait NovaCsvCompatible
{
    /**
     * Construit une requête pour les index resource.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query;
    }

    /**
     * Récupère les ressources pour une page particulière
     * Cette méthode remplace complètement celle de Nova
     *
     * @param \Illuminate\Database\Eloquent\Builder|null $query
     *
     * @return array
     */
    public static function buildIndexQuery(NovaRequest $request, $query = null)
    {
        $model = static::newModel();

        // Créer la requête de base
        $query = $query ?: $model->newQuery();

        // Activer le débogage pour ce module
        $debug = config('csv-eloquent.debug', false);
        if ($debug) {
            Log::debug('NovaCsvCompatible::buildIndexQuery - Début', [
                'model' => get_class($model),
                'request_params' => $request->only(['page', 'perPage', 'orderBy', 'orderByDirection', 'trashed']),
            ]);
        }

        // Appliquer toute logique additionnelle définie dans la ressource
        static::indexQuery($request, $query);

        // Force l'ordre par ID par défaut si aucun autre tri n'est spécifié
        if (empty($request->get('orderBy'))) {
            $query->orderBy($model->getKeyName(), 'desc');
        } else {
            $query->orderBy(
                $request->get('orderBy'),
                $request->get('orderByDirection', 'asc')
            );
        }

        // Gérer les soft deletes si nécessaire
        $trashedStatus = $request->input('trashed');
        if ($trashedStatus === TrashedStatus::WITH) {
            $query->withTrashed();
        } elseif ($trashedStatus === TrashedStatus::ONLY) {
            $query->onlyTrashed();
        }

        // Obtenir la taille de page
        $perPage = (int)($request->perPage ?: $model->getPerPage());
        $page = (int)$request->input('page', 1);

        // Exécuter la pagination avec capture d'exception
        try {
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            if ($debug) {
                Log::debug('Paginator créé', [
                    'class' => get_class($paginator),
                    'total_type' => gettype($paginator->total()),
                    'total_value' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                ]);
            }

            // IMPORTANT: Force explicitement le total comme un entier
            $forcedTotal = (int)$paginator->total();

            // Préparer le tableau de réponse avec les valeurs forcées en entier
            $response = [
                'resources' => $paginator,
                'total' => $forcedTotal,  // <- Cette valeur est utilisée par Nova pour l'affichage
                'count' => $forcedTotal,  // <- Certaines versions de Nova utilisent 'count'
            ];

            // Vérifier la structure avant de retourner
            if ($debug) {
                if (method_exists($paginator, 'toArray')) {
                    $paginatorArray = $paginator->toArray();
                    Log::debug('Structure du paginateur', [
                        'has_total' => isset($paginatorArray['total']),
                        'total_type' => isset($paginatorArray['total']) ? gettype($paginatorArray['total']) : 'non défini',
                        'total_value' => $paginatorArray['total'] ?? 'N/A',
                        'response_structure' => array_keys($response),
                        'response_total_type' => gettype($response['total']),
                    ]);
                }
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Erreur lors de la pagination dans NovaCsvCompatible', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Créer une réponse de secours en cas d'erreur
            return [
                'resources' => [],
                'total' => 0,
                'count' => 0,
            ];
        }
    }

    /**
     * Formate une ressource pour éviter les problèmes de sérialisation.
     * Cette méthode est appelée par Nova lors de la préparation de la réponse JSON.
     *
     * @return array
     */
    public static function formatIndexResponse(NovaRequest $request, array $response)
    {
        // S'assurer que 'total' existe et est un entier au niveau racine
        if (!isset($response['total']) || !is_int($response['total'])) {
            $total = 0; // Valeur par défaut sécuritaire

            if (isset($response['resources']) && method_exists($response['resources'], 'total')) {
                $total = (int)$response['resources']->total();
            } elseif (isset($response['resources']['total'])) {
                $total = (int)$response['resources']['total'];
            } elseif (isset($response['resources']['data'])) {
                $total = count($response['resources']['data']);
            }

            $response['total'] = $total;
        } else {
            // S'assurer que c'est bien un entier même si déjà défini
            $response['total'] = (int)$response['total'];
        }

        // Ajouter 'count' si absent (certaines versions de Nova l'utilisent)
        if (!isset($response['count']) && isset($response['total'])) {
            $response['count'] = $response['total'];
        }

        // S'assurer que les champs de pagination dans 'resources' sont des entiers
        if (isset($response['resources']) && is_array($response['resources'])) {
            foreach (['total', 'per_page', 'current_page', 'last_page', 'from', 'to'] as $field) {
                if (isset($response['resources'][$field])) {
                    $response['resources'][$field] = (int)$response['resources'][$field];
                }
            }
        }

        // Appeler parent si existe
        if (method_exists(get_parent_class(), 'formatIndexResponse')) {
            return parent::formatIndexResponse($request, $response);
        }

        return $response;
    }
}
