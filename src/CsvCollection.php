<?php

namespace Adisaf\CsvEloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CsvCollection extends Collection
{
    /**
     * Les métadonnées associées à la collection, contenant notamment les informations de pagination.
     *
     * @var array|null
     */
    protected $meta = null;

    /**
     * Crée une nouvelle collection.
     *
     * @param mixed $items
     *
     * @return void
     */
    public function __construct($items = [])
    {
        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('CsvCollection::__construct - Création avec ' . count($items) . " éléments\n");
        }

        // Vérifier les items avant de les passer à parent
        if (!empty($items)) {
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                if (is_object($items[0])) {
                    Log::debug('Premier item de type: ' . get_class($items[0]));
                } else {
                    Log::debug('Premier item de type: ' . gettype($items[0]));
                }
            }
        }

        parent::__construct($items);

        // Vérifier après construction
        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('CsvCollection après construction, count(): ' . $this->count());
        }
    }

    /**
     * Définit les métadonnées de la collection.
     *
     * @param array $meta
     * @return $this
     */
    public function setMeta(array $meta)
    {
        $this->meta = $meta;
        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('CsvCollection::setMeta - Métadonnées définies', [
                'meta' => $meta,
                'pagination' => $meta['pagination'] ?? 'non disponible'
            ]);
        }
        return $this;
    }

    /**
     * Récupère les métadonnées de la collection.
     *
     * @return array|null
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * Détermine si la collection est vide ou non.
     *
     * @return bool
     */
    public function isEmpty()
    {
        // Vérifie d'abord si nous avons des éléments dans la collection
        $result = parent::isEmpty();

        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('CsvCollection::isEmpty appelé, résultat: ' . ($result ? 'true' : 'false') . ', count(): ' . $this->count());
        }

        return $result;
    }

    /**
     * Récupère le premier élément de la collection.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function first(?callable $callback = null, $default = null)
    {
        $result = parent::first($callback, $default);
        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('CsvCollection::first appelé, résultat: ' . ($result === null ? 'null' : 'objet'));
        }

        return $result;
    }

    /**
     * Compte les éléments de la collection.
     */
    public function count(): int
    {
        // Vérifier si des métadonnées de pagination sont disponibles
        if (isset($this->meta) && isset($this->meta['pagination']) && isset($this->meta['pagination']['total'])) {
            $count = (int)$this->meta['pagination']['total'];

            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug('CsvCollection::count appelé, utilisant meta.pagination.total: ' . $count);
            }
        } else {
            // Fallback au comportement d'origine si aucune métadonnée n'est disponible
            $count = parent::count();

            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug('CsvCollection::count appelé, résultat depuis items: ' . $count);
            }
        }

        // Affiche quelques infos sur les items si count > 0
        if ($count > 0 && !empty($this->items)) {
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug("Items dans la collection: \n");
            }
            $i = 0;
            foreach ($this->items as $item) {
                if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                    if (is_object($item)) {
                        Log::debug("- Item #$i: " . get_class($item));
                    } else {
                        Log::debug("- Item #$i: " . gettype($item));
                    }
                }
                $i++;
                if ($i >= 2) {
                    break;
                } // Limiter à 2 items pour la clarté
            }
        }

        return $count;
    }

    /**
     * Total des éléments dans la source de données
     */
    public function total(): int
    {
        if (isset($this->meta) && isset($this->meta['pagination']) && isset($this->meta['pagination']['total'])) {
            return (int)$this->meta['pagination']['total'];
        }

        return parent::count();
    }
}
