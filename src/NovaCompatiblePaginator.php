<?php

namespace Adisaf\CsvEloquent;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Classe de pagination personnalisée pour Nova
 * Étend LengthAwarePaginator pour assurer la compatibilité avec Nova
 */
class NovaCompatiblePaginator extends LengthAwarePaginator
{
    /**
     * Crée une nouvelle instance de NovaCompatiblePaginator.
     *
     * @param mixed $items
     * @param int $total
     * @param int $perPage
     * @param int|null $currentPage
     * @param array $options
     */
    public function __construct($items, $total, $perPage, $currentPage = null, array $options = [])
    {
        // Force la conversion en entier
        $total = is_numeric($total) ? (int)$total : 0;

        if (config('csv-eloquent.debug', false)) {
            Log::info('NovaCompatiblePaginator::__construct', [
                'total' => $total,
                'type' => gettype($total),
                'perPage' => $perPage,
                'currentPage' => $currentPage,
            ]);
        }

        parent::__construct($items, $total, $perPage, $currentPage, $options);
    }

    /**
     * Obtient le nombre total d'éléments.
     *
     * @return int
     */
    public function total()
    {
        // Force le retour en entier
        $total = parent::total();
        $total = is_numeric($total) ? (int)$total : 0;

        if (config('csv-eloquent.debug', false)) {
            Log::info('NovaCompatiblePaginator::total', [
                'valeur' => $total,
                'type' => gettype($total),
            ]);
        }

        return $total;
    }

    /**
     * Convertit le paginateur en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Forcer tous les champs numériques en entiers
        foreach (['total', 'per_page', 'current_page', 'last_page', 'from', 'to'] as $field) {
            if (isset($array[$field])) {
                $array[$field] = (int)$array[$field];
            }
        }

        // S'assurer que total est un entier
        $array['total'] = (int)$this->total();

        // Ajouter la propriété count que Nova utilise parfois
        if (!isset($array['count']) && isset($array['total'])) {
            $array['count'] = $array['total'];
        }

        if (config('csv-eloquent.debug', false)) {
            Log::info('NovaCompatiblePaginator::toArray', [
                'total' => $array['total'] ?? 'non défini',
                'type' => isset($array['total']) ? gettype($array['total']) : 'non défini',
            ]);
        }

        return $array;
    }

    /**
     * Convertit l'objet en quelque chose de sérialisable en JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        $data = $this->toArray();

        // Vérification supplémentaire pour s'assurer que total est un entier
        if (isset($data['total'])) {
            $data['total'] = (int)$data['total'];
        }

        // Ajouter la propriété totalRecords que Nova pourrait chercher
        if (!isset($data['totalRecords']) && isset($data['total'])) {
            $data['totalRecords'] = $data['total'];
        }

        return $data;
    }
}
