<?php
// Añadir al inicio del archivo procesar.php (antes de cualquier otro código)
ini_set('memory_limit', '1024M'); // Aumenta a 1GB

// Cargar autoload de Composer
require 'vendor/autoload.php';

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

                    // Subir datos a la base de datos
                    foreach ($filas as $fila) {
                        $contrato = $fila[0];
                        $documento = $fila[1];
                        $identificador = $fila[2];
                        $nombre_concatenado = $fila[3];
                        $fecha_entrada = $fila[4];
                        $fecha_salida = isset($fila[5]) ? $fila[5] : null;

                        $stmt = $conn->prepare("INSERT INTO data1 (contrato, documento, identificador, nombre_concatenado, fecha_entrada, fecha_salida) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssss", $contrato, $documento, $identificador, $nombre_concatenado, $fecha_entrada, $fecha_salida);
                        $stmt->execute();
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

// Función para obtener el nombre del mes
function getMonthName($date) {
    return date('F', strtotime($date));
}

// Función para obtener el nombre del día
function getDayName($date) {
    return date('D', strtotime($date));
}

// Función para generar la matriz de asistencia
function generateAttendanceMatrix($datos) {
    $attendance = [];
    $dates = [];
    $months = [];

    foreach ($datos as $data) {
        foreach ($data['filas'] as $fila) {
            $employee = $fila[2]; // Nombre Concatenado
            $rut = $fila[1]; // Documento Identificador
            $program = $fila[0]; // Contrato
            $entryDate = formatExcelDate($fila[3]); // Fecha Entrada
            $exitDate = isset($fila[4]) ? formatExcelDate($fila[4]) : null; // Fecha Salida
            $entryDateOnly = getDateOnly($entryDate);
            $exitDateOnly = $exitDate ? getDateOnly($exitDate) : null;

            if (!isset($attendance[$employee])) {
                $attendance[$employee] = [
                    'rut' => $rut,
                    'program' => $program,
                    'days' => [],
                    'countX' => 0
                ];
            }

            if ($entryDateOnly) {
                $currentDate = $entryDateOnly;
                while ($currentDate <= $exitDateOnly || ($exitDateOnly === null && $currentDate === $entryDateOnly)) {
                    $attendance[$employee]['days'][$currentDate] = [
                        'entry' => $currentDate === $entryDateOnly ? $entryDate : null,
                        'exit' => $currentDate === $exitDateOnly ? $exitDate : null,
                        'noExit' => $exitDate === null
                    ];
                    $dates[$currentDate] = true;
                    $months[getMonthName($currentDate)][] = $currentDate;
                    $attendance[$employee]['countX']++;
                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                }
            }
        }
    }

    ksort($dates);
    ksort($attendance); // Ordenar por nombre del funcionario
    return [$attendance, array_keys($dates), $months];
}

// Función para descargar la matriz en formato Excel
function downloadExcel($attendance, $dates) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Encabezados
    $sheet->setCellValue('A1', 'Funcionario');
    $sheet->setCellValue('B1', 'RUT');
    $sheet->setCellValue('C1', 'Programa');
    $col = 'D';
    foreach ($dates as $date) {
        $sheet->setCellValue($col . '1', $date);
        $col++;
    }
    $sheet->setCellValue($col . '1', 'Cantidad de X');

    // Datos
    $row = 2;
    foreach ($attendance as $employee => $info) {
        $sheet->setCellValue('A' . $row, $employee);
        $sheet->setCellValue('B' . $row, $info['rut']);
        $sheet->setCellValue('C' . $row, $info['program']);
        $col = 'D';
        foreach ($dates as $date) {
            $sheet->setCellValue($col . $row, isset($info['days'][$date]) ? 'X' : '');
            $col++;
        }
        $sheet->setCellValue($col . $row, $info['countX']);
        $row++;
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $filename = 'matriz_asistencia.xlsx';
    $writer->save($filename);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    readfile($filename);
    unlink($filename);
    exit;
}

if (isset($_POST['download_excel'])) {
    list($attendance, $dates, $months) = generateAttendanceMatrix($datos);
    downloadExcel($attendance, $dates);
}

// Función para obtener datos desde la base de datos
function getDataFromDatabase($conn) {
    $sql = "SELECT * FROM data1";
    $result = $conn->query($sql);

    $datos = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $datos[] = [
                'contrato' => $row['contrato'],
                'documento' => $row['documento'],
                'identificador' => $row['identificador'],
                'nombre_concatenado' => $row['nombre_concatenado'],
                'fecha_entrada' => $row['fecha_entrada'],
                'fecha_salida' => $row['fecha_salida']
            ];
        }
    }
    return $datos;
}

