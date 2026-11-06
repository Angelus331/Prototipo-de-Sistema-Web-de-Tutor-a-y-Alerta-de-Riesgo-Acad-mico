<?php
session_start();

/* ============================================================
   RF05: GENERACIÓN DE REPORTES E HISTORIAL DE TUTORÍA
   Componente: Gestión de Reportes e Historial de Tutoría
   ============================================================ */

interface ProveedorRiesgoAcademico {
    public function obtenerNivelRiesgo($codigo, $periodo);
}

class RiesgoAcademicoMock implements ProveedorRiesgoAcademico {
    private $niveles = [
        '2021015' => 'verde',
        '2021089' => 'amarillo',
        '2021102' => 'naranja',
        '2021204' => 'amarillo',
        '2021305' => 'verde',
    ];

    public function obtenerNivelRiesgo($codigo, $periodo) {
        // En el sistema real este servicio lo proporciona el componente
        // Gestión de Riesgo Académico (Monitoreo y Alerta Temprana).
        return $this->niveles[$codigo] ?? null;
    }
}

class GestorReportesTutoria {
    private $fichas = [];
    private $errores = [];
    private $proveedorRiesgo;

    private $nombresEstudiantes = [
        '2021015' => 'Ana Torres Quispe',
        '2021089' => 'Luis Mamani Flores',
        '2021102' => 'Rosa Condori Apaza',
        '2021204' => 'Jorge Huanca Ramos',
        '2021305' => 'María Chura Lima',
    ];

    private $asignaciones = [
        '2021015' => ['Dr. Carlos Pérez'],
        '2021089' => ['Dr. Carlos Pérez', 'Dra. María Gómez'],
        '2021102' => ['Dra. María Gómez'],
        '2021204' => ['Ing. Luis Quispe'],
        '2021305' => ['Ing. Luis Quispe'],
    ];

    public function __construct(ProveedorRiesgoAcademico $proveedorRiesgo, $fichasIniciales = []) {
        $this->proveedorRiesgo = $proveedorRiesgo;
        $this->fichas = $fichasIniciales;
    }

    public function generarMetricas($estudiantes, $periodo) {
        $this->errores = [];
        $periodo = trim($periodo);

        if ($periodo === '') {
            $this->errores[] = 'El periodo académico es obligatorio.';
            return null;
        }
        if (count($estudiantes) === 0) {
            $this->errores[] = 'No hay estudiantes para generar el reporte.';
            return null;
        }

        $conteo = ['verde' => 0, 'amarillo' => 0, 'naranja' => 0, 'sin_dato' => 0];
        foreach ($estudiantes as $codigo) {
            $nivel = $this->proveedorRiesgo->obtenerNivelRiesgo($codigo, $periodo);
            if (!in_array($nivel, ['verde', 'amarillo', 'naranja'], true)) {
                $nivel = 'sin_dato';
            }
            $conteo[$nivel]++;
        }

        $total = count($estudiantes);
        $metricas = [];
        foreach ($conteo as $nivel => $cantidad) {
            $metricas[$nivel] = [
                'cantidad' => $cantidad,
                'porcentaje' => round(($cantidad * 100) / $total, 1),
            ];
        }

        return ['periodo' => $periodo, 'total' => $total, 'metricas' => $metricas];
    }

    public function registrarFicha($datos) {
        $this->errores = [];
        $codigo = trim($datos['codigo'] ?? '');
        $tutor = trim($datos['tutor'] ?? '');
        $fecha = trim($datos['fecha'] ?? '');
        $motivo = trim($datos['motivo'] ?? '');
        $acuerdos = trim($datos['acuerdos'] ?? '');

        if ($codigo === '' || $tutor === '') {
            $this->errores[] = 'Debe seleccionar el estudiante y el tutor responsable.';
        }
        if ($motivo === '') {
            $this->errores[] = 'El motivo de la tutoría es obligatorio.';
        }
        if (!$this->fechaValida($fecha)) {
            $this->errores[] = 'La fecha no es válida o es futura.';
        }
        if ($codigo !== '' && $tutor !== '') {
            $asignados = $this->asignaciones[$codigo] ?? [];
            if (!in_array($tutor, $asignados, true)) {
                $this->errores[] = 'El estudiante no está asignado a este tutor.';
            }
        }

        if (!empty($this->errores)) {
            return false;
        }

        $nuevoId = count($this->fichas) > 0 ? max(array_column($this->fichas, 'id')) + 1 : 1;
        $nivel = $this->proveedorRiesgo->obtenerNivelRiesgo($codigo, '2026-II');
        if (!in_array($nivel, ['verde', 'amarillo', 'naranja'], true)) {
            $nivel = 'sin_dato';
        }

        $this->fichas[] = [
            'id' => $nuevoId,
            'codigo' => $codigo,
            'estudiante' => $this->nombresEstudiantes[$codigo] ?? $codigo,
            'tutor' => $tutor,
            'fecha' => $fecha,
            'motivo' => $motivo,
            'acuerdos' => $acuerdos !== '' ? $acuerdos : 'Sin acuerdos registrados.',
            'riesgo' => $nivel,
            'estado' => 'Registrada',
        ];

        return true;
    }

