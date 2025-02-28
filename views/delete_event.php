<?php
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
$employee = $_POST['employee'];
$date = $_POST['date'];
$rut = $_POST['rut'];
$project = $_POST['project']; // Nuevo parámetro

// Iniciar transacción
$conn->begin_transaction();

try {
    // Eliminar evento de employee_events (sin proyecto)
    $stmt = $conn->prepare("DELETE FROM employee_events WHERE nombre = ? AND fecha = ? AND identificador = ?");
    $stmt->bind_param("sss", $employee, $date, $rut);
    $stmt->execute();
    $stmt->close();

    // Eliminar de data1 (con proyecto)
    $stmt = $conn->prepare("DELETE FROM data1 WHERE nombre = ? AND DATE(fecha_entrada) = ? AND identificador = ? AND contrato = ?");
    $stmt->bind_param("ssss", $employee, $date, $rut, $project);
    $stmt->execute();
    $stmt->close();

    // Eliminar de data2 (con proyecto)
    $stmt = $conn->prepare("DELETE FROM data2 WHERE nombre = ? AND DATE(fecha_entrada) = ? AND identificador = ? AND contrato = ?");
    $stmt->bind_param("ssss", $employee, $date, $rut, $project);
    $stmt->execute();
    $stmt->close();

    // Confirmar la transacción
    $conn->commit();
    echo "Evento eliminado exitosamente";
} catch (Exception $e) {
    // Si hay error, revertir los cambios
    $conn->rollback();
    echo "Error al eliminar el evento: " . $e->getMessage();
}

$conn->close();
?>