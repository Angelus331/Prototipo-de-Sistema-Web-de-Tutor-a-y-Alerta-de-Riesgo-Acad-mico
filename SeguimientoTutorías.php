<?php

declare(strict_types=1);
date_default_timezone_set('America/Lima');
session_start();

// Escapa texto antes de imprimirlo en HTML.
function escapar($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Datos simulados: todos los estudiantes de esta lista estan asignados al tutor de prueba.
// Los niveles de riesgo son ilustrativos; una futura integracion los consultara al RF01.
$tutorActual = ['id' => 'TUT001', 'nombre' => 'Tutor academico (Name)'];
$estudiantesAsignados = [
    '2021089' => ['nombre' => 'alumno 1', 'riesgo' => 'Alto'],
    '2021204' => ['nombre' => 'alumno 2', 'riesgo' => 'Medio'],
    '2021305' => ['nombre' => 'alumno 3', 'riesgo' => 'Bajo'],
];

/** clase de registro de seguimiento de una sesion de tutoria. */
class SesionTutoria
{
    private $id;
    private $tutorId;
    private $codigoEstudiante;
    private $fecha;
    private $observaciones;
    private $compromisos;

    public function __construct(
        string $tutorId,
        string $codigoEstudiante,
        string $fecha,
        string $observaciones,
        string $compromisos
    ) {
        $this->id = bin2hex(random_bytes(6));
        $this->tutorId = $tutorId;
        $this->codigoEstudiante = $codigoEstudiante;
        $this->fecha = $fecha;
        $this->observaciones = $observaciones;
        $this->compromisos = $compromisos;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tutor_id' => $this->tutorId,
            'codigo_estudiante' => $this->codigoEstudiante,
            'fecha' => $this->fecha,
            'observaciones' => $this->observaciones,
            'compromisos' => $this->compromisos,
            'registrado_en' => date('Y-m-d H:i:s'),
        ];
    }
}

/** Almacena y recupera temporalmente los seguimientos mediante $_SESSION. */
class SeguimientoRepository
{
    public function __construct()
    {
        if (!isset($_SESSION['rf04_seguimientos'])) {
            $_SESSION['rf04_seguimientos'] = [];
        }
    }

    public function guardar(array $registro): void
    {
        $_SESSION['rf04_seguimientos'][] = $registro;
    }

    public function obtenerPorTutor(string $tutorId): array
    {
        return array_values(array_filter(
            $_SESSION['rf04_seguimientos'],
            static function (array $registro) use ($tutorId): bool {
                return $registro['tutor_id'] === $tutorId;
            }
        ));
    }
}

/** Gestiona exclusivamente las reglas del seguimiento de tutorias (RF04). */
class SeguimientoService
{
    private $repositorio;
    private $estudiantesAsignados;

    public function __construct(SeguimientoRepository $repositorio, array $estudiantesAsignados)
    {
        $this->repositorio = $repositorio;
        $this->estudiantesAsignados = $estudiantesAsignados;
    }

    //verificar  datos antes de guardar
    public function validarDatosSesion(array $datos): array
    {
        $errores = [];
        $codigo = trim((string) ($datos['codigo_estudiante'] ?? ''));
        $fecha = trim((string) ($datos['fecha'] ?? ''));
        $observaciones = trim((string) ($datos['observaciones'] ?? ''));
        $compromisos = trim((string) ($datos['compromisos'] ?? ''));

        if ($codigo === '' || !array_key_exists($codigo, $this->estudiantesAsignados)) {
            $errores[] = 'Seleccione un estudiante asignado a este tutor.';
        }

        if ($fecha === '') {
            $errores[] = 'La fecha de la sesion es obligatoria.';
        } else {
            $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if ($fechaValida === false || $fechaValida->format('Y-m-d') !== $fecha) {
                $errores[] = 'Ingrese una fecha valida.';
            } elseif ($fechaValida > new DateTimeImmutable('today')) {
                $errores[] = 'No se puede registrar como realizada una sesion futura.';
            }
        }

        if ($observaciones === '') {
            $errores[] = 'Ingrese las notas de evolucion u observaciones.';
        } elseif (strlen($observaciones) > 3000) {
            $errores[] = 'Las observaciones son demasiado extensas (maximo 3000 bytes).';
        }

        if ($compromisos === '') {
            $errores[] = 'Ingrese los compromisos establecidos.';
        } elseif (strlen($compromisos) > 1500) {
            $errores[] = 'Los compromisos son demasiado extensos (maximo 1500 bytes).';
        }

        return $errores;
    }

