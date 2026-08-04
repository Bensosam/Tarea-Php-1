<?php

declare(strict_types=1);

require_once __DIR__ . "/funciones.php";

iniciar_sesion();

if (empty($_POST)) {
    header("Location: index.php");
    exit;
}

$pagina_actual = (int) ($_POST["pagina_actual"] ?? 1);
if ($pagina_actual < 1) {
    $pagina_actual = 1;
}
$buscar_actual = trim((string) ($_POST["buscar_actual"] ?? ""));

function redireccionar(int $pagina, string $buscar): never
{
    $parametros = ["pagina" => $pagina];
    if ($buscar !== "") {
        $parametros["buscar"] = $buscar;
    }

    header("Location: index.php?" . http_build_query($parametros));
    exit;
}

if (!validar_token_csrf($_POST["csrf_token"] ?? null)) {
    establecer_mensaje("danger", "La solicitud no es válida o expiró. Intente nuevamente.");
    redireccionar($pagina_actual, $buscar_actual);
}

$accion = null;
if (isset($_POST["btn_agregar"])) {
    $accion = "agregar";
} elseif (isset($_POST["btn_modificar"])) {
    $accion = "modificar";
} elseif (isset($_POST["btn_eliminar"])) {
    $accion = "eliminar";
}

if ($accion === null) {
    establecer_mensaje("danger", "No se pudo completar la operación.");
    redireccionar($pagina_actual, $buscar_actual);
}

$txt_id = (int) ($_POST["txt_id"] ?? 0);
$txt_codigo = trim((string) ($_POST["txt_codigo"] ?? ""));
$txt_nombres = trim((string) ($_POST["txt_nombres"] ?? ""));
$txt_apellidos = trim((string) ($_POST["txt_apellidos"] ?? ""));
$txt_direccion = trim((string) ($_POST["txt_direccion"] ?? ""));
$txt_telefono = trim((string) ($_POST["txt_telefono"] ?? ""));
$txt_fn = trim((string) ($_POST["txt_fn"] ?? ""));
$drop_puesto = (int) ($_POST["drop_puesto"] ?? 0);

$conexion = obtener_conexion();

/*
 * ELIMINAR EMPLEADO
 */
if ($accion === "eliminar") {
    if ($txt_id <= 0) {
        establecer_mensaje("danger", "El identificador del empleado no es válido.");
        $conexion->close();
        redireccionar($pagina_actual, $buscar_actual);
    }

    $consulta = $conexion->prepare("SELECT id_empleado FROM empleados WHERE id_empleado = ?");
    $consulta->bind_param("i", $txt_id);
    $consulta->execute();
    $existe = $consulta->get_result()->fetch_assoc();
    $consulta->close();

    if (!$existe) {
        establecer_mensaje("danger", "El empleado que intenta eliminar no existe.");
        $conexion->close();
        redireccionar($pagina_actual, $buscar_actual);
    }

    try {
        $eliminar = $conexion->prepare("DELETE FROM empleados WHERE id_empleado = ?");
        $eliminar->bind_param("i", $txt_id);
        $eliminar->execute();
        $eliminar->close();

        establecer_mensaje("success", "Empleado eliminado correctamente.");
    } catch (mysqli_sql_exception $error) {
        establecer_mensaje("danger", "No se pudo completar la operación.");
    }

    $conexion->close();
    redireccionar($pagina_actual, $buscar_actual);
}

/*
 * VALIDACIONES PARA AGREGAR / MODIFICAR
 */
$errores = [];

if ($txt_codigo === "") {
    $errores[] = "El código del empleado no puede estar vacío.";
} elseif (!preg_match('/^[A-Za-z0-9\-]{2,10}$/', $txt_codigo)) {
    $errores[] = "El código debe tener de 2 a 10 caracteres (letras, números o guiones).";
}

if ($txt_nombres === "") {
    $errores[] = "Los nombres no pueden estar vacíos.";
} elseif (!preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\'\-\s]{2,60}$/u', $txt_nombres)) {
    $errores[] = "Los nombres contienen caracteres no permitidos.";
}

if ($txt_apellidos === "") {
    $errores[] = "Los apellidos no pueden estar vacíos.";
} elseif (!preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\'\-\s]{2,60}$/u', $txt_apellidos)) {
    $errores[] = "Los apellidos contienen caracteres no permitidos.";
}

if ($txt_direccion !== "" && mb_strlen($txt_direccion) > 100) {
    $errores[] = "La dirección no puede superar los 100 caracteres.";
}

