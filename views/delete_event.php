<?php
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

    // Función para procesar tabla de datos
    function processDataTable($conn, $table, $employee, $date, $rut, $project) {
        // Modificar la consulta para incluir casos donde fecha_salida es NULL
        $sql = "SELECT * FROM $table WHERE 
                nombre = ? AND 
                identificador = ? AND 
                contrato = ? AND 
                DATE(fecha_entrada) <= ? AND 
                (DATE(fecha_salida) >= ? OR fecha_salida IS NULL)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $employee, $rut, $project, $date, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $entrada = new DateTime($row['fecha_entrada']);
            $deleteDate = new DateTime($date);
            
            // Si no hay fecha_salida, tratar como un registro de un solo día
            if ($row['fecha_salida'] === null) {
                error_log("Eliminando registro sin fecha de salida");
                if ($entrada->format('Y-m-d') === $deleteDate->format('Y-m-d')) {
                    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
                    $stmt->bind_param("i", $row['id']);
                    $stmt->execute();
                }
                return;
            }

            $salida = new DateTime($row['fecha_salida']);
            
            // Debug
            error_log("Entrada: " . $entrada->format('Y-m-d H:i:s'));
            error_log("Salida: " . ($row['fecha_salida'] ? $salida->format('Y-m-d H:i:s') : 'NULL'));
            error_log("Delete Date: " . $deleteDate->format('Y-m-d'));
            
            // Si el rango es solo un día
            if ($entrada->format('Y-m-d') === $salida->format('Y-m-d')) {
                error_log("Eliminando registro de un solo día");
                $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();
                return;
            }
            
            // Si es el primer día del rango
            if ($entrada->format('Y-m-d') === $deleteDate->format('Y-m-d')) {
                error_log("Modificando fecha de entrada");
                $nuevaEntrada = clone $deleteDate;
                $nuevaEntrada->modify('+1 day');
                $nuevaEntrada = $nuevaEntrada->format('Y-m-d') . ' ' . $entrada->format('H:i:s');
                
                $stmt = $conn->prepare("UPDATE $table SET fecha_entrada = ? WHERE id = ?");
                $stmt->bind_param("si", $nuevaEntrada, $row['id']);
                $stmt->execute();
                return;
            }
            
            // Si es el último día del rango
            if ($salida->format('Y-m-d') === $deleteDate->format('Y-m-d')) {
                error_log("Modificando fecha de salida");
                $nuevaSalida = clone $deleteDate;
                $nuevaSalida->modify('-1 day');
                $nuevaSalida = $nuevaSalida->format('Y-m-d') . ' ' . $salida->format('H:i:s');
                
                $stmt = $conn->prepare("UPDATE $table SET fecha_salida = ? WHERE id = ?");
                $stmt->bind_param("si", $nuevaSalida, $row['id']);
                $stmt->execute();
                return;
            }
            
            // Si es un día intermedio
            if ($entrada->format('Y-m-d') < $deleteDate->format('Y-m-d') && 
                $salida->format('Y-m-d') > $deleteDate->format('Y-m-d')) {
                error_log("Dividiendo registro");
                
                // Crear primer registro
                $stmt = $conn->prepare("INSERT INTO $table 
                    (identificador, nombre, contrato, fecha_entrada, fecha_salida, created_at, manual_entry) 
                    VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                
                $nuevaSalida = clone $deleteDate;
                $nuevaSalida->modify('-1 day');
                $nuevaSalida = $nuevaSalida->format('Y-m-d') . ' ' . $salida->format('H:i:s');
                
                $stmt->bind_param("sssssi", 
                    $row['identificador'], 
                    $row['nombre'], 
                    $row['contrato'], 
                    $row['fecha_entrada'], 
                    $nuevaSalida,
                    $row['manual_entry']
                );
                $stmt->execute();

                // Crear segundo registro
                $nuevaEntrada = clone $deleteDate;
                $nuevaEntrada->modify('+1 day');
                $nuevaEntrada = $nuevaEntrada->format('Y-m-d') . ' ' . $entrada->format('H:i:s');
                
                $stmt->bind_param("sssssi", 
                    $row['identificador'], 
                    $row['nombre'], 
                    $row['contrato'], 
                    $nuevaEntrada,
                    $row['fecha_salida'],
                    $row['manual_entry']
                );
                $stmt->execute();

                // Eliminar registro original
                $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    // Procesar data1 y data2
    processDataTable($conn, 'data1', $employee, $date, $rut, $project);
    processDataTable($conn, 'data2', $employee, $date, $rut, $project);

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