    //registrar una sesion solo cuando  los datos cumplen las validaciones.
    public function registrarSesion(string $tutorId, array $datos): array
    {
        $errores = $this->validarDatosSesion($datos);
        if ($errores !== []) {
            return ['correcto' => false, 'errores' => $errores];
        }

        $sesion = new SesionTutoria(
            $tutorId,
            trim((string) $datos['codigo_estudiante']),
            trim((string) $datos['fecha']),
            trim((string) $datos['observaciones']),
            trim((string) $datos['compromisos'])
        );
        $this->repositorio->guardar($sesion->toArray());
        return ['correcto' => true, 'errores' => []];
    }

    //consulta los registros del tutor actual.
    public function consultarHistorial(string $tutorId, string $codigoEstudiante = ''): array
    {
        $registros = $this->repositorio->obtenerPorTutor($tutorId);
        if ($codigoEstudiante !== '' && isset($this->estudiantesAsignados[$codigoEstudiante])) {
            $registros = array_values(array_filter(
                $registros,
                static function (array $registro) use ($codigoEstudiante): bool {
                    return $registro['codigo_estudiante'] === $codigoEstudiante;
                }
            ));
        }
        return array_reverse($registros); 
    }
}

$servicio = new SeguimientoService(new SeguimientoRepository(), $estudiantesAsignados);

// Token sencillo contra envios de formularios no autorizados (CSRF).
if (!isset($_SESSION['rf04_token'])) {
    $_SESSION['rf04_token'] = bin2hex(random_bytes(16));
}

