<?php
// Añadir al inicio del archivo visualizar.php (antes de cualquier otro código)
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
function generateAttendanceMatrix($datos, $events, $fecha_inicio, $fecha_termino, $eventColors) {
    $attendance = [];
    $dates = [];
    $months = [];
    $unlinkedEvents = [];

    // Generar todas las fechas en el rango
    $currentDate = $fecha_inicio;
    while ($currentDate <= $fecha_termino) {
        $dates[$currentDate] = true;
        $months[getMonthName($currentDate)][] = $currentDate;
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }

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
                if (isset($dates[$currentDate])) {
                    $attendance[$employee]['days'][$currentDate] = [
                        'entry' => $currentDate === $entryDateOnly ? $entryDate : null,
                        'exit' => $currentDate === $exitDateOnly ? $exitDate : null,
                        'noExit' => $exitDate === null,
                        'event' => null,
                        'eventColor' => ''
                    ];
                    $attendance[$employee]['countX']++;
                }
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
            $attendance[$employee]['days'][$date]['eventColor'] = getEventColor($eventType, $eventColors);
        } else {
            $unlinkedEvents[] = $event;
        }
    }

    ksort($dates);
    ksort($attendance); // Ordenar por nombre del funcionario
    return [$attendance, array_keys($dates), $months, $unlinkedEvents];
}

// Función para guardar evento en la base de datos
function saveEvent($conn, $identificador, $nombre, $fecha, $event_type) {
    $stmt = $conn->prepare("INSERT INTO employee_events (identificador, nombre, fecha, event_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $identificador, $nombre, $fecha, $event_type);
    $stmt->execute();
    $stmt->close();
}

// Función para obtener el color asociado a un tipo de evento desde la base de datos
function getEventColors($conn) {
    $colors = [];
    $sql = "SELECT nombre, color_html FROM event_types";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $colors[$row['nombre']] = $row['color_html'];
        }
    }
    return $colors;
}

// Obtener colores de eventos
$eventColors = getEventColors($conn);

