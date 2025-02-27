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

// Función para obtener eventos desde la base de datos
function getEventsFromDatabase($conn, $fecha_inicio, $fecha_termino) {
    $sql = "SELECT * FROM employee_events WHERE fecha BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_termino);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    $stmt->close();
    return $events;
}

// Función para generar la matriz de asistencia
function generateAttendanceMatrix($datos, $events) {
    $attendance = [];
    $dates = [];
    $months = [];

    foreach ($datos as $fila) {
        $employee = $fila['nombre']; // Nombre Concatenado
        $rut = $fila['identificador']; // Documento Identificador
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
                    'noExit' => $exitDate === null,
                    'event' => null,
                    'eventColor' => ''
                ];
                $dates[$currentDate] = true;
                $months[getMonthName($currentDate)][] = $currentDate;
                $attendance[$employee]['countX']++;
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }
        }
    }

    foreach ($events as $event) {
        $employee = $event['nombre'];
        $date = getDateOnly($event['fecha']); // Obtener solo la fecha sin la hora
        $eventType = $event['event_type'];

        if (isset($attendance[$employee]) && isset($attendance[$employee]['days'][$date])) {
            $attendance[$employee]['days'][$date]['event'] = $eventType;
            $attendance[$employee]['days'][$date]['eventColor'] = getEventColor($eventType);
        }
    }

    ksort($dates);
    ksort($attendance); // Ordenar por nombre del funcionario
    return [$attendance, array_keys($dates), $months];
}

// Función para guardar evento en la base de datos
function saveEvent($conn, $identificador, $nombre, $fecha, $event_type) {
    $stmt = $conn->prepare("INSERT INTO employee_events (identificador, nombre, fecha, event_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $identificador, $nombre, $fecha, $event_type);
    $stmt->execute();
    $stmt->close();
}

// Función para obtener el color asociado a un tipo de evento
function getEventColor($event_type) {
    $colors = [
        "Ni idea que pasa" => "#FF0000",
        "Cambio de turno" => "#FFA500",
        "Licencia medica" => "#FFFF00",
        "Trabajador con asistencia en Talana" => "#008000",
        "Trabajador con asistencia en Planificación" => "#0000FF",
        "Permiso con o sin goce o falla" => "#4B0082",
        "Teletrabajo" => "#EE82EE",
        "Vacaciones, nacimiento, sindical" => "#A52A2A",
        "Finiquito" => "#000000",
        "Cambio Faena" => "#808080"
    ];
    return $colors[$event_type] ?? "#FFFFFF";
}

$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_termino = isset($_GET['fecha_termino']) ? $_GET['fecha_termino'] : date('Y-m-t');
$formato = isset($_GET['formato']) ? $_GET['formato'] : '1';

$datos = [];

if ($formato == '1') {

    $sql = "SELECT * FROM data1 WHERE fecha_entrada BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_termino);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $datos[] = $row;
    }
    $stmt->close();
} elseif ($formato == '2') {
    $sql = "SELECT * FROM data2 WHERE fecha_entrada BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_termino);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $datos[] = $row;
    }
    $stmt->close();
}

