<?php

namespace Adisaf\CsvEloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CsvCollection extends Collection
{
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
     * Détermine si la collection est vide ou non.
     *
     * @return bool
     */
    public function isEmpty()
    {
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
        $count = parent::count();
        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('CsvCollection::count appelé, résultat: ' . $count);
        }

        // Affiche quelques infos sur les items si count > 0
        if ($count > 0) {
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
}
