<?php

namespace App\Core;

function exception_handler(\Throwable $exception): void
{
    error_reporting(0);
    ini_set('display_errors', 0);

    $statusCode = method_exists($exception, 'getCode') ? $exception->getCode() : 500;
    if ($statusCode < 400 || $statusCode >= 600) {
        $statusCode = 500; 
    }

    $response = [
        'success' => false,
        'error' => $exception->getMessage()
    ];

    if (defined('IS_DEV_ENVIRONMENT') && IS_DEV_ENVIRONMENT) {
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
        $safeResponse = [
            'success' => false,
            'error' => 'Ocurrio un error en el servidor (Fallo de codificacion JSON). Revise los logs.',
            'raw_error' => utf8_encode($exception->getMessage())
        ];
        echo json_encode($safeResponse);
    } else {
        echo $json;
    }
    error_log(
        "Excepcion API ($statusCode): " . $exception->getMessage() .
        " en " . $exception->getFile() .
        " linea " . $exception->getLine()
    );

    exit();
}