<?php
require '../vendor/autoload.php';
require '../includes/functions.php'; // Include the shared functions file

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Conectar a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
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

// Obtener datos según el formato
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

$events = getEventsFromDatabase($conn, $fecha_inicio, $fecha_termino);
list($attendance, $dates, $months, $unlinkedEvents) = generateAttendanceMatrix($datos, $events, $fecha_inicio, $fecha_termino, $eventColors);

$conn->close();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header
$sheet->setCellValue('A1', 'Funcionario');
$sheet->setCellValue('B1', 'RUT');
$sheet->setCellValue('C1', 'Proyecto');

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
foreach ($attendance as $key => $info) {
    list($employee, $program) = explode('|', $key); // Use the same key structure
    $sheet->setCellValue('A' . $row, $info['program']); // Swap with program
    $sheet->setCellValue('B' . $row, $info['rut']);
    $sheet->setCellValue('C' . $row, $employee); // Swap with employee

    $col = 'D';
    foreach ($dates as $date) {
        $eventColor = isset($info['days'][$date]['event']) ? getEventColor($info['days'][$date]['event'], $eventColors) : '';
        $unlinkedEvent = array_filter($unlinkedEvents, function($event) use ($employee, $date) {
            return $event['nombre'] === $employee && getDateOnly($event['fecha']) === $date;
        });
        $unlinkedEventColor = !empty($unlinkedEvent) ? getEventColor(reset($unlinkedEvent)['event_type'], $eventColors) : '';

        $value = isset($info['days'][$date]) ? 'X' : '';
        $sheet->setCellValue($col . $row, $value);
        if ($eventColor || $unlinkedEventColor) {
            $sheet->getStyle($col . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(substr($eventColor ?: $unlinkedEventColor, 1));
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
