<?php

namespace Adisaf\CsvEloquent\Traits;

use Laravel\Nova\Http\Requests\NovaRequest;

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
     * Construit une requête "count" pour les index resource.
     *
     * Cette méthode est cruciale pour afficher correctement le total dans Nova.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexCountQuery(NovaRequest $request, $query)
    {
        // Pour les modèles CSV, nous ne modifions pas la requête
        // car le total est déjà géré par la méthode paginate() du Builder CSV
        return $query;
    }

    /**
     * Construit une requête pour les relations de type "related resource".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function relatableQuery(NovaRequest $request, $query)
    {
        return $query;
    }

    /**
     * Récupère les ressources pour une page particulière.
     *
     * @return array
     */
    public static function buildIndexQuery(NovaRequest $request, $query = null)
    {
        $query = $query ?: static::newModel()->newQuery();

        // Force l'ordre par ID par défaut si aucun autre tri n'est spécifié
        if (empty($request->get('orderBy'))) {
            $query->orderBy(static::newModel()->getKeyName(), 'desc');
        }

        // Obtenez la taille par page à partir de la requête ou utilisez la valeur par défaut
        $perPage = $request->perPage ?: static::newModel()->getPerPage();

        // Utilisez la page passée dans la requête
        $page = $request->get('page', 1);

        // Utilisez le paginateur existant de notre Builder CSV
        return [
            'resources' => $query->paginate($perPage, ['*'], 'page', $page),
        ];
    }
}
