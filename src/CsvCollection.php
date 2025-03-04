<?php

namespace Adisaf\CsvEloquent;

use Illuminate\Support\Collection;

class CsvCollection extends Collection
{
    /**
     * Crée une nouvelle collection.
     *
     * @param mixed $items
     * @return void
     */
    public function __construct($items = [])
    {
        echo "CsvCollection::__construct - Création avec " . count($items) . " éléments\n";

        // Vérifier les items avant de les passer à parent
        if (!empty($items)) {
            echo "Premier item de type: " . get_class($items[0]) . "\n";
        }

        parent::__construct($items);

        // Vérifier après construction
        echo "CsvCollection après construction, count(): " . $this->count() . "\n";
    }

    /**
     * Détermine si la collection est vide ou non.
     *
     * @return bool
     */
    public function isEmpty()
    {
        $result = parent::isEmpty();
        echo "CsvCollection::isEmpty appelé, résultat: " . ($result ? 'true' : 'false') . ", count(): " . $this->count() . "\n";
        return $result;
    }

    /**
     * Récupère le premier élément de la collection.
     *
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        $result = parent::first($callback, $default);
        echo "CsvCollection::first appelé, résultat: " . ($result === null ? 'null' : 'objet') . "\n";
        return $result;
    }

    /**
     * Compte les éléments de la collection.
     *
     * @return int
     */
    public function count(): int
    {
        $count = parent::count();
        echo "CsvCollection::count appelé, résultat: " . $count . "\n";

        // Affiche quelques infos sur les items si count > 0
        if ($count > 0) {
            echo "Items dans la collection: \n";
            $i = 0;
            foreach ($this->items as $item) {
                echo "- Item #$i: " . get_class($item) . "\n";
                $i++;
                if ($i >= 2) break; // Limiter à 2 items pour la clarté
            }
        }

        return $count;
    }
}
