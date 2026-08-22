<?php

namespace App\Core;

use App\Support\ErrorPresenter;

function exception_handler(\Throwable $exception): void
{
    error_reporting(0);
    ini_set('display_errors', 0);

    $statusCode = ErrorPresenter::httpStatus($exception, 500);

    // El detalle completo va SIEMPRE al log, se muestre o no al cliente.
    ErrorPresenter::logException($exception, 'exception_handler');

    // 4xx lanzado por la app -> mensaje real (la UX del proyecto depende de el).
    // 5xx / sin codigo / clase inesperada -> generico. Ver ErrorPresenter.
    $response = [
        'success' => false,
        'error' => ErrorPresenter::publicMessage($exception, 'exception_handler')
    ];

    if (ErrorPresenter::isDevEnvironment()) {
        $response['trace'] = explode("\n", $exception->getTraceAsString());
        $response['file'] = $exception->getFile() . ':' . $exception->getLine();
    }

    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        // Antes este fallback devolvia `raw_error` con el mensaje crudo, lo que
        // filtraba exactamente lo que el resto del handler ya estaba ocultando.
        error_log(
            'exception_handler: json_encode fallo (' . json_last_error_msg() . '). ' .
            ErrorPresenter::describe($exception, 'exception_handler')
        );
        echo '{"success":false,"error":"Ocurrio un error en el servidor. Revise los logs."}';
    } else {
        echo $json;
    }

    exit();
}
