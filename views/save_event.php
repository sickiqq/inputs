<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "inputs_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = $_POST['rut'];
    $nombre = $_POST['employee'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $event_type = $_POST['event_type'];

    $currentDate = $start_date;
    while ($currentDate <= $end_date) {
        $stmt = $conn->prepare("INSERT INTO employee_events (identificador, nombre, fecha, event_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $identificador, $nombre, $currentDate, $event_type);
        $stmt->execute();
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }
    echo "success";
}

$conn->close();
?>
