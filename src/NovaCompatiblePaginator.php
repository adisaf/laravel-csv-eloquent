<?php

namespace Adisaf\CsvEloquent;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Classe de pagination personnalisée pour Nova
 * Étend LengthAwarePaginator pour assurer la compatibilité avec Nova
 */
class NovaCompatiblePaginator extends LengthAwarePaginator
{
    /**
     * Convertit le paginateur en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Assurez-vous que total est bien un nombre entier
        $array['total'] = (int) $array['total'];

        // S'assurer que les propriétés essentielles existent
        if (! isset($array['per_page'])) {
            $array['per_page'] = (int) $this->perPage();
        }

        if (! isset($array['current_page'])) {
            $array['current_page'] = $this->currentPage();
        }

        // Ajouter total_pages si ce n'est pas déjà fait
        if (! isset($array['total_pages']) && isset($array['last_page'])) {
            $array['total_pages'] = $array['last_page'];
        }

        return $array;
    }
}
