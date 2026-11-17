<?php
require_once __DIR__ . '/TutorService.php';
require_once __DIR__ . '/EstudianteService.php';
require_once __DIR__ . '/CitaRepository.php';
require_once __DIR__ . '/NotificacionService.php';

/**
 * Componente: Gestión de Citas
 * Responsabilidad: gestionar el ciclo de vida de las citas de acompañamiento,
 * validando la disponibilidad del tutor y controlando el estado de las citas.
 * Estados: Confirmada -> Reprogramada -> Cancelada.
 *
 * Colabora (solo por métodos públicos) con:
 *  - Gestión de Tutores      (TutorService)
 *  - Gestión de Estudiantes  (EstudianteService)
 *  - Persistencia de Citas   (CitaRepository)
 *  - Notificaciones          (NotificacionService)
 */
class CitaService
{
    private TutorService $tutorService;
    private EstudianteService $estudianteService;
    private CitaRepository $repo;
    private NotificacionService $notificaciones;

    public function __construct(
        TutorService $tutorService,
        EstudianteService $estudianteService,
        CitaRepository $repo,
        NotificacionService $notificaciones
    ) {
        $this->tutorService = $tutorService;
        $this->estudianteService = $estudianteService;
        $this->repo = $repo;
        $this->notificaciones = $notificaciones;
    }

