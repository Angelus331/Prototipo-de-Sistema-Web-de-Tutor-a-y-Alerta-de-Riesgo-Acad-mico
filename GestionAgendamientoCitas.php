<?php
session_start();

// Inicializar citas en sesión si no existen
if (!isset($_SESSION['citas'])) {
    $_SESSION['citas'] = [
        ['id' => 1, 'estudiante' => 'David Mamani', 'tutor' => 'Dr. Carlos Pérez', 'fecha' => '2026-11-10', 'hora_inicio' => '09:00', 'hora_fin' => '10:00', 'estado' => 'Confirmada']
    ];
}

// Listado de tutores oficiales con sus horarios
$tutores = [
    1 => ['nombre' => 'Dr. Carlos Pérez', 'inicio' => '08:00', 'fin' => '13:00'],
    2 => ['nombre' => 'Dra. María Gómez', 'inicio' => '14:00', 'fin' => '18:00'],
    3 => ['nombre' => 'Ing. Luis Quispe', 'inicio' => '09:00', 'fin' => '16:00'],
];

$errores = [];
$success = '';

// Procesar peticiones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Manejar la acción de cancelar cita
    if (isset($_POST['accion']) && $_POST['accion'] === 'cancelar') {
        $idCancelar = $_POST['id'] ?? null;
        $_SESSION['citas'] = array_values(array_filter($_SESSION['citas'], function ($c) use ($idCancelar) {
            return $c['id'] != $idCancelar;
        }));
        $success = 'La cita ha sido cancelada correctamente.';
    }
    // Manejar el registro de una nueva cita
    else {
        $tutor_id = $_POST['tutor_id'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $hora_fin = $_POST['hora_fin'] ?? '';

        if (empty($tutor_id) || !isset($tutores[$tutor_id])) {
            $errores[] = 'Seleccione un profesional válido.';
        }
        if (empty($fecha)) {
            $errores[] = 'La fecha es obligatoria.';
        }
        if (empty($hora_inicio) || empty($hora_fin)) {
            $errores[] = 'Las horas de inicio y fin son obligatorias.';
        } elseif ($hora_inicio >= $hora_fin) {
            $errores[] = 'La hora de inicio debe ser anterior a la hora de fin.';
        }

        if (empty($errores)) {
            $tutor = $tutores[$tutor_id];

            // Validación estricta contra el horario del tutor (RF02/RF03)
            if ($hora_inicio < $tutor['inicio'] || $hora_fin > $tutor['fin']) {
                $errores[] = "La cita seleccionada está fuera del horario de atención de {$tutor['nombre']} ({$tutor['inicio']} - {$tutor['fin']}).";
            } else {
                $citasActuales = $_SESSION['citas'];
                $nuevoId = count($citasActuales) > 0 ? max(array_column($citasActuales, 'id')) + 1 : 1;

                $_SESSION['citas'][] = [
                    'id' => $nuevoId,
                    'estudiante' => 'David Mamani',
                    'tutor' => $tutor['nombre'],
                    'fecha' => $fecha,
                    'hora_inicio' => $hora_inicio,
                    'hora_fin' => $hora_fin,
                    'estado' => 'Confirmada'
                ];
                $success = '¡Cita agendada y validada exitosamente!';
            }
        }
    }
}
$citas = $_SESSION['citas'];
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

        <!-- Tarjeta Principal de Registro -->
        <div class="bg-white p-6 rounded-xl shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-slate-800">Agendamiento de Citas y Tutoría</h2>
            </div>

            <!-- Mensajes de Éxito -->
            <?php if (!empty($success)): ?>
                <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg text-sm">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <!-- Errores de Validación o Horario -->
            <?php if (!empty($errores)): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                    <ul class="list-disc pl-4">
                        <?php foreach ($errores as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Seleccionar Tutor / Bienestar:</label>
                    <select name="tutor_id" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                        <option value="">-- Seleccione un profesional --</option>
                        <?php foreach ($tutores as $id => $tutor): ?>
                            <option value="<?= $id ?>" <?= (isset($_POST['tutor_id']) && $_POST['tutor_id'] == $id) ? 'selected' : '' ?>>
                                <?= $tutor['nombre'] ?> (Atención: <?= $tutor['inicio'] ?> - <?= $tutor['fin'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Fecha:</label>
                        <input type="date" name="fecha" value="<?= htmlspecialchars($_POST['fecha'] ?? '') ?>"
                            class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Hora Inicio:</label>
                        <input type="time" name="hora_inicio"
                            value="<?= htmlspecialchars($_POST['hora_inicio'] ?? '') ?>"
                            class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Hora Fin:</label>
                        <input type="time" name="hora_fin" value="<?= htmlspecialchars($_POST['hora_fin'] ?? '') ?>"
                            class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-blue-600 text-white p-2 rounded-lg font-medium hover:bg-blue-700 transition">
                    Agendar Cita con Tutor
                </button>
            </form>
        </div>

        <!-- Sección Calendario / Listado de Citas Registradas -->
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
                                    <td class="p-3 font-semibold">#<?= $c['id'] ?></td>
                                    <td class="p-3"><?= $c['estudiante'] ?></td>
                                    <td class="p-3"><?= $c['tutor'] ?></td>
                                    <td class="p-3"><?= $c['fecha'] ?> (<?= $c['hora_inicio'] ?> - <?= $c['hora_fin'] ?>)</td>
                                    <td class="p-3"><span class="text-green-600 font-medium"><?= $c['estado'] ?></span></td>
                                    <td class="p-3 text-center">
                                        <form method="POST" onsubmit="return confirm('¿Estás seguro de cancelar esta cita?');">
                                            <input type="hidden" name="accion" value="cancelar">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit"
                                                class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1 rounded-md text-xs font-medium transition">
                                                Cancelar
                                            </button>
                                        </form>
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