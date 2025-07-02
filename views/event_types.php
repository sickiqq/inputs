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
    $action = $_POST['action'];
    $event_type = $_POST['event_type'] ?? null;
    $color_html = $_POST['color_html'] ?? null;
    $id = $_POST['id'] ?? null;

    if ($action === 'create' && $event_type && $color_html) {
        $stmt = $conn->prepare("INSERT INTO event_types (nombre, color_html) VALUES (?, ?)");
        $stmt->bind_param("ss", $event_type, $color_html);
        $stmt->execute();
    } elseif ($action === 'update' && $id && $event_type && $color_html) {
        $stmt = $conn->prepare("UPDATE event_types SET nombre = ?, color_html = ? WHERE id = ?");
        $stmt->bind_param("ssi", $event_type, $color_html, $id);
        $stmt->execute();
    } elseif ($action === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM event_types WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}

$event_types = [];
$result = $conn->query("SELECT * FROM event_types");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $event_types[] = $row;
    }
}

$conn->close();

function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Tipos de Eventos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <a href="../index.php" class="btn btn-secondary mb-4">Volver al Inicio</a>
        <h2>Gestionar Tipos de Eventos</h2>
        <form action="" method="post" class="mb-4">
            <input type="hidden" name="action" value="create">
            <div class="mb-3">
                <label for="event_type" class="form-label">Nuevo Tipo de Evento</label>
                <input type="text" class="form-control" id="event_type" name="event_type" required>
            </div>
            <div class="mb-3">
                <label for="color_html" class="form-label">Color HTML</label>
                <input type="text" class="form-control" id="color_html" name="color_html" required>
            </div>
            <button type="submit" class="btn btn-primary">Agregar</button>
        </form>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Color HTML</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($event_types as $type): ?>
                    <tr>
                        <td><?php echo escape($type['id'] ?? ''); ?></td>
                        <td><?php echo escape($type['nombre'] ?? ''); ?></td>
                        <td><?php echo escape($type['color_html'] ?? ''); ?></td>
                        <td>
                            <form action="" method="post" style="display:inline-block;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo escape($type['id'] ?? ''); ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                            <button class="btn btn-warning btn-sm" onclick="editEventType(<?php echo escape($type['id'] ?? ''); ?>, '<?php echo escape($type['nombre'] ?? ''); ?>', '<?php echo escape($type['color_html'] ?? ''); ?>')">Editar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <form id="editForm" action="" method="post" style="display:none;">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div class="mb-3">
                <label for="editEventType" class="form-label">Editar Tipo de Evento</label>
                <input type="text" class="form-control" id="editEventType" name="event_type" required>
            </div>
            <div class="mb-3">
                <label for="editColorHtml" class="form-label">Color HTML</label>
                <input type="text" class="form-control" id="editColorHtml" name="color_html" required>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    </div>
    <script>
        function editEventType(id, name, color) {
            document.getElementById('editId').value = id;
            document.getElementById('editEventType').value = name;
            document.getElementById('editColorHtml').value = color;
            document.getElementById('editForm').style.display = 'block';
        }
    </script>
</body>
</html>