    public function listarFichasPorEstudiante($codigo) {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return $this->fichas;
        }
        return array_values(array_filter($this->fichas, function ($ficha) use ($codigo) {
            return $ficha['codigo'] === $codigo;
        }));
    }

    private function fechaValida($fecha) {
        $fechaObj = DateTime::createFromFormat('!Y-m-d', $fecha);
        return $fechaObj
            && $fechaObj->format('Y-m-d') === $fecha
            && $fechaObj <= new DateTime('today');
    }

    public function getFichas() { return $this->fichas; }
    public function getErrores() { return $this->errores; }
    public function getNombresEstudiantes() { return $this->nombresEstudiantes; }
    public function getAsignaciones() { return $this->asignaciones; }
}

// Historial persistente en sesión (simula la base de datos del prototipo)
if (!isset($_SESSION['fichas_tutoria'])) {
    $_SESSION['fichas_tutoria'] = [
        [
            'id' => 1,
            'codigo' => '2021089',
            'estudiante' => 'Luis Mamani Flores',
            'tutor' => 'Dra. María Gómez',
            'fecha' => '2026-11-04',
            'motivo' => 'Bajo rendimiento en Cálculo I',
            'acuerdos' => 'Plan de estudio y seguimiento semanal.',
            'riesgo' => 'amarillo',
            'estado' => 'Registrada',
        ],
    ];
}

$proveedorRiesgo = new RiesgoAcademicoMock();
$gestor = new GestorReportesTutoria($proveedorRiesgo, $_SESSION['fichas_tutoria']);
$nombresEstudiantes = $gestor->getNombresEstudiantes();
$asignaciones = $gestor->getAsignaciones();

$periodo = trim($_POST['periodo'] ?? '2026-II');
$metricas = $gestor->generarMetricas(array_keys($nombresEstudiantes), $periodo);
$erroresReporte = $metricas === null ? $gestor->getErrores() : [];

$mensajeExito = '';
$erroresFicha = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['accion'] ?? '') === 'registrar_ficha') {
    if ($gestor->registrarFicha($_POST)) {
        $_SESSION['fichas_tutoria'] = $gestor->getFichas();
        $mensajeExito = 'Ficha de tutoría registrada correctamente.';
    } else {
        $erroresFicha = $gestor->getErrores();
    }
}

$filtroCodigo = trim($_GET['codigo'] ?? '');
$fichasMostradas = $gestor->listarFichasPorEstudiante($filtroCodigo);

$tutores = array_values(array_unique(array_merge(...array_values($asignaciones))));
sort($tutores);

