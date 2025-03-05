<?php

namespace Adisaf\CsvEloquent\Helpers;

class Formatter
{
    public static function formatDateTime(&$array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                self::formatDateTime($value); // Appel récursif si c'est un tableau
            } elseif (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                // Ajout du format souhaité (sans modifier la valeur existante)
                $value = "{$value}[.000000][+00:00]";
            }
        }
    }

    public static function transformBetween(&$array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                if (isset($value['$between']) && is_array($value['$between']) && count($value['$between']) === 2) {
                    $value['$between'] = implode(',', $value['$between']);
                }
                self::transformBetween($value);
            }
        }
    }
}
