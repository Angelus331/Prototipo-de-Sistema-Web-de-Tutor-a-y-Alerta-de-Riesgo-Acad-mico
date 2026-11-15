<?php
// SERVICIO / INTERFAZ DE COMUNICACIÓN ENTRE COMPONENTES
interface INotificacion {
    public function enviarNotificacion(string $codigoEstudiante, float $nota, float $asistencia): array;
}

// COMPONENTE B: Componente Notificaciones (Proveedor del Servicio)
class GestorNotificaciones implements INotificacion {
    public function enviarNotificacion(string $codigoEstudiante, float $nota, float $asistencia): array {
        // Validación en Componente B: verificar código válido para generar correo institucional
        if (!preg_match('/^[0-9]{7}$/', $codigoEstudiante)) {
            return [
                'exito' => false,
                'mensaje_respuesta' => "Error de envío: Código '$codigoEstudiante' sin correo institucional válido."
            ];
        }

        $correoAlumno = $codigoEstudiante . "@unap.edu.pe";
        $motivo = [];
        if ($nota < 11.0) $motivo[] = "Nota baja ($nota)";
        if ($asistencia < 85.0) $motivo[] = "Asistencia crítica ($asistencia%)";
        $detalleMotivo = implode(" y ", $motivo);

        // RESPUESTA DEL COMPONENTE B HACIA EL COMPONENTE A
        return [
            'exito' => true,
            'correo_destino' => $correoAlumno,
            'alerta_tutor' => 'Panel del Tutor actualizado',
            'mensaje_respuesta' => "Alerta enviada a $correoAlumno y al Tutor ($detalleMotivo)."
        ];
    }
}

// COMPONENTE A: Componente DetecciónDeRiesgo (Carga Masiva + Evaluador)
class GestorCargaMasiva {
    private $registros = [];
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

            // Validación de negocio: Notas entre 0 y 20
            if ($nota < 0 || $nota > 20) {
                $this->errores[] = "Fila " . ($num_fila + 2) . ": El alumno <strong>$codigo</strong> tiene una nota inválida ($nota). Debe estar entre 0 y 20.";
                continue;
            }

            $this->registros[] = [
                'codigo' => htmlspecialchars($codigo),
                'nota' => $nota,
                'asistencia' => $asistencia
            ];
        }
    }

    public function getRegistros() { return $this->registros; }
    public function getErrores() { return $this->errores; }
}

class EvaluadorRiesgoAcademico {
    private INotificacion $servicioNotificacion;

    // Inyección del Servicio que conecta Componente A con Componente B
    public function __construct(INotificacion $servicioNotificacion) {
        $this->servicioNotificacion = $servicioNotificacion;
    }

    public function evaluarYNotificar(array $estudiante): array {
        // Evaluación de umbral crítico RF01 (Nota < 11 o Asistencia < 85%)
        $enRiesgo = ($estudiante['nota'] < 11.0 || $estudiante['asistencia'] < 85.0);
        $estudiante['riesgo'] = $enRiesgo;

        if ($enRiesgo) {
            // FLUJO DE INTEGRACIÓN: COMPONENTE A -> SERVICIO -> COMPONENTE B -> RESPUESTA
            $respuestaComponenteB = $this->servicioNotificacion->enviarNotificacion(
                $estudiante['codigo'],
                $estudiante['nota'],
                $estudiante['asistencia']
            );
            $estudiante['notificacion'] = $respuestaComponenteB;
        } else {
            $estudiante['notificacion'] = [
                'exito' => true,
                'mensaje_respuesta' => 'Sin alerta (Alumno regular)'
            ];
        }

        return $estudiante;
    }
}

// EJECUCIÓN DE LA FUNCIONALIDAD INTEGRADA
$gestorCarga = new GestorCargaMasiva();
$componenteNotificaciones = new GestorNotificaciones(); // Componente B
$evaluadorRiesgo = new EvaluadorRiesgoAcademico($componenteNotificaciones); // Componente A integrado
$procesado = false;
$lista_final = [];

$datos_default = "Codigo,Nota,Asistencia\n2021015,14.5,90\n2021089,08.0,80\n2021102,25.0,100\n2021204,10.5,75\n2021305,16.0,95\n2023122,20.1,96";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenido = '';
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
        $contenido = file_get_contents($_FILES['archivo_csv']['tmp_name']);
    } elseif (!empty($_POST['csv_texto'])) {
        $contenido = $_POST['csv_texto'];
    }

    if (!empty($contenido)) {
        // 1. Componente A carga y valida el archivo CSV
        $gestorCarga->procesarCSV($contenido);
        
        // 2. Componente A evalúa riesgo e invoca al Componente B para emitir alertas
        foreach ($gestorCarga->getRegistros() as $est) {
            $lista_final[] = $evaluadorRiesgo->evaluarYNotificar($est);
        }
        $procesado = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integración RF01: Carga Masiva y Notificaciones</title>
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
    <header class="mb-4 text-center">
        <h2 class="fw-bold text-primary"><i class="bi bi-mortarboard-fill me-2"></i>Sistema Web de Tutoría y Alerta Temprana</h2>
        <p class="text-secondary">Integración RF01: Componente DetecciónDeRiesgo (Carga Masiva) ↔ Componente Notificaciones</p>
    </header>

    <div class="row g-4">
        <div class="col-lg-4">
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
                        <span>Validación: Notas entre 0 y 20. Alerta automática si Nota &lt; 11 o Asistencia &lt; 85%.</span>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-play-circle me-1"></i> Procesar e Integrar Alertas
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-custom p-4 bg-white h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-clipboard2-data-fill me-2 text-success"></i>Estado del Procesamiento e Integración</h5>

                <?php if (!$procesado): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        <p class="mb-0">Haga clic en <strong>"Procesar e Integrar Alertas"</strong> para validar el CSV y disparar notificaciones.</p>
                    </div>
                <?php else: ?>
                    <?php $errores = $gestorCarga->getErrores(); ?>

                    <?php if (!empty($errores)): ?>
                        <div class="alert alert-warning py-2 mb-3 small">
                            <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Notificaciones de Validación (Rechazados):</h6>
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
                                <span class="d-block small text-muted">Registros Validados (Comp. A)</span>
                                <span class="h4 fw-bold text-success mb-0"><?= count($lista_final) ?></span>
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
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>CÓDIGO</th>
                                    <th>NOTA</th>
                                    <th>ASISTENCIA</th>
                                    <th>ESTADO (COMP. A)</th>
                                    <th>RESPUESTA NOTIFICACIÓN (COMP. B)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista_final as $est): ?>
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
                                        <td>
                                            <?php if ($est['riesgo'] && $est['notificacion']['exito']): ?>
                                                <span class="text-danger fw-semibold"><i class="bi bi-envelope-check-fill me-1"></i><?= $est['notificacion']['mensaje_respuesta'] ?></span>
                                            <?php elseif ($est['riesgo'] && !$est['notificacion']['exito']): ?>
                                                <span class="text-warning fw-semibold"><i class="bi bi-exclamation-octagon-fill me-1"></i><?= $est['notificacion']['mensaje_respuesta'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="bi bi-dash-circle me-1"></i><?= $est['notificacion']['mensaje_respuesta'] ?></span>
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