if ($txt_telefono !== "" && !preg_match('/^[0-9]{8}$/', $txt_telefono)) {
    $errores[] = "El teléfono debe tener 8 dígitos numéricos.";
}

$fecha_nacimiento = DateTime::createFromFormat("Y-m-d", $txt_fn);
$errores_fecha = DateTime::getLastErrors();

if (
    $txt_fn === ""
    || $fecha_nacimiento === false
    || ($errores_fecha !== false && ($errores_fecha["warning_count"] > 0 || $errores_fecha["error_count"] > 0))
) {
    $errores[] = "La fecha de nacimiento no es válida.";
} elseif ($fecha_nacimiento > new DateTime("today")) {
    $errores[] = "La fecha de nacimiento no puede ser una fecha futura.";
}

if ($drop_puesto <= 0) {
    $errores[] = "Debe seleccionar un puesto.";
} else {
    $consulta_puesto = $conexion->prepare("SELECT id_puesto FROM puestos WHERE id_puesto = ?");
    $consulta_puesto->bind_param("i", $drop_puesto);
    $consulta_puesto->execute();
    $puesto_valido = $consulta_puesto->get_result()->fetch_assoc();
    $consulta_puesto->close();

    if (!$puesto_valido) {
        $errores[] = "El puesto seleccionado no existe.";
    }
}

if ($accion === "modificar") {
    if ($txt_id <= 0) {
        $errores[] = "El identificador del empleado no es válido.";
    } else {
        $consulta_id = $conexion->prepare("SELECT id_empleado FROM empleados WHERE id_empleado = ?");
        $consulta_id->bind_param("i", $txt_id);
        $consulta_id->execute();
        $empleado_existe = $consulta_id->get_result()->fetch_assoc();
        $consulta_id->close();

        if (!$empleado_existe) {
            $errores[] = "El empleado que intenta modificar no existe.";
        }
    }
}

if (empty($errores) && $txt_codigo !== "") {
    if ($accion === "modificar") {
        $consulta_codigo = $conexion->prepare(
            "SELECT id_empleado FROM empleados WHERE codigo = ? AND id_empleado <> ?"
        );
        $consulta_codigo->bind_param("si", $txt_codigo, $txt_id);
    } else {
        $consulta_codigo = $conexion->prepare(
            "SELECT id_empleado FROM empleados WHERE codigo = ?"
        );
        $consulta_codigo->bind_param("s", $txt_codigo);
    }

    $consulta_codigo->execute();
    $codigo_repetido = $consulta_codigo->get_result()->fetch_assoc();
    $consulta_codigo->close();

    if ($codigo_repetido) {
        $errores[] = "El código ingresado ya existe.";
    }
}

if (!empty($errores)) {
    establecer_mensaje("danger", implode(" ", $errores));
    $conexion->close();
    redireccionar($pagina_actual, $buscar_actual);
}

$fecha_nacimiento_sql = $fecha_nacimiento->format("Y-m-d");

try {
    if ($accion === "modificar") {
        $sql = "UPDATE empleados
                SET codigo = ?,
                    nombres = ?,
                    apellidos = ?,
                    direccion = ?,
                    telefono = ?,
                    fecha_nacimiento = ?,
                    id_puesto = ?,
                    updated_at = NOW()
                WHERE id_empleado = ?";

        $sentencia = $conexion->prepare($sql);
        $sentencia->bind_param(
            "ssssssii",
            $txt_codigo,
            $txt_nombres,
            $txt_apellidos,
            $txt_direccion,
            $txt_telefono,
            $fecha_nacimiento_sql,
            $drop_puesto,
            $txt_id
        );
        $sentencia->execute();
        $sentencia->close();

        establecer_mensaje("success", "Empleado modificado correctamente.");
    } else {
        $sql = "INSERT INTO empleados
                (
                    codigo,
                    nombres,
                    apellidos,
                    direccion,
                    telefono,
                    fecha_nacimiento,
                    id_puesto,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $sentencia = $conexion->prepare($sql);
        $sentencia->bind_param(
            "ssssssi",
            $txt_codigo,
            $txt_nombres,
            $txt_apellidos,
            $txt_direccion,
            $txt_telefono,
            $fecha_nacimiento_sql,
            $drop_puesto
        );
        $sentencia->execute();
        $sentencia->close();

        establecer_mensaje("success", "Empleado registrado correctamente.");
    }
} catch (mysqli_sql_exception $error) {
    establecer_mensaje("danger", "No se pudo completar la operación.");
}

$conexion->close();
redireccionar($pagina_actual, $buscar_actual);
