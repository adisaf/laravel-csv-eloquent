<?php

namespace Adisaf\CsvEloquent\Paginator;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class FixedPaginator extends LengthAwarePaginator
{
    /**
     * Crée une nouvelle instance de paginateur.
     *
     * @param mixed $items
     * @param int $total
     * @param int $perPage
     * @param int|null $currentPage
     *
     * @return void
     */
    public function __construct($items, $total, $perPage, $currentPage, array $options = [])
    {
        // Forcer le total à être un entier AVANT d'appeler le constructeur parent
        $total = is_numeric($total) ? (int) $total : count($items);

        if (config('csv-eloquent.debug', false)) {
            Log::debug("FixedPaginator::__construct - Total forcé à: {$total}");
        }

        parent::__construct($items, $total, $perPage, $currentPage, $options);
    }

    /**
     * Convertit le paginateur en tableau.
     * Cette méthode corrige les problèmes de sérialisation JSON.
     *
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Forcer les valeurs numériques à être des entiers
        $numericFields = ['total', 'per_page', 'current_page', 'last_page', 'from', 'to'];

        foreach ($numericFields as $field) {
            if (isset($array[$field])) {
                // Si c'est un objet ou non numérique, remplacer par une valeur appropriée
                if (is_object($array[$field]) || ! is_numeric($array[$field])) {
                    if ($field === 'total') {
                        $array[$field] = isset($array['data']) ? count($array['data']) : 0;
                    } elseif ($field === 'per_page') {
                        $array[$field] = $this->perPage();
                    } elseif ($field === 'current_page') {
                        $array[$field] = $this->currentPage();
                    } elseif ($field === 'last_page') {
                        $array[$field] = max(1, ceil(($array['total'] ?? 0) / ($array['per_page'] ?? 1)));
                    }
                }

                // Forcer la conversion en entier
                $array[$field] = (int) $array[$field];
            }
        }

        // Log pour débogage
        if (config('csv-eloquent.debug', false)) {
            Log::debug('FixedPaginator::toArray', [
                'total_type' => isset($array['total']) ? gettype($array['total']) : 'non défini',
                'total_value' => $array['total'] ?? 'N/A',
            ]);
        }

        return $array;
    }

    /**
     * Méthode qui retourne le total.
     * Cette méthode s'assure que le total est toujours un entier.
     *
     * @return int
     */
    public function total()
    {
        $total = parent::total();

        // Si le total est un objet ou non numérique, utiliser une valeur par défaut
        if (is_object($total) || ! is_numeric($total)) {
            if (config('csv-eloquent.debug', false)) {
                Log::debug('FixedPaginator::total - Total est un objet ou non numérique', [
                    'total_type' => gettype($total),
                ]);
            }

            return count($this->items());
        }

        // Forcer la conversion en entier
        return (int) $total;
    }

    /**
     * Convertit l'objet en données JSON sérialisables.
     */
    public function jsonSerialize(): array
    {
        $data = $this->toArray();

        // Double vérification du type de total
        if (isset($data['total'])) {
            $data['total'] = (int) $data['total'];
        }

        return $data;
    }
}
