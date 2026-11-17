<?php
require_once __DIR__ . '/CitaService.php';

/**
 * Controlador: recibe la petición, invoca la operación del servicio
 * y devuelve un resultado uniforme para la vista.
 * Las reglas de negocio viven en CitaService.
 */
class CitaController
{
    private CitaService $service;
    private int $estudianteId;

    public function __construct(CitaService $service, int $estudianteId)
    {
        $this->service = $service;
        $this->estudianteId = $estudianteId;
    }

    public function manejar(array $post): array
    {
        $accion = $post['accion'] ?? 'agendar';
        $id = (int) ($post['id'] ?? 0);

        switch ($accion) {
            case 'cancelar':
                return $this->service->cancelarCita($id)
                    ? ['ok' => true, 'mensaje' => 'La cita ha sido cancelada correctamente.']
                    : ['ok' => false, 'errores' => ['La cita no existe.']];

            case 'reprogramar':
                return $this->service->reprogramarCita($id, $post);

            default:
                return $this->service->agendarCita($this->estudianteId, $post);
        }
    }

    /** Citas del perfil del estudiante en sesión. */
    public function listar(): array
    {
        return $this->service->consultarCitasPorEstudiante($this->estudianteId);
    }

    public function horariosDisponibles(int $tutorId, string $fecha): array
    {
        return $this->service->horariosDisponibles($tutorId, $fecha);
    }
}