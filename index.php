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

// Obtener meses y años de los registros
$months = [];
$sql = "SELECT DISTINCT DATE_FORMAT(fecha_entrada, '%M %Y') as monthYear FROM data1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $months[$row['monthYear']] = true;
    }
}

$conn->close();

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Visualizador de Excel</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
        <style>
            .container {
                max-width: 800px;
                margin-top: 50px;
            }
            .chart-container {
                margin-top: 30px;
            }
        </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Carga tus archivos Excel</h3>
            </div>
            <div class="card-body">
                <form action="views/cargar.php" method="post" enctype="multipart/form-data">
                    <div class="mb-3 row">
                        <div class="col-md-6">
                            <label for="formato" class="form-label">Selecciona el formato</label>
                            <select class="form-control" id="formato" name="formato" required>
                                <option value="1">Cordillera</option>
                                <option value="2">Patache</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="archivo" class="form-label">Selecciona archivos Excel (.xlsx, .xls)</label>
                            <input class="form-control" type="file" id="archivo" name="archivos[]" accept=".xlsx, .xls" multiple required>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <p><strong>Nota:</strong> Todos los archivos Excel deben tener el mismo formato:</p>
                        <ul>
                            <li>Primera columna: Categorías o etiquetas (texto)</li>
                            <li>Segunda columna: Valores numéricos</li>
                        </ul>
                    </div>
                    <input type="hidden" id="initial_format" name="initial_format" value="">
                    <div class="button-container">
                        <button type="submit" class="btn btn-primary">Cargar datos</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-body">
                <form action="views/visualizar.php" method="get">
                    <div class="mb-3 row">
                        <div class="col-md-4">
                            <label for="formato" class="form-label">Selecciona el formato</label>
                            <select class="form-control" id="formato" name="formato" required>
                                <option value="1">Cordillera</option>
                                <option value="2">Patache</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="fecha_inicio" class="form-label">Fecha de inicio</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="fecha_termino" class="form-label">Fecha de término</label>
                            <input type="date" class="form-control" id="fecha_termino" name="fecha_termino" value="<?php echo date('Y-m-t'); ?>" required>
                        </div>
                    </div>
                    <div class="button-container">
                        <button type="submit" class="btn btn-success">Visualizar datos</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-body">
                <a href="views/event_types.php" class="btn btn-info">Gestionar Tipos de Eventos</a>
            </div>
        </div>
        <div class="mt-4 row">
            <div class="col-md-6">
                <h4>Registros (Cordillera):</h4>
                <ul>
                    <?php if (isset($months) && !empty($months)): ?>
                        <?php foreach (array_keys($months) as $monthYear): ?>
                            <li><?php echo escape($monthYear); ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No hay registros disponibles.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-md-6">
                <h4>Registros (Patache):</h4>
                <ul>
                    <?php
                    // Conectar a la base de datos nuevamente para obtener los registros de Patache
                    $conn = new mysqli($servername, $username, $password, $dbname);
                    if ($conn->connect_error) {
                        die("Conexión fallida: " . $conn->connect_error);
                    }

                    $months2 = [];
                    $sql2 = "SELECT DISTINCT DATE_FORMAT(fecha_entrada, '%M %Y') as monthYear FROM data2";
                    $result2 = $conn->query($sql2);

                    if ($result2->num_rows > 0) {
                        while ($row2 = $result2->fetch_assoc()) {
                            $months2[$row2['monthYear']] = true;
                        }
                    }

                    $conn->close();
                    ?>

                    <?php if (isset($months2) && !empty($months2)): ?>
                        <?php foreach (array_keys($months2) as $monthYear2): ?>
                            <li><?php echo escape($monthYear2); ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No hay registros disponibles.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
