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

// Eliminar evento de la base de datos
$stmt = $conn->prepare("DELETE FROM employee_events WHERE nombre = ? AND fecha = ? AND identificador = ?");
$stmt->bind_param("sss", $employee, $date, $rut);
$stmt->execute();
$stmt->close();

$conn->close();
echo "Evento eliminado exitosamente";
?>
