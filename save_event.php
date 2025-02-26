<?php
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "inputs_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = $_POST['rut'];
    $nombre = $_POST['employee'];
    $fecha = $_POST['date'];
    $event_type = $_POST['event_type'];

    $stmt = $conn->prepare("INSERT INTO employee_events (identificador, nombre, fecha, event_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $identificador, $nombre, $fecha, $event_type);
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
    $stmt->close();
}

$conn->close();
?>
