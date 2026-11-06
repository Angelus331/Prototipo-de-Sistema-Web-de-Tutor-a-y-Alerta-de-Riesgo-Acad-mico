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
    private string $estudiante;

    public function __construct(CitaService $service, string $estudiante)
    {
        $this->service = $service;
        $this->estudiante = $estudiante;
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
                return $this->service->agendarCita($this->estudiante, $post);
        }
    }

    public function listar(): array
    {
        return $this->service->consultarCitas();
    }
}