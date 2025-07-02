<?php
// Añadir al inicio del archivo visualizar.php (antes de cualquier otro código)
ini_set('memory_limit', '1024M'); // Aumenta a 1GB

// Cargar autoload de Composer
require '../vendor/autoload.php';
require '../includes/functions.php'; // Include the shared functions file

// Conectar a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "inputs_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Función para escapar valores de forma segura
function escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Funciones para interactuar con la base de datos
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

function saveEvent($conn, $identificador, $nombre, $fecha, $event_type) {
    $stmt = $conn->prepare("INSERT INTO employee_events (identificador, nombre, fecha, event_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $identificador, $nombre, $fecha, $event_type);
    $stmt->execute();
    $stmt->close();
}

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

// Obtener parámetros de la URL
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_termino = isset($_GET['fecha_termino']) ? $_GET['fecha_termino'] : date('Y-m-t');
$formato = isset($_GET['formato']) ? $_GET['formato'] : '1';

// Obtener datos según formato seleccionado
$datos = [];
if ($formato == '1') {
    $sql = "SELECT * FROM data1 WHERE fecha_entrada <= ? AND (fecha_salida IS NULL OR fecha_salida >= ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fecha_termino, $fecha_inicio);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $datos[] = $row;
    }
    $stmt->close();
} elseif ($formato == '2') {
    $sql = "SELECT * FROM data2 WHERE fecha_entrada <= ? AND (fecha_salida IS NULL OR fecha_salida >= ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fecha_termino, $fecha_inicio);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $datos[] = $row;
    }
    $stmt->close();
}