$events = getEventsFromDatabase($conn, $fecha_inicio, $fecha_termino);
list($attendance, $dates, $months) = generateAttendanceMatrix($datos, $events);

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
        .modal-content {
            padding: 20px;
        }
        .event-color {
            display: inline-block;
            width: 10px;
            height: 10px;
            margin-right: 5px;
        }
        .event-option {
            display: flex;
            align-items: center;
        }
        .legend {
            margin-bottom: 20px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        select option {
            color: black;
        }
        select option[value="Ni idea que pasa"] {
            color: #FF0000;
        }
        select option[value="Cambio de turno"] {
            color: #FFA500;
        }
        select option[value="Licencia medica"] {
            color: #FFFF00;
        }
        select option[value="Trabajador con asistencia en Talana"] {
            color: #008000;
        }
        select option[value="Trabajador con asistencia en Planificación"] {
            color: #0000FF;
        }
        select option[value="Permiso con o sin goce o falla"] {
            color: #4B0082;
        }
        select option[value="Teletrabajo"] {
            color: #EE82EE;
        }
        select option[value="Vacaciones, nacimiento, sindical"] {
            color: #A52A2A;
        }
        select option[value="Finiquito"] {
            color: #000000;
        }
        select option[value="Cambio Faena"] {
            color: #808080;
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

        function showModal(employee, date, rut) {
            document.getElementById('modalEmployee').textContent = employee;
            document.getElementById('modalDate').textContent = date;
            document.getElementById('modalRut').textContent = rut;
            var modal = new bootstrap.Modal(document.getElementById('infoModal'));
            modal.show();
        }

        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })

            document.querySelectorAll('.attendance-cell').forEach(cell => {
                cell.addEventListener('click', function() {
                    showModal(this.dataset.employee, this.dataset.date, this.dataset.rut);
                });
            });

            document.getElementById('saveButton').addEventListener('click', function() {
                var employee = document.getElementById('modalEmployee').textContent;
                var date = document.getElementById('modalDate').textContent;
                var rut = document.getElementById('modalRut').textContent;
                var eventType = document.getElementById('statusSelect').value;

                var xhr = new XMLHttpRequest();
                xhr.open("POST", "save_event.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        alert("Evento guardado exitosamente");
                        location.reload();
                    }
                };
                xhr.send("employee=" + encodeURIComponent(employee) + "&date=" + encodeURIComponent(date) + "&rut=" + encodeURIComponent(rut) + "&event_type=" + encodeURIComponent(eventType));
            });
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
                    <form method="get" action="index.php" style="display: inline;">
                        <button type="submit" class="btn btn-primary">Volver</button>
                    </form>
                </div>

                <div class="legend">
                    <div class="legend-item"><span class="event-color" style="background-color: #FF0000;"></span>Ni idea que pasa</div>
                    <div class="legend-item"><span class="event-color" style="background-color: #FFA500;"></span>Cambio de turno</div>
                    <div class="legend-item"><span class="event-color" style="background-color: #FFFF00;"></span>Licencia medica</div>
                    <div class="legend-item"><span class="event-color" style="background-color: #008000;"></span>Trabajador con asistencia en Talana</div>
                    <div class="legend-item"><span class="event-color" style="background-color: #0000FF;"></span>Trabajador con asistencia en Planificación</div>
                    <div class="legend-item"><span class="event-color" style="background-color: #4B0082;"></span>Permiso con o sin goce o falla</div>
                    <div class="legend-item"><span class="event-color" style="background-color: #EE82EE;"></span>Teletrabajo</div>
                    <div class="legend-item"><span class="event-color" style="background-color: #A52A2A;"></span>Vacaciones, nacimiento, sindical</div>
                    <div class="legend-item"><span class="event-color" style="background-color: #000000;"></span>Finiquito</div>
                    <div class="legend-item"><span class="event-color" style="background-color: #808080;"></span>Cambio Faena</div>
                </div>

                <form method="get" action="visualizar.php">
                    <div class="row mb-3">
                        <div class="col">
                            <label for="formato" class="form-label">Formato</label>
                            <select class="form-select" id="formato" name="formato">
                                <option value="1" <?php echo $formato == '1' ? 'selected' : ''; ?>>Formato 1</option>
                                <option value="2" <?php echo $formato == '2' ? 'selected' : ''; ?>>Formato 2</option>
                            </select>
                        </div>
                        <div class="col">
                            <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo escape($fecha_inicio); ?>">
                        </div>
                        <div class="col">
                            <label for="fecha_termino" class="form-label">Fecha Término</label>
                            <input type="date" class="form-control" id="fecha_termino" name="fecha_termino" value="<?php echo escape($fecha_termino); ?>">
                        </div>
                        
                        <div class="col d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                        </div>
                    </div>
                </form>

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
                                        <?php
                                            $eventColor = isset($info['days'][$date]['event']) ? getEventColor($info['days'][$date]['event']) : '';
                                            $noExitClass = isset($info['days'][$date]) && $info['days'][$date]['noExit'] ? 'no-exit' : '';
                                        ?>
                                        <td class="attendance-cell <?php echo $noExitClass; ?>" data-employee="<?php echo escape($employee); ?>" data-date="<?php echo escape($date); ?>" data-rut="<?php echo escape($info['rut']); ?>" style="background-color: <?php echo $eventColor; ?>;">
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

    <!-- Modal -->
    <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="infoModalLabel">Información del bloque</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Funcionario:</strong> <span id="modalEmployee"></span></p>
                    <p><strong>RUT:</strong> <span id="modalRut"></span></p>
                    <p><strong>Fecha:</strong> <span id="modalDate"></span></p>
                    <div class="mb-3">
                        <label for="statusSelect" class="form-label">Estado</label>
                        <select class="form-select" id="statusSelect">
                            <option value="Ni idea que pasa">Ni idea que pasa</option>
                            <option value="Cambio de turno">Cambio de turno</option>
                            <option value="Licencia medica">Licencia medica</option>
                            <option value="Trabajador con asistencia en Talana">Trabajador con asistencia en Talana</option>
                            <option value="Trabajador con asistencia en Planificación">Trabajador con asistencia en Planificación</option>
                            <option value="Permiso con o sin goce o falla">Permiso con o sin goce o falla</option>
                            <option value="Teletrabajo">Teletrabajo</option>
                            <option value="Vacaciones, nacimiento, sindical">Vacaciones, nacimiento, sindical</option>
                            <option value="Finiquito">Finiquito</option>
                            <option value="Cambio Faena">Cambio Faena</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="saveButton">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

