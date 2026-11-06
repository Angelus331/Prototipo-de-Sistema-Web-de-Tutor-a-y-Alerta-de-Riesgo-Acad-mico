<?php
session_start();
require_once __DIR__ . '/TutorService.php';
require_once __DIR__ . '/CitaService.php';
require_once __DIR__ . '/CitaController.php';

$tutorService = new TutorService();
$controller = new CitaController(new CitaService($tutorService), 'David Mamani');
$tutores = $tutorService->listarTutores();

$errores = [];
$success = '';
$accionPost = $_POST['accion'] ?? 'agendar';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = $controller->manejar($_POST);
    if ($res['ok']) {
        $success = $res['mensaje'];
    } else {
        $errores = $res['errores'];
    }
}
$citas = $controller->listar();

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
// Solo conserva los valores en el formulario de agendar si hubo error
$conservar = $_SERVER['REQUEST_METHOD'] === 'POST' && $accionPost === 'agendar' && !empty($errores);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gestión y Agendamiento de Citas - UNAP</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-slate-50 p-6">
    <div class="max-w-4xl mx-auto space-y-6">

        <div class="bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-xl font-bold text-slate-800 mb-4">Agendamiento de Citas y Tutoría</h2>

            <?php if (!empty($success)): ?>
                <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg text-sm"><?= e($success) ?></div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                    <ul class="list-disc pl-4">
                        <?php foreach ($errores as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="agendar">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Seleccionar Tutor / Bienestar:</label>
                    <select name="tutor_id" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                        <option value="">-- Seleccione un profesional --</option>
                        <?php foreach ($tutores as $id => $t): ?>
                            <option value="<?= $id ?>" <?= ($conservar && ($_POST['tutor_id'] ?? '') == $id) ? 'selected' : '' ?>>
                                <?= e($t['nombre']) ?> (Atención: <?= e($t['inicio']) ?> - <?= e($t['fin']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Fecha:</label>
                        <input type="date" name="fecha" value="<?= $conservar ? e($_POST['fecha'] ?? '') : '' ?>"
                            class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Hora Inicio:</label>
                        <input type="time" name="hora_inicio"
                            value="<?= $conservar ? e($_POST['hora_inicio'] ?? '') : '' ?>"
                            class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Hora Fin:</label>
                        <input type="time" name="hora_fin" value="<?= $conservar ? e($_POST['hora_fin'] ?? '') : '' ?>"
                            class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-blue-600 text-white p-2 rounded-lg font-medium hover:bg-blue-700 transition">
                    Agendar Cita con Tutor
                </button>
            </form>
        </div>

        <div id="calendario-citas" class="bg-white p-6 rounded-xl shadow-md">
            <h3 class="text-lg font-bold text-slate-800 mb-3">Calendario y Citas Registradas</h3>

            <?php if (empty($citas)): ?>
                <p class="text-slate-500 text-sm">No hay citas registradas en el sistema todavía.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-100 text-slate-700 uppercase text-xs">
                            <tr>
                                <th class="p-3">ID</th>
                                <th class="p-3">Estudiante</th>
                                <th class="p-3">Tutor Asignado</th>
                                <th class="p-3">Fecha y Hora</th>
                                <th class="p-3">Estado</th>
                                <th class="p-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php foreach ($citas as $c): ?>
                                <tr>
                                    <td class="p-3 font-semibold">#<?= e($c['id']) ?></td>
                                    <td class="p-3"><?= e($c['estudiante']) ?></td>
                                    <td class="p-3"><?= e($c['tutor']) ?></td>
                                    <td class="p-3"><?= e($c['fecha']) ?> (<?= e($c['hora_inicio']) ?> -
                                        <?= e($c['hora_fin']) ?>)</td>
                                    <td class="p-3"><span class="text-green-600 font-medium"><?= e($c['estado']) ?></span></td>
                                    <td class="p-3">
                                        <div class="flex flex-col items-center gap-2">
                                            <form method="POST"
                                                onsubmit="return confirm('¿Estás seguro de cancelar esta cita?');">
                                                <input type="hidden" name="accion" value="cancelar">
                                                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                                <button type="submit"
                                                    class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1 rounded-md text-xs font-medium transition">
                                                    Cancelar
                                                </button>
                                            </form>
                                            <details class="text-xs">
                                                <summary class="cursor-pointer text-blue-600 text-center">Reprogramar</summary>
                                                <form method="POST" class="mt-2 space-y-1">
                                                    <input type="hidden" name="accion" value="reprogramar">
                                                    <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                                    <input type="date" name="fecha" value="<?= e($c['fecha']) ?>"
                                                        class="w-full p-1 border border-slate-300 rounded">
                                                    <input type="time" name="hora_inicio" value="<?= e($c['hora_inicio']) ?>"
                                                        class="w-full p-1 border border-slate-300 rounded">
                                                    <input type="time" name="hora_fin" value="<?= e($c['hora_fin']) ?>"
                                                        class="w-full p-1 border border-slate-300 rounded">
                                                    <button type="submit"
                                                        class="w-full bg-blue-50 text-blue-600 hover:bg-blue-100 px-2 py-1 rounded font-medium">Guardar</button>
                                                </form>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</body>

</html>