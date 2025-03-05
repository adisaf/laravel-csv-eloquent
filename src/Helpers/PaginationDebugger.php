<?php

namespace Adisaf\CsvEloquent\Helpers;

class PaginationDebugger
{
    /**
     * Écrit les informations de pagination dans un fichier de log
     *
     * @param mixed $paginator Le paginateur à analyser
     * @param string $context Information contextuelle
     * @return void
     */
    public static function inspect($paginator, $context = 'default')
    {
        // Assurez-vous que le dossier logs existe
        $logsDir = storage_path('logs');
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        $logFile = $logsDir . '/pagination-debug.log';

        // Information à enregistrer
        $info = [
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => $context,
            'class' => get_class($paginator),
            'properties' => [],
            'methods' => [],
            'toArray' => null,
            'jsonSerialize' => null,
        ];

        // Récupérer toutes les propriétés publiques
        foreach (get_object_vars($paginator) as $key => $value) {
            $info['properties'][$key] = [
                'type' => gettype($value),
                'value' => is_scalar($value) ? $value : (is_null($value) ? 'NULL' : '[' . gettype($value) . ']'),
            ];
        }

        // Vérifier les méthodes importantes
        $methodsToCheck = ['total', 'count', 'getTotal', 'perPage', 'currentPage', 'lastPage'];
        foreach ($methodsToCheck as $method) {
            if (method_exists($paginator, $method)) {
                try {
                    $result = $paginator->$method();
                    $info['methods'][$method] = [
                        'type' => gettype($result),
                        'value' => is_scalar($result) ? $result : '[' . gettype($result) . ']',
                    ];
                } catch (\Exception $e) {
                    $info['methods'][$method] = [
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        // Examiner les méthodes de sérialisation
        if (method_exists($paginator, 'toArray')) {
            try {
                $array = $paginator->toArray();
                $info['toArray'] = array_map(function ($item) {
                    return is_scalar($item) ? $item : '[' . gettype($item) . ']';
                }, $array);
            } catch (\Exception $e) {
                $info['toArray'] = ['error' => $e->getMessage()];
            }
        }

        if (method_exists($paginator, 'jsonSerialize')) {
            try {
                $json = $paginator->jsonSerialize();
                $info['jsonSerialize'] = is_array($json) ?
                    array_map(function ($item) {
                        return is_scalar($item) ? $item : '[' . gettype($item) . ']';
                    }, $json) :
                    ['not_array' => gettype($json)];
            } catch (\Exception $e) {
                $info['jsonSerialize'] = ['error' => $e->getMessage()];
            }
        }

        // Écrire dans le fichier de log
        file_put_contents(
            $logFile,
            json_encode($info, JSON_PRETTY_PRINT) . "\n\n",
            FILE_APPEND
        );

        return $paginator;
    }
}
