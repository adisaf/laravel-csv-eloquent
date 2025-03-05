<?php

namespace Adisaf\CsvEloquent\Paginator;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class FixedPaginator extends LengthAwarePaginator
{
    public function __construct($items, $total, $perPage, $currentPage, array $options = [])
    {
        // Forcer le total en entier avant de l'utiliser
        $total = is_numeric($total) ? (int) $total : count($items);

        Log::info('FixedPaginator::__construct', [
            'total' => $total,
            'type' => gettype($total),
            'perPage' => $perPage,
            'currentPage' => $currentPage,
        ]);

        parent::__construct($items, $total, $perPage, $currentPage, $options);
    }

    public function toArray()
    {
        $array = parent::toArray();

        // Forcer tous les champs numériques en entiers
        foreach (['total', 'per_page', 'current_page', 'last_page', 'from', 'to'] as $field) {
            if (isset($array[$field])) {
                $array[$field] = (int) $array[$field];
            }
        }

        Log::info('FixedPaginator::toArray', [
            'total' => $array['total'] ?? 'non défini',
            'type' => isset($array['total']) ? gettype($array['total']) : 'non défini',
        ]);

        return $array;
    }

    public function total()
    {
        $total = parent::total();

        // Forcer le retour en entier
        $total = is_numeric($total) ? (int) $total : count($this->items());

        Log::info('FixedPaginator::total', [
            'valeur' => $total,
            'type' => gettype($total),
        ]);

        return $total;
    }

    public function jsonSerialize(): array
    {
        $data = $this->toArray();

        // Vérification supplémentaire pour total
        if (isset($data['total'])) {
            $data['total'] = (int) $data['total'];
        }

        return $data;
    }
}
