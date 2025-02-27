<?php
// Añadir al inicio del archivo cargar.php (antes de cualquier otro código)
ini_set('memory_limit', '1024M'); // Aumenta a 1GB

// Cargar autoload de Composer
require '../vendor/autoload.php';

// Conectar a la base de datos
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "inputs_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

use PhpOffice\PhpSpreadsheet\IOFactory;

// Variables para almacenar mensajes de error e información de los archivos
$error = '';
$datos = [];
$info_archivos = [];
$initial_format = isset($_POST['initial_format']) ? json_decode($_POST['initial_format'], true) : null;
$formato = isset($_POST['formato']) ? $_POST['formato'] : '1';

// Función para formatear fechas de Excel
function formatExcelDate($excelDate) {
    if (is_numeric($excelDate)) {
        $unixDate = ($excelDate - 25569) * 86400;
        return gmdate("Y-m-d H:i:s", (int)$unixDate);
    }
    return $excelDate;
}

// Función para obtener solo la fecha de una fecha completa
function getDateOnly($dateTime) {
    return explode(' ', $dateTime)[0];
}

// Comprobar si se han enviado archivos
if (isset($_FILES['archivos']) && count($_FILES['archivos']['name']) > 0) {
    foreach ($_FILES['archivos']['tmp_name'] as $index => $archivo) {
        if ($_FILES['archivos']['error'][$index] == 0) {
            // Obtener información del archivo
            $nombre_archivo = $_FILES['archivos']['name'][$index];
            $tamano_archivo = $_FILES['archivos']['size'][$index];
            $tipo_archivo = $_FILES['archivos']['type'][$index];
            
            // Almacenar información del archivo
            $info_archivo = [
                'nombre' => $nombre_archivo,
                'tamano' => formatBytes($tamano_archivo),
                'tipo' => $tipo_archivo,
                'fecha_carga' => date('Y-m-d H:i:s'),
            ];
            
            // Comprobar el tipo de archivo
            $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
            if ($extension != 'xlsx' && $extension != 'xls') {
                $error = 'Por favor, sube un archivo Excel válido (.xlsx o .xls)';
                break;
            } else {
                try {
                    // Cargar el archivo Excel de manera más eficiente
                    $reader = IOFactory::createReader($extension == 'xlsx' ? 'Xlsx' : 'Xls');
                    $reader->setReadDataOnly(true); // Solo lee datos, no formatos
                    $spreadsheet = $reader->load($archivo);
                    
                    // Obtener más información del documento
                    $info_archivo['autor'] = $spreadsheet->getProperties()->getCreator() ?: 'No disponible';
                    $modified = $spreadsheet->getProperties()->getModified();
                    $info_archivo['ultima_modificacion'] = $modified ? 
                        (is_object($modified) ? $modified->format('Y-m-d H:i:s') : date('Y-m-d H:i:s', $modified)) : 
                        'No disponible';
                    $info_archivo['titulo'] = $spreadsheet->getProperties()->getTitle() ?: 'No disponible';
                    $info_archivo['descripcion'] = $spreadsheet->getProperties()->getDescription() ?: 'No disponible';
                    $info_archivo['total_hojas'] = $spreadsheet->getSheetCount();
                    $info_archivo['hoja_activa'] = $spreadsheet->getActiveSheet()->getTitle();
                    
                    $hoja = $spreadsheet->getActiveSheet();
                    
                    // Obtener el rango de celdas utilizadas
                    $mayor_fila = $hoja->getHighestDataRow();
                    $mayor_columna = $hoja->getHighestDataColumn();
                    $info_archivo['rango_datos'] = 'A1:' . $mayor_columna . $mayor_fila;
                    $info_archivo['total_filas'] = $mayor_fila;
                    $info_archivo['total_columnas'] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($mayor_columna);
                    
                    // Obtener datos
                    $filas = $hoja->toArray();
            
                    // Procesar encabezados y datos
                    $tieneEncabezados = true; // Cambiar a false si no hay encabezados
                    if ($tieneEncabezados && count($filas) > 0) {
                        $encabezados = array_shift($filas);
                    } else {
                        // Si no hay encabezados, crear encabezados genéricos basados en el número de columnas
                        $encabezados = array_map(function($i) {
                            return "Columna " . ($i + 1);
                        }, range(0, count($filas[0]) - 1));
                    }
                    
                    // Validar formato del archivo
                    if ($initial_format === null) {
                        $initial_format = $encabezados;
                    } elseif ($initial_format !== $encabezados) {
                        $error = 'Todos los archivos deben tener el mismo formato.';
                        break;
                    }
                    
                    // Almacenar los datos para pasar a la vista
                    $datos[] = [
                        'encabezados' => $encabezados,
                        'filas' => $filas
                    ];
                    $info_archivos[] = $info_archivo;

                    if ($formato == '1') {
                        // Subir datos a la base de datos
                        foreach ($filas as $fila) {
                            $contrato = $fila[0];
                            $identificador = $fila[1];
                            $nombre = $fila[2];
                            $fecha_entrada = formatExcelDate($fila[3]);
                            $fecha_salida = isset($fila[4]) ? formatExcelDate($fila[4]) : null;

                            // Verificar si el registro ya existe
                            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM data1 WHERE contrato = ? AND identificador = ?");
                            $check_stmt->bind_param("ss", $contrato, $identificador);
                            $check_stmt->execute();
                            $check_stmt->bind_result($count);
                            $check_stmt->fetch();
                            $check_stmt->close();

                            if ($count == 0) {
                                $stmt = $conn->prepare("INSERT INTO data1 (contrato, identificador, nombre, fecha_entrada, fecha_salida) VALUES (?, ?, ?, ?, ?)");
                                $stmt->bind_param("sssss", $contrato, $identificador, $nombre, $fecha_entrada, $fecha_salida);
                                $stmt->execute();
                            }
                        }
                    } elseif ($formato == '2') {
                        // Process Formato 2
                        $entries = [];
                        foreach ($filas as $fila) {
                            $fecha_hora = formatExcelDate($fila[0]);
                            $nombre = $fila[1];
                            $identificador = $fila[2];
                            $tipo_accion = $fila[3];
                            $ubicacion = $fila[4];
                            $punto_control = $fila[5];
                            $rut_empresa = $fila[6];
                            $tipo_personal = $fila[7];
                            $codigo_contrato = $fila[8];

                            $fecha = getDateOnly($fecha_hora);

                            if (!isset($entries[$identificador][$fecha])) {
                                $entries[$identificador][$fecha] = [
                                    'nombre' => $nombre,
                                    'identificador' => $identificador,
                                    'contrato' => $codigo_contrato,
                                    'fecha_entrada' => null,
                                    'fecha_salida' => null,
                                    'tipo_accion' => $tipo_accion,
                                    'ubicacion' => $ubicacion,
                                    'punto_control' => $punto_control,
                                    'rut_empresa' => $rut_empresa,
                                    'tipo_personal' => $tipo_personal
                                ];
                            }

                            if ($tipo_accion == 'Entrada') {
                                $entries[$identificador][$fecha]['fecha_entrada'] = $fecha_hora;
                            } elseif ($tipo_accion == 'Salida') {
                                $entries[$identificador][$fecha]['fecha_salida'] = $fecha_hora;
                            }
                        }

                        foreach ($entries as $identificador => $dates) {
                            foreach ($dates as $date => $entry) {
                                // Verificar si el registro ya existe
                                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM data2 WHERE identificador = ? AND contrato = ? AND fecha_entrada = ? AND fecha_salida = ?");
                                $check_stmt->bind_param("ssss", $entry['identificador'], $entry['contrato'], $entry['fecha_entrada'], $entry['fecha_salida']);
                                $check_stmt->execute();
                                $check_stmt->bind_result($count);
                                $check_stmt->fetch();
                                $check_stmt->close();

                                if ($count == 0) {
                                    $stmt = $conn->prepare("INSERT INTO data2 (identificador, nombre, contrato, fecha_entrada, fecha_salida, tipo_accion, ubicacion, punto_control, rut_empresa, tipo_personal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                    $stmt->bind_param("ssssssssss", $entry['identificador'], $entry['nombre'], $entry['contrato'], $entry['fecha_entrada'], $entry['fecha_salida'], $entry['tipo_accion'], $entry['ubicacion'], $entry['punto_control'], $entry['rut_empresa'], $entry['tipo_personal']);
                                    $stmt->execute();
                                }
                            }
                        }
                    }
                    
                } catch (Exception $e) {
                    $error = 'Error al procesar el archivo: ' . $e->getMessage();
                    break;
                }
            }
        } else {
            $error = 'No se ha subido ningún archivo o ha ocurrido un error durante la subida.';
            break;
        }
    }
} else {
    $error = 'No se ha subido ningún archivo o ha ocurrido un error durante la subida.';
}

// Función para formatear bytes a unidades legibles
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Función para escapar valores de forma segura
function escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador de Excel - Resultados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        .container {
            width: 100%;
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .file-info {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 20px;
        }
        .table thead th {
            vertical-align: bottom;
        }
        .table thead th .day-name {
            font-size: 0.8em;
            font-weight: normal;
        }
        .tooltip-inner {
            max-width: 200px;
            white-space: pre-wrap;
        }
        .no-exit {
            background-color: rgba(255, 255, 0, 0.3) !important;
        }
        .button-container {
            text-align: right;
            margin-bottom: 20px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Resultados de la carga</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                        <p class="mt-2">
                            <a href="../index.php" class="btn btn-outline-primary">Volver a intentarlo</a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <p><strong>Archivos procesados y datos subidos correctamente.</strong></p>
                        <a href="../index.php" class="btn btn-info">Volver</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
