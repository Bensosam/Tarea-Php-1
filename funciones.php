<?php

declare(strict_types=1);

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function obtener_conexion(): mysqli
{
    require_once __DIR__ . "/datos_conexion.php";

    $conexion = mysqli_connect($db_host, $db_usr, $db_pass, $db_nombre);

    if (!$conexion) {
        die("Error de conexión: " . mysqli_connect_error());
    }

    $conexion->set_charset("utf8mb4");

    return $conexion;
}

/*
 * CSRF
 */
function generar_token_csrf(): string
{
    iniciar_sesion();

    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function validar_token_csrf(?string $token): bool
{
    iniciar_sesion();

    if (empty($_SESSION["csrf_token"]) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION["csrf_token"], $token);
}

/*
 * Mensajes flash
 */
function establecer_mensaje(string $tipo, string $texto): void
{
    iniciar_sesion();

    $_SESSION["mensaje"] = [
        "tipo" => $tipo,
        "texto" => $texto,
    ];
}

function obtener_mensaje(): ?array
{
    iniciar_sesion();

    if (empty($_SESSION["mensaje"])) {
        return null;
    }

    $mensaje = $_SESSION["mensaje"];
    unset($_SESSION["mensaje"]);

    return $mensaje;
}

function escapar(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}