// Obtener eventos y colores
$eventColors = getEventColors($conn);
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
        .bold-x {
            font-weight: bold;
        }
        .sticky-wrapper {
            position: relative;
            overflow-x: auto;
            overflow-y: visible;
            border: 1px solid #dee2e6;
            background: white;
        }
        
        .sticky-col {
            position: sticky;
            background: white;
            z-index: 1000;
            border-right: 2px solid #dee2e6 !important;
        }
        
        .sticky-funcionario {
            left: 0;
            min-width: 200px;
            max-width: 200px;
        }
        
        .sticky-rut {
            left: 200px;
            min-width: 120px;
            max-width: 120px;
        }
        
        .sticky-proyecto {
            left: 320px;
            min-width: 150px;
            max-width: 150px;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th, .table td {
            white-space: nowrap;
        }
        .double-scroll {
            width: 100%;
        }
        
        .top-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            margin-bottom: 5px;
        }
        
        .top-scroll-spacer {
            height: 1px;
            min-width: 100%;
        }

        .sticky-wrapper {
            border: 1px solid #dee2e6;
            margin-top: 5px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
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

                <div class="double-scroll">
                    <div class="top-scroll">
                        <div class="top-scroll-spacer"></div>
                    </div>
                    <div class="sticky-wrapper">
                        <table class="table table-bordered table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th rowspan="2" class="sticky-col sticky-funcionario">Proyecto</th>
                                    <th rowspan="2" class="sticky-col sticky-rut">RUT</th>
                                    <th rowspan="2" class="sticky-col sticky-proyecto">Funcionario</th>
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
                                <?php foreach ($attendance as $key => $info): 
                                    list($employee, $program) = explode('|', $key);
                                ?>
                                    <tr>
                                        <td class="sticky-col sticky-funcionario"><?php echo escape($info['program']); ?></td>
                                        <td class="sticky-col sticky-rut"><?php echo escape($info['rut']); ?></td>
                                        <td class="sticky-col sticky-proyecto"><?php echo escape($employee); ?></td>
                                        <?php foreach ($dates as $date): ?>
                                            <?php
                                                $eventColor = isset($info['days'][$date]['event']) ? getEventColor($info['days'][$date]['event'], $eventColors) : '';
                                                $noExitClass = isset($info['days'][$date]) && $info['days'][$date]['noExit'] ? 'no-exit' : '';
                                                $unlinkedEvent = array_filter($unlinkedEvents, function($event) use ($employee, $date) {
                                                    return $event['nombre'] === $employee && getDateOnly($event['fecha']) === $date;
                                                });
                                                $unlinkedEventColor = !empty($unlinkedEvent) ? getEventColor(reset($unlinkedEvent)['event_type'], $eventColors) : '';
                                                $boldClass = isset($info['days'][$date]['manualEntry']) && $info['days'][$date]['manualEntry'] == 1 ? 'bold-x' : '';
                                            ?>
                                            <td class="attendance-cell <?php echo $noExitClass; ?> <?php echo $boldClass; ?>" 
                                                data-employee="<?php echo escape($employee); ?>" 
                                                data-date="<?php echo escape($date); ?>" 
                                                data-rut="<?php echo escape($info['rut']); ?>" 
                                                data-event-type="<?php echo escape($info['days'][$date]['event'] ?? ''); ?>"
                                                data-project="<?php echo escape($info['program']); ?>"
                                                data-manual-entry="<?php echo isset($info['days'][$date]['manualEntry']) ? $info['days'][$date]['manualEntry'] : '1'; ?>"
                                                style="background-color: <?php echo $eventColor ?: $unlinkedEventColor; ?>;">
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
                    <p><strong>Proyecto:</strong> <span id="modalProject"></span></p>
                    <input type="hidden" id="modalProjectHidden">
                    <input type="hidden" id="modalManualEntry">
                    
                    <!-- Nueva estructura para fechas en la misma fila -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="startDate" class="form-label">Fecha de Inicio</label>
                            <input type="date" class="form-control" id="startDate">
                        </div>
                        <div class="col-md-6">
                            <label for="endDate" class="form-label">Fecha de Término</label>
                            <input type="date" class="form-control" id="endDate">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="statusSelect" class="form-label">Estado</label>
                        <select class="form-select" id="statusSelect">
                            <option value="">Seleccionar estado</option>
                            <?php foreach ($eventColors as $eventName => $color): ?>
                            <option value="<?php echo $eventName; ?>"><?php echo $eventName; ?></option>
                            <?php endforeach; ?>
                            <option value="Asistencia">Asistencia</option> <!-- New option added -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <!-- Botones alineados a izquierda y derecha -->
                    <button type="button" class="btn btn-primary" id="saveButton">Guardar</button>
                    <button type="button" class="btn btn-danger" id="deleteButton">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Funciones de utilidad
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
            
            // Modificar el selector para que sea más específico
            const clickedCell = event.target.closest('.attendance-cell');
            if (clickedCell) {
                const project = clickedCell.dataset.project;
                const manualEntry = clickedCell.dataset.manualEntry;
                document.getElementById('modalProject').textContent = project;
                document.getElementById('modalProjectHidden').value = project;
                document.getElementById('modalManualEntry').value = manualEntry;
            }
            
            var modal = new bootstrap.Modal(document.getElementById('infoModal'));
            modal.show();
        }

        function saveToDatabase(table, data) {
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "save_to_database.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        console.log("Data saved to " + table);
                    } else {
                        console.error("Error saving data to " + table + ": " + xhr.statusText);
                    }
                }
            };
            var params = "table=" + encodeURIComponent(table);
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    params += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(data[key]);
                }
            }
            xhr.send(params);
        }

        // Inicialización del documento
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Agregar evento de clic a las celdas de asistencia
            document.querySelectorAll('.attendance-cell').forEach(cell => {
                cell.addEventListener('click', function(event) {
                    showModal(
                        this.dataset.employee,
                        this.dataset.date,
                        this.dataset.rut,
                        this.dataset.eventType
                    );
                });
            });

            // Configurar el botón Guardar
            document.getElementById('saveButton').addEventListener('click', function(event) {
                event.preventDefault(); // Prevent default form submission

                var employee = document.getElementById('modalEmployee').textContent;
                var startDate = document.getElementById('startDate').value;
                var endDate = document.getElementById('endDate').value;
                var rut = document.getElementById('modalRut').textContent;
                var eventType = document.getElementById('statusSelect').value;

                if (!eventType) {
                    alert("Por favor, seleccione un tipo de evento.");
                    return;
                }

                if (eventType === 'Asistencia') {
                    var location = document.getElementById('formato').value; // Assume this gets the location (1 for cordillera, 2 for patache)
                    var table = location === '1' ? 'data1' : 'data2';
                    var data = {
                        identificador: rut,
                        nombre: employee,
                        contrato: document.getElementById('modalProjectHidden').value, // Get the project value
                        fecha_entrada: startDate,
                        fecha_salida: endDate,
                        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ') // Format created_at correctly
                    };

                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "save_to_database.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4) {
                            if (xhr.status === 200) {
                                console.log(xhr.responseText); // Log the response text
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        // alert("Evento guardado exitosamente");
                                        window.location.reload();
                                    } else {
                                        alert("Error al guardar el evento: " + response.message);
                                    }
                                } catch (e) {
                                    alert("Error al procesar la respuesta del servidor.");
                                }
                            } else {
                                alert("Error al guardar el evento: " + xhr.statusText);
                            }
                        }
                    };
                    var params = "table=" + encodeURIComponent(table);
                    for (var key in data) {
                        if (data.hasOwnProperty(key)) {
                            params += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(data[key]);
                        }
                    }
                    xhr.send(params);
                } else {
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "save_event.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            // alert("Evento guardado exitosamente");
                            window.location.reload();
                        }
                    };
                    xhr.send("employee=" + encodeURIComponent(employee) + 
                             "&start_date=" + encodeURIComponent(startDate) + 
                             "&end_date=" + encodeURIComponent(endDate) + 
                             "&rut=" + encodeURIComponent(rut) + 
                             "&event_type=" + encodeURIComponent(eventType));
                }
            });

            // Configurar el botón Eliminar
            document.getElementById('deleteButton').addEventListener('click', function(event) {
                event.preventDefault(); // Prevent default form submission

                var employee = document.getElementById('modalEmployee').textContent;
                var date = document.getElementById('startDate').value;
                var rut = document.getElementById('modalRut').textContent;
                var project = document.getElementById('modalProjectHidden').value;
                var manualEntry = document.getElementById('modalManualEntry').value;

                function proceedWithDelete() {
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "delete_event.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            location.reload();
                        }
                    };
                    xhr.send("employee=" + encodeURIComponent(employee) + 
                             "&date=" + encodeURIComponent(date) + 
                             "&rut=" + encodeURIComponent(rut) +
                             "&project=" + encodeURIComponent(project));
                }

                console.log('Parámetros de eliminación:', {
                    employee: employee,
                    date: date,
                    rut: rut,
                    project: project,
                    manualEntry: manualEntry
                });

                if (manualEntry === '0') {
                    if (confirm("Este registro corresponde a una importación del reloj control. ¿Está seguro de que desea eliminarlo?")) {
                        proceedWithDelete();
                    }
                } else {
                    proceedWithDelete();
                }
            });

            // Set up synchronized scrolling
            const topScroll = document.querySelector('.top-scroll');
            const bottomScroll = document.querySelector('.sticky-wrapper');
            const spacer = document.querySelector('.top-scroll-spacer');

            // Set initial width
            function updateScrollWidth() {
                spacer.style.width = bottomScroll.querySelector('table').offsetWidth + 'px';
            }
            
            // Update width after page loads
            updateScrollWidth();
            
            // Update width when window resizes
            window.addEventListener('resize', updateScrollWidth);
            
            // Sync the scrolling
            topScroll.addEventListener('scroll', function() {
                bottomScroll.scrollLeft = topScroll.scrollLeft;
            });
            
            bottomScroll.addEventListener('scroll', function() {
                topScroll.scrollLeft = bottomScroll.scrollLeft;
            });
        });
    </script>
</body>
</html>