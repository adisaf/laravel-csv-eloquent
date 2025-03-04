<?php

namespace Paymetrust\CsvEloquent\Exceptions;

use Exception;

class CsvApiException extends Exception
{
    /**
     * Les données de réponse associées à l'exception.
     *
     * @var array|null
     */
    protected $responseData;

    /**
     * Crée une nouvelle instance d'exception.
     *
     * @param string $message
     * @param int $code
     *
     * @return void
     */
    public function __construct($message = '', $code = 0, ?\Throwable $previous = null, ?array $responseData = null)
    {
        parent::__construct($message, $code, $previous);

        $this->responseData = $responseData;
    }

    /**
     * Obtient les données de réponse associées à l'exception.
     *
     * @return array|null
     */
    public function getResponseData()
    {
        return $this->responseData;
    }
}
