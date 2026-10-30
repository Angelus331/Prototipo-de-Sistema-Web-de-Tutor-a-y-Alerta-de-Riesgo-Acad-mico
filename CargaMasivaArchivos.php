<?php
class GestorCargaMasiva {
    private $bd_estudiantes = [];
    private $errores = [];

    public function procesarCSV($contenido_csv) {
        $lineas = explode("\n", trim($contenido_csv));
        if (empty($lineas)) return;

        $cabecera = str_getcsv(array_shift($lineas));

        foreach ($lineas as $num_fila => $linea) {
            $linea = trim($linea);
            if (empty($linea)) continue;

            $fila = str_getcsv($linea);
            if (count($fila) < 3) {
                $this->errores[] = "Fila " . ($num_fila + 2) . ": Formato incompleto o columnas insuficientes.";
                continue;
            }

            $codigo = trim($fila[0]);
            $nota = (float) trim($fila[1]);
            $asistencia = (float) trim($fila[2]);

            if ($nota < 0 || $nota > 20) {
                $this->errores[] = "Fila " . ($num_fila + 2) . ": El alumno <strong>$codigo</strong> tiene una nota inválida ($nota). Debe estar entre 0 y 20.";
                continue;
            }

            $enRiesgo = ($nota < 11.0 || $asistencia < 85.0);
            
            $this->bd_estudiantes[] = [
                'codigo' => htmlspecialchars($codigo),
                'nota' => $nota,
                'asistencia' => $asistencia,
                'riesgo' => $enRiesgo
            ];
        }
    }

    public function getEstudiantes() { return $this->bd_estudiantes; }
    public function getErrores() { return $this->errores; }
}

$gestor = new GestorCargaMasiva();
$procesado = false;

$datos_default = "Codigo,Nota,Asistencia\n2021015,14.5,90\n2021089,08.0,80\n2021102,25.0,100\n2021204,10.5,75\n2021305,16.0,95";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenido = '';
    
    // Si se subió un archivo físico .csv
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
        $contenido = file_get_contents($_FILES['archivo_csv']['tmp_name']);
    } 
    // Si se pegó texto en el formulario
    elseif (!empty($_POST['csv_texto'])) {
        $contenido = $_POST['csv_texto'];
    }

    if (!empty($contenido)) {
        $gestor->procesarCSV($contenido);
        $procesado = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Carga Masiva - Monitoreo Académico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .badge-risk { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .badge-safe { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    </style>
</head>
<body class="py-4">

<div class="container">
    <!-- Encabezado del Sistema -->
    <header class="mb-4 text-center">
        <h2 class="fw-bold text-primary"><i class="bi bi-mortarboard-fill me-2"></i>Sistema Web de Tutoría y Alerta Temprana</h2>
        <p class="text-secondary">RF01: Carga Masiva de Datos Académicos y Detección de Umbral Crítico</p>
    </header>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card card-custom p-4 bg-white h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-cloud-arrow-up-fill me-2 text-primary"></i>Ingreso de Datos Masivos</h5>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Opción 1: Seleccionar archivo CSV</label>
                        <input class="form-control" type="file" name="archivo_csv" accept=".csv, .txt">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Opción 2: Pegar contenido CSV directamente</label>
                        <textarea class="form-control font-monospace" name="csv_texto" rows="7" style="font-size: 0.85rem;"><?= htmlspecialchars($_POST['csv_texto'] ?? $datos_default) ?></textarea>
                    </div>

                    <div class="alert alert-info py-2 px-3 small d-flex align-items-center mb-3">
                        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                        <span>Validación activa: Calificaciones permitidas entre 0 y 20 puntos.</span>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-play-circle me-1"></i> Procesar Carga Masiva
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-custom p-4 bg-white h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-clipboard2-data-fill me-2 text-success"></i>Estado del Procesamiento</h5>

                <?php if (!$procesado): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        <p class="mb-0">Haga clic en <strong>"Procesar Carga Masiva"</strong> para ejecutar la validación y almacenar los registros.</p>
                    </div>
                <?php else: ?>
                    <?php 
                        $lista = $gestor->getEstudiantes();
                        $errores = $gestor->getErrores();
                    ?>

                    <?php if (!empty($errores)): ?>
                        <div class="alert alert-warning py-2 mb-3 small">
                            <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Notificaciones de Validación:</h6>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errores as $err): ?>
                                    <li><?= $err ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <div class="border rounded p-2 text-center bg-light">
                                <span class="d-block small text-muted">Registros Validados</span>
                                <span class="h4 fw-bold text-success mb-0"><?= count($lista) ?></span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-2 text-center bg-light">
                                <span class="d-block small text-muted">Rechazados por Inconsistencia</span>
                                <span class="h4 fw-bold text-danger mb-0"><?= count($errores) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>CÓDIGO</th>
                                    <th>NOTA</th>
                                    <th>ASISTENCIA</th>
                                    <th>ESTADO PREVENTIVO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista as $est): ?>
                                    <tr>
                                        <td class="fw-bold font-monospace"><?= $est['codigo'] ?></td>
                                        <td>
                                            <span class="fw-semibold <?= ($est['nota'] < 11) ? 'text-danger' : 'text-dark' ?>">
                                                <?= number_format($est['nota'], 1) ?>
                                            </span>
                                        </td>
                                        <td><?= $est['asistencia'] ?>%</td>
                                        <td>
                                            <?php if ($est['riesgo']): ?>
                                                <span class="badge badge-risk px-2 py-1"><i class="bi bi-flag-fill me-1"></i>Riesgo Detectado</span>
                                            <?php else: ?>
                                                <span class="badge badge-safe px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Regular</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>