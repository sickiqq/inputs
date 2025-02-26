<?php
// Añadir al inicio del archivo visualizar.php (antes de cualquier otro código)
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

// Función para escapar valores de forma segura
function escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Función para generar la matriz de asistencia
function generateAttendanceMatrix($datos) {
    $attendance = [];
    $dates = [];
    $months = [];

    foreach ($datos as $fila) {
        $employee = $fila['nombre_concatenado']; // Nombre Concatenado
        $rut = $fila['documento']; // Documento Identificador
        $program = $fila['contrato']; // Contrato
        $entryDate = formatExcelDate($fila['fecha_entrada']); // Fecha Entrada
        $exitDate = isset($fila['fecha_salida']) ? formatExcelDate($fila['fecha_salida']) : null; // Fecha Salida
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

    ksort($dates);
    ksort($attendance); // Ordenar por nombre del funcionario
    return [$attendance, array_keys($dates), $months];
}

// Función para obtener datos desde la base de datos
function getDataFromDatabase($conn) {
    $sql = "SELECT * FROM data1";
    $result = $conn->query($sql);

    $datos = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $datos[] = $row;
        }
    }
    return $datos;
}

$datos = getDataFromDatabase($conn);
list($attendance, $dates, $months) = generateAttendanceMatrix($datos);

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
                <div class="button-container">
                    <form method="post" action="cargar.php" style="display: inline;">
                        <button type="submit" class="btn btn-primary">Cargar otro archivo</button>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th rowspan="2">Funcionario</th>
                                <th rowspan="2">RUT</th>
                                <th rowspan="2">Programa</th>
                                <?php foreach ($months as $month => $monthDates): ?>
                                    <th colspan="<?php echo count($monthDates); ?>"><?php echo escape($month); ?></th>
                                <?php endforeach; ?>
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
            </div>
        </div>
    </div>
</body>
</html>

