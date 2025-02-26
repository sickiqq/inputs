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
                <form action="cargar.php" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="archivo" class="form-label">Selecciona archivos Excel (.xlsx, .xls)</label>
                        <input class="form-control" type="file" id="archivo" name="archivos[]" accept=".xlsx, .xls" multiple required>
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
                        <a href="visualizar.php" class="btn btn-success">Visualizar información</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="mt-4">
            <h4>Meses y años de los registros:</h4>
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
    </div>
</body>
</html>
