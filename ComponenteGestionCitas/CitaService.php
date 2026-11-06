<?php
require_once __DIR__ . '/TutorService.php';

/**
 * Componente: Gestión de Citas
 * Responsabilidad: gestionar el ciclo de vida de las citas de acompañamiento,
 * validando la disponibilidad del tutor y controlando el estado de las citas.
 */
class CitaService
{
    private TutorService $tutorService;

    public function __construct(TutorService $tutorService)
    {
        $this->tutorService = $tutorService;

        // Si no hay citas, o hay citas viejas sin tutor_id, se reinicia con la semilla
        $sesionInvalida = !isset($_SESSION['citas'])
            || count(array_filter($_SESSION['citas'], fn($c) => !isset($c['tutor_id']))) > 0;

        if ($sesionInvalida) {
            $_SESSION['citas'] = [
                [
                    'id' => 1,
                    'estudiante' => 'David Mamani',
                    'tutor_id' => 1,
                    'tutor' => 'Dr. Carlos Pérez',
                    'fecha' => '2026-11-10',
                    'hora_inicio' => '09:00',
                    'hora_fin' => '10:00',
                    'estado' => 'Confirmada',
                ]
            ];
        }
    }

    /** Recibe los datos, valida, procesa y devuelve el resultado. */
    public function agendarCita(string $estudiante, array $datos): array
    {
        $errores = $this->validarDatos($datos);

        if (empty($errores)) {
            $errores = $this->verificarDisponibilidad(
                (int) $datos['tutor_id'],
                $datos['fecha'],
                $datos['hora_inicio'],
                $datos['hora_fin']
            );
        }
        if (!empty($errores)) {
            return ['ok' => false, 'errores' => $errores];
        }

        $citas = $this->consultarCitas();
        $nuevoId = $citas ? max(array_column($citas, 'id')) + 1 : 1;
        $tutorId = (int) $datos['tutor_id'];

        $_SESSION['citas'][] = [
            'id' => $nuevoId,
            'estudiante' => $estudiante,
            'tutor_id' => $tutorId,
            'tutor' => $this->tutorService->obtenerNombre($tutorId),
            'fecha' => $datos['fecha'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $datos['hora_fin'],
            'estado' => 'Confirmada',
        ];

        return ['ok' => true, 'mensaje' => '¡Cita agendada y validada exitosamente!'];
    }

    public function cancelarCita(int $id): bool
    {
        $antes = count($_SESSION['citas']);
        $_SESSION['citas'] = array_values(array_filter(
            $_SESSION['citas'],
            fn($c) => $c['id'] !== $id
        ));
        return count($_SESSION['citas']) < $antes;
    }

    public function reprogramarCita(int $id, array $datos): array
    {
        $cita = $this->consultarCita($id);
        if (!$cita) {
            return ['ok' => false, 'errores' => ['La cita no existe.']];
        }

        // El tutor se mantiene; solo cambian fecha y horas
        $datos['tutor_id'] = $cita['tutor_id'];

        $errores = $this->validarDatos($datos);
        if (empty($errores)) {
            // Se excluye la propia cita para que no choque consigo misma
            $errores = $this->verificarDisponibilidad(
                (int) $cita['tutor_id'],
                $datos['fecha'],
                $datos['hora_inicio'],
                $datos['hora_fin'],
                $id
            );
        }
        if (!empty($errores)) {
            return ['ok' => false, 'errores' => $errores];
        }

        foreach ($_SESSION['citas'] as &$c) {
            if ($c['id'] === $id) {
                $c['fecha'] = $datos['fecha'];
                $c['hora_inicio'] = $datos['hora_inicio'];
                $c['hora_fin'] = $datos['hora_fin'];
                $c['estado'] = 'Reprogramada';
            }
        }
        unset($c);

        return ['ok' => true, 'mensaje' => 'Cita reprogramada correctamente.'];
    }

    public function consultarCitas(): array
    {
        return $_SESSION['citas'];
    }

    public function consultarCita(int $id): ?array
    {
        foreach ($_SESSION['citas'] as $c) {
            if ($c['id'] === $id) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Verifica disponibilidad. Devuelve lista de errores (vacía = disponible).
     * - Horario de atención: lo decide Gestión de Tutores (TutorService).
     * - Cruce con otras citas: lo decide Gestión de Citas (datos propios).
     */
    public function verificarDisponibilidad(int $tutorId, string $fecha, string $ini, string $fin, ?int $excluirId = null): array
    {
        $r = $this->tutorService->estaEnHorarioAtencion($tutorId, $ini, $fin);
        if (!$r['disponible']) {
            return [$r['motivo']];
        }

        foreach ($_SESSION['citas'] as $c) {
            if ($c['id'] === $excluirId) {
                continue;
            }
            if (
                ($c['tutor_id'] ?? null) == $tutorId && $c['fecha'] === $fecha
                && $ini < $c['hora_fin'] && $fin > $c['hora_inicio']
            ) {
                return ["El tutor ya tiene una cita de {$c['hora_inicio']} a {$c['hora_fin']} ese día."];
            }
        }
        return [];
    }

    /** Validaciones de formato y coherencia de los datos recibidos. */
    private function validarDatos(array $d): array
    {
        $errores = [];

        if (empty($d['tutor_id']) || !$this->tutorService->existeTutor((int) $d['tutor_id'])) {
            $errores[] = 'Seleccione un profesional válido.';
        }
        if (empty($d['fecha'])) {
            $errores[] = 'La fecha es obligatoria.';
        } elseif ($d['fecha'] < date('Y-m-d')) {
            $errores[] = 'No se puede agendar una cita en una fecha pasada.';
        }
        if (empty($d['hora_inicio']) || empty($d['hora_fin'])) {
            $errores[] = 'Las horas de inicio y fin son obligatorias.';
        } elseif ($d['hora_inicio'] >= $d['hora_fin']) {
            $errores[] = 'La hora de inicio debe ser anterior a la hora de fin.';
        }

        return $errores;
    }
}