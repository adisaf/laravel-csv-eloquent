<?php

namespace Adisaf\CsvEloquent\Traits;

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
     * @return array
     */
    public static function buildIndexQuery(NovaRequest $request, $query = null)
    {
        $model = static::newModel();

        // Créer la requête de base
        $query = $query ?: $model->newQuery();

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
        $perPage = $request->perPage ?: $model->getPerPage();
        $page = $request->input('page', 1);

        // Exécuter la pagination
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Important: Forcer le total comme un entier
        if (is_object($paginator) && property_exists($paginator, 'total')) {
            $paginator->total = (int) $paginator->total;
        }

        // Nova analyse également le JSON résultant
        if (method_exists($paginator, 'toArray')) {
            $paginatorArray = $paginator->toArray();
            if (isset($paginatorArray['total'])) {
                $paginatorArray['total'] = (int) $paginatorArray['total'];
                // Méthode hack pour réinjecter cette valeur
                $paginator->setCollection(
                    $paginator->getCollection()->map(function ($item) use ($paginatorArray) {
                        $item->__paginationTotal = $paginatorArray['total'];

                        return $item;
                    })
                );
            }
        }

        return [
            'resources' => $paginator,
            'total' => (int) ($paginator->total ?? count($paginator->items())),
        ];
    }
}