if (isset($_POST['view_database'])) {
    $datos = getDataFromDatabase($conn);
    list($attendance, $dates, $months) = generateAttendanceMatrix(['filas' => $datos]);
}
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
    <script>
        function toggleRows(index) {
            var rows = document.querySelectorAll('.extra-row-' + index);
            var button = document.getElementById('toggleButton-' + index);
            rows.forEach(row => row.style.display = row.style.display === 'none' ? '' : 'none');
            button.textContent = button.textContent === 'Ver más' ? 'Ver menos' : 'Ver más';
        }

        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Visualización de datos</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                        <p class="mt-2">
                            <a href="index.html" class="btn btn-outline-primary">Volver a intentarlo</a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <p><strong>Archivos procesados correctamente.</strong></p>
                    </div>
                    
                    <div class="button-container">
                        <form method="post" style="display: inline;">
                            <button type="submit" name="download_excel" class="btn btn-success">Descargar Matriz en Excel</button>
                        </form>
                        <form method="post" style="display: inline;">
                            <button type="submit" name="view_database" class="btn btn-info">Visualizar desde Base de Datos</button>
                        </form>
                        <a href="index.html" class="btn btn-primary">Cargar otro archivo</a>
                    </div>
                    
                    <!-- Información de los archivos -->
                    <?php foreach ($info_archivos as $index => $info_archivo): ?>
                        <div class="file-info mb-4">
                            <h4>Información del archivo <?php echo $index + 1; ?></h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nombre:</strong> <?php echo escape($info_archivo['nombre']); ?></p>
                                    <p><strong>Tamaño:</strong> <?php echo escape($info_archivo['tamano']); ?></p>
                                    <p><strong>Tipo:</strong> <?php echo escape($info_archivo['tipo']); ?></p>
                                    <p><strong>Fecha de carga:</strong> <?php echo escape($info_archivo['fecha_carga']); ?></p>
                                    <p><strong>Total de filas:</strong> <?php echo escape($info_archivo['total_filas']); ?></p>
                                    <p><strong>Total de columnas:</strong> <?php echo escape($info_archivo['total_columnas']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Autor:</strong> <?php echo escape($info_archivo['autor']); ?></p>
                                    <p><strong>Última modificación:</strong> <?php echo escape($info_archivo['ultima_modificacion']); ?></p>
                                    <p><strong>Título:</strong> <?php echo escape($info_archivo['titulo']); ?></p>
                                    <p><strong>Rango de datos:</strong> <?php echo escape($info_archivo['rango_datos']); ?></p>
                                    <p><strong>Total de hojas:</strong> <?php echo escape($info_archivo['total_hojas']); ?></p>
                                    <p><strong>Hoja activa:</strong> <?php echo escape($info_archivo['hoja_activa']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Datos cargados del archivo <?php echo $index + 1; ?>:</h4>
                        <div class="table-responsive">
                            <?php list($attendance, $dates, $months) = generateAttendanceMatrix($datos); ?>
                            <table class="table table-bordered table-hover">
                                <thead class="table-primary">
                                    <tr>
                                        <th rowspan="2">Funcionario</th>
                                        <th rowspan="2">RUT</th>
                                        <th rowspan="2">Programa</th>
                                        <?php foreach ($months as $month => $monthDates): ?>
                                            <th colspan="<?php echo count($monthDates); ?>"><?php echo escape($month); ?></th>
                                        <?php endforeach; ?>
                                        <!-- <th rowspan="2">Cantidad de X</th> -->
                                    </tr>
                                    <tr>
                                        <?php foreach ($dates as $date): ?>
                                            <th>
                                                <div class="day-name"><?php echo escape(getDayName($date)); ?></div>
                                                <?php echo escape(date('d', strtotime($date))); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $employee => $info): ?>
                                        <tr>
                                            <td><?php echo escape($employee); ?></td>
                                            <td><?php echo escape($info['rut']); ?></td>
                                            <td><?php echo escape($info['program']); ?></td>
                                            <?php foreach ($dates as $date): ?>
                                                <td class="<?php echo isset($info['days'][$date]) && $info['days'][$date]['noExit'] ? 'no-exit' : ''; ?>">
                                                    <?php if (isset($info['days'][$date])): ?>
                                                        <span data-bs-toggle="tooltip" data-bs-placement="top" title="Entrada: <?php echo escape($info['days'][$date]['entry']); ?><?php if ($info['days'][$date]['exit']): ?>&#10;Salida: <?php echo escape($info['days'][$date]['exit']); ?><?php endif; ?>">X</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td><?php echo escape($info['countX']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>