    /** Recibe los datos, valida, procesa y devuelve el resultado. */
    public function agendarCita(int $estudianteId, array $datos): array
    {
        $errores = $this->validarEstudiante($estudianteId);
        if (empty($errores)) {
            $errores = $this->validarDatos($datos);
        }
        if (empty($errores)) {
            $errores = $this->verificarDisponibilidad(
                (int) $datos['tutor_id'], $datos['fecha'], $datos['hora_inicio'], $datos['hora_fin']
            );
        }
        if (empty($errores)) {
            $errores = $this->verificarCruceEstudiante(
                $estudianteId, $datos['fecha'], $datos['hora_inicio'], $datos['hora_fin']
            );
        }
        if (!empty($errores)) {
            return ['ok' => false, 'errores' => $errores];
        }

        $tutorId = (int) $datos['tutor_id'];
        $cita = [
            'id' => $this->repo->siguienteId(),
            'estudiante_id' => $estudianteId,
            'estudiante' => $this->estudianteService->obtenerNombre($estudianteId),
            'tutor_id' => $tutorId,
            'tutor' => $this->tutorService->obtenerNombre($tutorId),
            'fecha' => $datos['fecha'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $datos['hora_fin'],
            'estado' => 'Confirmada',
        ];
        $this->repo->guardar($cita);
        $this->notificar('Cita confirmada', $cita);

        return ['ok' => true, 'mensaje' => '¡Cita agendada y validada exitosamente!'];
    }

    /** Cancela (no borra): la cita queda como "Cancelada" y libera el horario. */
    public function cancelarCita(int $id): bool
    {
        $cita = $this->repo->buscar($id);
        if (!$cita || $cita['estado'] === 'Cancelada') {
            return false;
        }
        $cita['estado'] = 'Cancelada';
        $this->repo->guardar($cita);
        $this->notificar('Cita cancelada', $cita);
        return true;
    }

    public function reprogramarCita(int $id, array $datos): array
    {
        $cita = $this->repo->buscar($id);
        if (!$cita) {
            return ['ok' => false, 'errores' => ['La cita no existe.']];
        }
        if ($cita['estado'] === 'Cancelada') {
            return ['ok' => false, 'errores' => ['No se puede reprogramar una cita cancelada.']];
        }

        $datos['tutor_id'] = $cita['tutor_id'];   // el tutor se mantiene

        $errores = $this->validarDatos($datos);
        if (empty($errores)) {
            $errores = $this->verificarDisponibilidad(
                (int) $cita['tutor_id'], $datos['fecha'], $datos['hora_inicio'], $datos['hora_fin'], $id
            );
        }
        if (empty($errores)) {
            $errores = $this->verificarCruceEstudiante(
                (int) $cita['estudiante_id'], $datos['fecha'], $datos['hora_inicio'], $datos['hora_fin'], $id
            );
        }
        if (!empty($errores)) {
            return ['ok' => false, 'errores' => $errores];
        }

        $cita['fecha'] = $datos['fecha'];
        $cita['hora_inicio'] = $datos['hora_inicio'];
        $cita['hora_fin'] = $datos['hora_fin'];
        $cita['estado'] = 'Reprogramada';
        $this->repo->guardar($cita);
        $this->notificar('Cita reprogramada', $cita);

        return ['ok' => true, 'mensaje' => 'Cita reprogramada correctamente.'];
    }

    public function consultarCitas(): array
    {
        return $this->repo->todas();
    }

    /** Citas del perfil del estudiante (RF03: consultar desde su perfil). */
    public function consultarCitasPorEstudiante(int $estudianteId): array
    {
        return array_values(array_filter(
            $this->repo->todas(),
            fn($c) => ($c['estudiante_id'] ?? null) === $estudianteId
        ));
    }

    public function consultarCita(int $id): ?array
    {
        return $this->repo->buscar($id);
    }

    /**
     * Horarios libres de un tutor en una fecha (RF03: "fechas y horarios disponibles").
     * Combina el horario de atención (Gestión de Tutores) con las citas activas (propio).
     * Devuelve una lista de ['inicio' => 'HH:MM', 'fin' => 'HH:MM'].
     */
    public function horariosDisponibles(int $tutorId, string $fecha, int $minutos = 60): array
    {
        $h = $this->tutorService->obtenerHorario($tutorId);
        if (!$h || $fecha === '' || $fecha < date('Y-m-d')) {
            return [];
        }
        $libres = [];
        $t = strtotime($h['inicio']);
        $limite = strtotime($h['fin']);
        while ($t + $minutos * 60 <= $limite) {
            $ini = date('H:i', $t);
            $fin = date('H:i', $t + $minutos * 60);
            if (empty($this->verificarDisponibilidad($tutorId, $fecha, $ini, $fin))) {
                $libres[] = ['inicio' => $ini, 'fin' => $fin];
            }
            $t += $minutos * 60;
        }
        return $libres;
    }

    /**
     * Verifica disponibilidad. Devuelve lista de errores (vacía = disponible).
     * - Horario de atención: lo decide Gestión de Tutores.
     * - Cruce con otras citas activas: lo decide Gestión de Citas.
     */
    public function verificarDisponibilidad(int $tutorId, string $fecha, string $ini, string $fin, ?int $excluirId = null): array
    {
        $r = $this->tutorService->estaEnHorarioAtencion($tutorId, $ini, $fin);
        if (!$r['disponible']) {
            return [$r['motivo']];
        }
        foreach ($this->repo->todas() as $c) {
            if ($c['id'] === $excluirId || $c['estado'] === 'Cancelada') {
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

    /** Servicio de Gestión de Estudiantes: ¿puede el estudiante solicitar citas? */
    private function validarEstudiante(int $estudianteId): array
    {
        $r = $this->estudianteService->puedeSolicitarCita($estudianteId);
        return $r['habilitado'] ? [] : [$r['motivo']];
    }

    /** Un estudiante no puede tener dos citas activas que se crucen el mismo día. */
    private function verificarCruceEstudiante(int $estudianteId, string $fecha, string $ini, string $fin, ?int $excluirId = null): array
    {
        foreach ($this->repo->todas() as $c) {
            if ($c['id'] === $excluirId || $c['estado'] === 'Cancelada') {
                continue;
            }
            if (
                ($c['estudiante_id'] ?? null) == $estudianteId && $c['fecha'] === $fecha
                && $ini < $c['hora_fin'] && $fin > $c['hora_inicio']
            ) {
                return ["El estudiante ya tiene una cita de {$c['hora_inicio']} a {$c['hora_fin']} ese día."];
            }
        }
        return [];
    }

    /** Avisa al estudiante por correo (Componente Notificaciones). */
    private function notificar(string $asunto, array $cita): void
    {
        $this->notificaciones->enviarCorreo(
            $this->estudianteService->obtenerCorreo((int) $cita['estudiante_id']),
            $asunto,
            "{$asunto}: {$cita['tutor']}, {$cita['fecha']} de {$cita['hora_inicio']} a {$cita['hora_fin']}."
        );
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