$mensajeExito = $_SESSION['rf04_exito'] ?? '';
unset($_SESSION['rf04_exito']);
$errores = [];
$formulario = [
    'codigo_estudiante' => '',
    'fecha' => date('Y-m-d'),
    'observaciones' => '',
    'compromisos' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formulario as $campo => $valor) {
        $formulario[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    if (!hash_equals($_SESSION['rf04_token'], (string) ($_POST['rf04_token'] ?? ''))) {
        $errores[] = 'La solicitud no es valida. Actualice la pagina y vuelva a intentarlo.';
    } else {
        $resultado = $servicio->registrarSesion($tutorActual['id'], $formulario);
        if ($resultado['correcto']) {
            $_SESSION['rf04_exito'] = 'Seguimiento registrado correctamente en el historial.';
            // Evita registrar dos veces si se actualiza el navegador.
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        $errores = $resultado['errores'];
    }
}

$filtro = trim((string) ($_GET['estudiante'] ?? ''));
if ($filtro !== '' && !isset($estudiantesAsignados[$filtro])) {
    $filtro = '';
}
$historial = $servicio->consultarHistorial($tutorActual['id'], $filtro);
$totalRegistros = count($servicio->consultarHistorial($tutorActual['id']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RF04 - Seguimiento de Tutorías | Práctica 2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f3f6fb; color: #23324b; font-family: system-ui, Arial, sans-serif; }
        .cabecera { background: linear-gradient(115deg, #183d79, #2563aa); color: white; padding: 32px 0; }
        .tarjeta { border: 0; border-radius: 14px; box-shadow: 0 5px 22px rgba(38, 65, 105, .07); }
        .etiqueta-alto { background: #fde8e7; color: #9b2825; }
        .etiqueta-medio { background: #fff2d4; color: #856000; }
        .etiqueta-bajo { background: #e1f4e7; color: #17683a; }
        .riesgo { display: inline-block; border-radius: 20px; padding: 4px 11px; font-size: .8rem; font-weight: 650; }
        .dato { font-size: 1.8rem; font-weight: 750; color: #173f77; }
        .texto-registro { white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
</head>
<body>
    <header class="cabecera mb-4">
        <div class="container">
            <h1 class="h3 fw-bold mb-1">Sistema Web de Tutoría y Alerta Temprana</h1>
        </div>
    </header>

    <main class="container pb-5">

        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="card tarjeta p-3 h-100">
                <strong><?= escapar($tutorActual['nombre']) ?></strong>
            </div></div>
            <div class="col-md-4"><div class="card tarjeta p-3 h-100">
                <small class="text-secondary">Estudiantes asignados</small>
                <div class="dato"><?= count($estudiantesAsignados) ?></div>
            </div></div>
            <div class="col-md-4"><div class="card tarjeta p-3 h-100">
                <small class="text-secondary">Seguimientos registrados</small>
                <div class="dato"><?= $totalRegistros ?></div>
            </div></div>
        </div>

        <div class="row g-4">
            <section class="col-lg-6">
                <div class="card tarjeta p-4 h-100">
                    <h2 class="h5 fw-bold mb-3">Registrar sesión de tutoría</h2>
                    <?php if ($mensajeExito !== ''): ?>
                        <div class="alert alert-success" role="status"><?= escapar($mensajeExito) ?></div>
                    <?php endif; ?>
                    <?php if ($errores !== []): ?>
                        <div class="alert alert-danger" role="alert">
                            <strong>No se guardó el seguimiento:</strong>
                            <ul class="mb-0 mt-1">
                                <?php foreach ($errores as $error): ?>
                                    <li><?= escapar($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="post" novalidate>
                        <input type="hidden" name="rf04_token" value="<?= escapar($_SESSION['rf04_token']) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="codigo_estudiante">Estudiante asignado *</label>
                            <select class="form-select" name="codigo_estudiante" id="codigo_estudiante" required>
                                <option value="">-- Seleccione un estudiante --</option>
                                <?php foreach ($estudiantesAsignados as $codigo => $estudiante): ?>
                                    <option value="<?= escapar($codigo) ?>" <?= $formulario['codigo_estudiante'] === $codigo ? 'selected' : '' ?>>
                                        <?= escapar($codigo . ' - ' . $estudiante['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="fecha">Fecha de la sesión *</label>
                            <input class="form-control" type="date" id="fecha" name="fecha"
                                   max="<?= date('Y-m-d') ?>" value="<?= escapar($formulario['fecha']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="observaciones">Notas de evolución / observaciones *</label>
                            <textarea class="form-control" id="observaciones" name="observaciones"
                                      rows="4" required placeholder="Describa lo conversado durante la tutoría..."><?= escapar($formulario['observaciones']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="compromisos">Compromisos establecidos *</label>
                            <textarea class="form-control" id="compromisos" name="compromisos"
                                      rows="3" required placeholder="Ej.: asistir a sesiones de reforzamiento..."><?= escapar($formulario['compromisos']) ?></textarea>
                        </div>
                        <button class="btn btn-primary w-100 py-2 fw-semibold" type="submit">Guardar seguimiento</button>
                    </form>
                </div>
            </section>

            <section class="col-lg-6">
                <div class="card tarjeta p-4 h-100">
                    <h2 class="h5 fw-bold mb-3">Panel de estudiantes asignados</h2>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light"><tr><th>Código</th><th>Estudiante</th><th>Riesgo</th></tr></thead>
                            <tbody>
                                <?php foreach ($estudiantesAsignados as $codigo => $estudiante): ?>
                                    <?php $claseRiesgo = ['Alto' => 'etiqueta-alto', 'Medio' => 'etiqueta-medio', 'Bajo' => 'etiqueta-bajo'][$estudiante['riesgo']]; ?>
                                    <tr>
                                        <td class="font-monospace"><?= escapar($codigo) ?></td>
                                        <td><?= escapar($estudiante['nombre']) ?></td>
                                        <td><span class="riesgo <?= $claseRiesgo ?>"><?= escapar($estudiante['riesgo']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <section class="card tarjeta p-4 mt-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                <h2 class="h5 fw-bold mb-0">Historial de sesiones de tutoría</h2>
                <form method="get" class="d-flex gap-2 align-items-center">
                    <label for="estudiante" class="small text-secondary text-nowrap">Filtrar:</label>
                    <select class="form-select form-select-sm" id="estudiante" name="estudiante">
                        <option value="">Todos los estudiantes</option>
                        <?php foreach ($estudiantesAsignados as $codigo => $estudiante): ?>
                            <option value="<?= escapar($codigo) ?>" <?= $filtro === $codigo ? 'selected' : '' ?>>
                                <?= escapar($codigo . ' - ' . $estudiante['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary btn-sm" type="submit">Consultar</button>
                </form>
            </div>

            <?php if ($historial === []): ?>
                <div class="text-center text-secondary bg-light rounded p-4">
                    No hay sesiones registradas para la selección actual.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th><th>Estudiante</th><th>Observaciones</th><th>Compromisos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $registro): ?>
                                <tr>
                                    <td class="text-nowrap"><?= escapar($registro['fecha']) ?></td>
                                    <td><?= escapar($estudiantesAsignados[$registro['codigo_estudiante']]['nombre']) ?>
                                        <small class="d-block text-secondary"><?= escapar($registro['codigo_estudiante']) ?></small>
                                    </td>
                                    <td class="texto-registro"><?= escapar($registro['observaciones']) ?></td>
                                    <td class="texto-registro"><?= escapar($registro['compromisos']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
