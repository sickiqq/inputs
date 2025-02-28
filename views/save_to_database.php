<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "inputs_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => "Conexión fallida: " . $conn->connect_error]);
    exit();
}

$table = $_POST['table'];
$record = $_POST;
unset($record['table']);

$columns = implode(", ", array_keys($record));
$values = implode(", ", array_map(function($value) use ($conn) {
    return "'" . $conn->real_escape_string($value) . "'";
}, array_values($record)));

$sql = "INSERT INTO $table ($columns) VALUES ($values)";
if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => "Error: " . $sql . "<br>" . $conn->error]);
}

$conn->close();
?>