// Función para obtener el color asociado a un tipo de evento
function getEventColor($event_type, $eventColors) {
    return $eventColors[$event_type] ?? "#FFFFFF";
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
list($attendance, $dates, $months, $unlinkedEvents) = generateAttendanceMatrix($datos, $events, $fecha_inicio, $fecha_termino, $eventColors);

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
        .table-responsive {
            overflow-x: auto;
        }
        .table-responsive-top {
            overflow-x: auto;
            margin-bottom: 10px;
        }
        .table thead th {
            vertical-align: bottom;
        }
        .table thead th .day-name {
            font-size: 0.8em;
            font-weight: normal;
        }
        .table tbody td {
            font-size: 0.8em;
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
        <?php foreach ($eventColors as $eventName => $color): ?>
        select option[value="<?php echo $eventName; ?>"] {
            color: <?php echo $color; ?>;
        }
        <?php endforeach; ?>
        .nowrap {
            white-space: nowrap;
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

        function showModal(employee, date, rut, eventType) {
            document.getElementById('modalEmployee').textContent = employee;
            document.getElementById('modalRut').textContent = rut;
            document.getElementById('startDate').value = date;
            document.getElementById('endDate').value = date;
            document.getElementById('statusSelect').value = eventType || '';
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
                    showModal(this.dataset.employee, this.dataset.date, this.dataset.rut, this.dataset.eventType);
                });
            });

            document.getElementById('saveButton').addEventListener('click', function() {
                var employee = document.getElementById('modalEmployee').textContent;
                var startDate = document.getElementById('startDate').value;
                var endDate = document.getElementById('endDate').value;
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
                xhr.send("employee=" + encodeURIComponent(employee) + "&start_date=" + encodeURIComponent(startDate) + "&end_date=" + encodeURIComponent(endDate) + "&rut=" + encodeURIComponent(rut) + "&event_type=" + encodeURIComponent(eventType));
            });

            document.getElementById('deleteButton').addEventListener('click', function() {
                var employee = document.getElementById('modalEmployee').textContent;
                var date = document.getElementById('startDate').value;
                var rut = document.getElementById('modalRut').textContent;

                var xhr = new XMLHttpRequest();
                xhr.open("POST", "delete_event.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        alert("Evento eliminado exitosamente");
                        location.reload();
                    }
                };
                xhr.send("employee=" + encodeURIComponent(employee) + "&date=" + encodeURIComponent(date) + "&rut=" + encodeURIComponent(rut));
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
                    <form method="get" action="../index.php" style="display: inline;">
                        <button type="submit" class="btn btn-primary">Volver</button>
                    </form>
                </div>

                <div class="legend">
                    <?php foreach ($eventColors as $eventName => $color): ?>
                    <div class="legend-item"><span class="event-color" style="background-color: <?php echo $color; ?>;"></span><?php echo $eventName; ?></div>
                    <?php endforeach; ?>
                </div>

                <form method="get" action="visualizar.php">
                    <div class="row mb-3">
                        <div class="col">
                            <label for="formato" class="form-label">Formato</label>
                            <select class="form-select" id="formato" name="formato">
                                <option value="1" <?php echo $formato == '1' ? 'selected' : ''; ?>>Cordillera</option>
                                <option value="2" <?php echo $formato == '2' ? 'selected' : ''; ?>>Patache</option>
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

                <form method="post" action="export_excel.php">
                    <input type="hidden" name="fecha_inicio" value="<?php echo escape($fecha_inicio); ?>">
                    <input type="hidden" name="fecha_termino" value="<?php echo escape($fecha_termino); ?>">
                    <input type="hidden" name="formato" value="<?php echo escape($formato); ?>">
                    <button type="submit" class="btn btn-success">Exportar a Excel</button>
                </form><br>


                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th rowspan="2" class="nowrap">Funcionario</th>
                                <th rowspan="2" class="nowrap">RUT</th>
                                <th rowspan="2" class="nowrap">Programa</th>
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
                                    <td class="nowrap"><?php echo escape($employee); ?></td>
                                    <td class="nowrap"><?php echo escape($info['rut']); ?></td>
                                    <td class="nowrap"><?php echo escape($info['program']); ?></td>
                                    <?php foreach ($dates as $date): ?>
                                        <?php
                                            $eventColor = isset($info['days'][$date]['event']) ? getEventColor($info['days'][$date]['event'], $eventColors) : '';
                                            $noExitClass = isset($info['days'][$date]) && $info['days'][$date]['noExit'] ? 'no-exit' : '';
                                            $unlinkedEvent = array_filter($unlinkedEvents, function($event) use ($employee, $date) {
                                                return $event['nombre'] === $employee && getDateOnly($event['fecha']) === $date;
                                            });
                                            $unlinkedEventColor = !empty($unlinkedEvent) ? getEventColor(reset($unlinkedEvent)['event_type'], $eventColors) : '';
                                        ?>
                                        <td class="attendance-cell <?php echo $noExitClass; ?>" data-employee="<?php echo escape($employee); ?>" data-date="<?php echo escape($date); ?>" data-rut="<?php echo escape($info['rut']); ?>" data-event-type="<?php echo escape($info['days'][$date]['event'] ?? ''); ?>" style="background-color: <?php echo $eventColor ?: $unlinkedEventColor; ?>;">
                                            <?php if (isset($info['days'][$date])): ?>
                                                <span data-bs-toggle="tooltip" data-bs-placement="top" title="Entrada: <?php echo escape($info['days'][$date]['entry']); ?><?php if ($info['days'][$date]['exit']): ?>&#10;Salida: <?php echo escape($info['days'][$date]['exit']); ?><?php endif; ?>">X</span>
                                            <?php elseif (!empty($unlinkedEvent)): ?>
                                                <span data-bs-toggle="tooltip" data-bs-placement="top" title="Evento: <?php echo escape(reset($unlinkedEvent)['event_type']); ?>"></span>
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
                    <div class="mb-3">
                        <label for="startDate" class="form-label">Fecha de Inicio</label>
                        <input type="date" class="form-control" id="startDate">
                    </div>
                    <div class="mb-3">
                        <label for="endDate" class="form-label">Fecha de Término</label>
                        <input type="date" class="form-control" id="endDate">
                    </div>
                    <div class="mb-3">
                        <label for="statusSelect" class="form-label">Estado</label>
                        <select class="form-select" id="statusSelect">
                            <option value="">Seleccionar estado</option>
                            <?php foreach ($eventColors as $eventName => $color): ?>
                            <option value="<?php echo $eventName; ?>"><?php echo $eventName; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="saveButton">Guardar</button>
                    <button type="button" class="btn btn-danger" id="deleteButton">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleRows(index) {
            var rows = document.querySelectorAll('.extra-row-' + index);
            var button = document.getElementById('toggleButton-' + index);
            rows.forEach(row => row.style.display = row.style.display === 'none' ? '' : 'none');
            button.textContent = button.textContent === 'Ver más' ? 'Ver menos' : 'Ver más';
        }

        function showModal(employee, date, rut, eventType) {
            document.getElementById('modalEmployee').textContent = employee;
            document.getElementById('modalRut').textContent = rut;
            document.getElementById('startDate').value = date;
            document.getElementById('endDate').value = date;
            document.getElementById('statusSelect').value = eventType || '';
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
                    showModal(this.dataset.employee, this.dataset.date, this.dataset.rut, this.dataset.eventType);
                });
            });

            document.getElementById('saveButton').addEventListener('click', function() {
                var employee = document.getElementById('modalEmployee').textContent;
                var startDate = document.getElementById('startDate').value;
                var endDate = document.getElementById('endDate').value;
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
                xhr.send("employee=" + encodeURIComponent(employee) + "&start_date=" + encodeURIComponent(startDate) + "&end_date=" + encodeURIComponent(endDate) + "&rut=" + encodeURIComponent(rut) + "&event_type=" + encodeURIComponent(eventType));
            });

            document.getElementById('deleteButton').addEventListener('click', function() {
                var employee = document.getElementById('modalEmployee').textContent;
                var date = document.getElementById('startDate').value;
                var rut = document.getElementById('modalRut').textContent;

                var xhr = new XMLHttpRequest();
                xhr.open("POST", "delete_event.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        alert("Evento eliminado exitosamente");
                        location.reload();
                    }
                };
                xhr.send("employee=" + encodeURIComponent(employee) + "&date=" + encodeURIComponent(date) + "&rut=" + encodeURIComponent(rut));
            });
        });
    </script>
</body>
</html>

