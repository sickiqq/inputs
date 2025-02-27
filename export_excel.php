<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Conectar a la base de datos
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "inputs_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Obtener datos del POST
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_termino = $_POST['fecha_termino'];
$formato = $_POST['formato'];

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
function generateAttendanceMatrix($datos, $events, $fecha_inicio, $fecha_termino) {
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
            $attendance[$employee]['days'][$date]['eventColor'] = getEventColor($eventType);
        } else {
            $unlinkedEvents[] = $event;
        }
    }

    ksort($dates);
    ksort($attendance); // Ordenar por nombre del funcionario
    return [$attendance, array_keys($dates), $months, $unlinkedEvents];
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

// Obtener datos según el formato
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
list($attendance, $dates, $months, $unlinkedEvents) = generateAttendanceMatrix($datos, $events, $fecha_inicio, $fecha_termino);

$conn->close();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header
$sheet->setCellValue('A1', 'Funcionario');
$sheet->setCellValue('B1', 'RUT');
$sheet->setCellValue('C1', 'Programa');

$col = 'D';
foreach ($dates as $date) {
    $sheet->setCellValue($col . '1', date('d', strtotime($date)));
    $col++;
}

// Add month row
$sheet->setCellValue('A2', 'Mes');
$col = 'D';
foreach ($dates as $date) {
    $sheet->setCellValue($col . '2', getMonthName($date));
    $col++;
}

// Fill data
$row = 3;
foreach ($attendance as $employee => $info) {
    $sheet->setCellValue('A' . $row, $employee);
    $sheet->setCellValue('B' . $row, $info['rut']);
    $sheet->setCellValue('C' . $row, $info['program']);

    $col = 'D';
    foreach ($dates as $date) {
        $eventColor = isset($info['days'][$date]['event']) ? getEventColor($info['days'][$date]['event']) : '';
        $unlinkedEvent = array_filter($unlinkedEvents, function($event) use ($employee, $date) {
            return $event['nombre'] === $employee && getDateOnly($event['fecha']) === $date;
        });
        $unlinkedEventColor = !empty($unlinkedEvent) ? getEventColor(reset($unlinkedEvent)['event_type']) : '';

        $value = isset($info['days'][$date]) ? 'X' : '';
        $sheet->setCellValue($col . $row, $value);
        if ($eventColor || $unlinkedEventColor) {
            $sheet->getStyle($col . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($eventColor ?: $unlinkedEventColor);
        }
        $col++;
    }
    $row++;
}

// Write to file
$writer = new Xlsx($spreadsheet);
$filename = 'attendance_matrix.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
?>