$etiquetasRiesgo = [
    'verde' => 'Bajo',
    'amarillo' => 'Medio',
    'naranja' => 'Alto',
    'sin_dato' => 'Sin dato',
];
$clasesRiesgo = [
    'verde' => 'badge-safe',
    'amarillo' => 'badge-warning',
    'naranja' => 'badge-risk',
    'sin_dato' => 'badge-secondary',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes e Historial de Tutoría - UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        .badge-safe { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .badge-warning { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
        .badge-risk { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .badge-secondary { background-color: #e2e3e5; color: #41464b; border: 1px solid #d3d6d8; }
        .table thead th { font-size: 0.78rem; letter-spacing: 0.03em; }
    </style>
</head>
<body class="py-4">

<div class="container">
    <header class="mb-4 text-center">
        <h2 class="fw-bold text-primary">
            <i class="bi bi-graph-up-arrow me-2"></i>Sistema Web de Tutoría y Alerta Temprana
        </h2>
        <p class="text-secondary mb-0">RF05: Generación de Reportes e Historial de Tutoría</p>
    </header>

    <!-- Métricas institucionales de riesgo -->
    <div class="card card-custom p-4 bg-white mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-bar-chart-fill me-2 text-primary"></i>Métricas institucionales de riesgo
            </h5>
            <span class="small text-muted">Datos obtenidos de Gestión de Riesgo Académico</span>
        </div>

        <form method="POST" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="accion" value="generar_reporte">
            <div class="col-sm-5">
                <label class="form-label small text-muted fw-semibold">Periodo académico</label>
                <input type="text" name="periodo" class="form-control"
                    value="<?= htmlspecialchars($periodo) ?>" placeholder="Ej. 2026-II">
            </div>
            <div class="col-sm-4">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-arrow-repeat me-1"></i>Generar reporte
                </button>
            </div>
        </form>

        <?php if (!empty($erroresReporte)): ?>
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <ul class="mb-0 ps-3">
                    <?php foreach ($erroresReporte as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($metricas !== null): ?>
            <div class="row g-3">
                <?php foreach (['verde', 'amarillo', 'naranja', 'sin_dato'] as $nivel): ?>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 text-center bg-light h-100">
                            <span class="badge <?= $clasesRiesgo[$nivel] ?> mb-2">
                                <?= $etiquetasRiesgo[$nivel] ?>
                            </span>
                            <span class="d-block h4 fw-bold mb-0">
                                <?= $metricas['metricas'][$nivel]['cantidad'] ?>
                                <small class="text-muted fs-6">
                                    (<?= $metricas['metricas'][$nivel]['porcentaje'] ?>%)
                                </small>
                            </span>
                            <span class="small text-muted">estudiantes</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="small text-muted mt-3 mb-0">
                Periodo <strong><?= htmlspecialchars($metricas['periodo']) ?></strong>
                &middot; Total evaluado: <strong><?= $metricas['total'] ?></strong> estudiantes
            </p>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <!-- Historial de fichas de tutoría -->
        <div class="col-lg-7">
            <div class="card card-custom p-4 bg-white h-100">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-journal-check me-2 text-success"></i>Historial de fichas de tutoría
                </h5>

                <form method="GET" class="row g-2 mb-3">
                    <div class="col-sm-7">
                        <select name="codigo" class="form-select">
                            <option value="">Todos los estudiantes</option>
                            <?php foreach ($nombresEstudiantes as $codigo => $nombre): ?>
                                <option value="<?= htmlspecialchars($codigo) ?>"
                                    <?= $filtroCodigo === $codigo ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($codigo . ' - ' . $nombre) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-5 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-secondary w-100">Filtrar</button>
                        <a href="GeneracionReportesHistorialTutoria.php"
                            class="btn btn-light border w-100">Limpiar</a>
                    </div>
                </form>

                <?php if (empty($fichasMostradas)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        <p class="mb-0">No hay fichas de tutoría registradas para el filtro seleccionado.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Estudiante</th>
                                    <th>Tutor</th>
                                    <th>Fecha</th>
                                    <th>Motivo</th>
                                    <th>Riesgo</th>
                                    <th>Acuerdos</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($fichasMostradas as $ficha): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= $ficha['id'] ?></td>
                                        <td>
                                            <span class="fw-semibold d-block"><?= htmlspecialchars($ficha['estudiante']) ?></span>
                                            <span class="text-muted font-monospace"><?= htmlspecialchars($ficha['codigo']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($ficha['tutor']) ?></td>
                                        <td class="font-monospace"><?= htmlspecialchars($ficha['fecha']) ?></td>
                                        <td><?= htmlspecialchars($ficha['motivo']) ?></td>
                                        <td>
                                            <span class="badge <?= $clasesRiesgo[$ficha['riesgo']] ?? 'badge-secondary' ?>">
                                                <?= $etiquetasRiesgo[$ficha['riesgo']] ?? 'Sin dato' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($ficha['acuerdos']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Registro de ficha de tutoría -->
        <div class="col-lg-5">
            <div class="card card-custom p-4 bg-white h-100">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-pencil-square me-2 text-primary"></i>Registrar ficha de tutoría
                </h5>

                <?php if (!empty($mensajeExito)): ?>
                    <div class="alert alert-success py-2 px-3 small mb-3">
                        <i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($mensajeExito) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($erroresFicha)): ?>
                    <div class="alert alert-danger py-2 px-3 small mb-3">
                        <h6 class="fw-bold mb-1">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Datos rechazados:
                        </h6>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($erroresFicha as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="accion" value="registrar_ficha">

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Estudiante</label>
                        <select name="codigo" class="form-select" required>
                            <option value="">-- Seleccione un estudiante --</option>
                            <?php foreach ($nombresEstudiantes as $codigo => $nombre): ?>
                                <option value="<?= htmlspecialchars($codigo) ?>"
                                    <?= (($_POST['codigo'] ?? '') === $codigo) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($codigo . ' - ' . $nombre) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Tutor responsable</label>
                        <select name="tutor" class="form-select" required>
                            <option value="">-- Seleccione un tutor --</option>
                            <?php foreach ($tutores as $tutor): ?>
                                <option value="<?= htmlspecialchars($tutor) ?>"
                                    <?= (($_POST['tutor'] ?? '') === $tutor) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tutor) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Fecha de la sesión</label>
                        <input type="date" name="fecha" class="form-control"
                            max="<?= date('Y-m-d') ?>"
                            value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Motivo de la tutoría</label>
                        <textarea name="motivo" class="form-control" rows="3"
                            placeholder="Ej. Bajo rendimiento en Cálculo I" required><?= htmlspecialchars($_POST['motivo'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Acuerdos de la sesión</label>
                        <textarea name="acuerdos" class="form-control" rows="3"
                            placeholder="Opcional: compromisos y seguimiento"><?= htmlspecialchars($_POST['acuerdos'] ?? '') ?></textarea>
                    </div>

                    <div class="alert alert-info py-2 px-3 small d-flex align-items-center mb-3">
                        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                        <span>Validación activa: fecha no futura, motivo obligatorio y tutor asignado al estudiante.</span>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-save me-1"></i>Registrar ficha
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
