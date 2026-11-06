<?php
/**
 * Componente: Gestión de Tutores
 * Responsabilidad: administrar los datos de los tutores y su horario de atención.
 */
class TutorService
{
    private array $tutores = [
        1 => ['nombre' => 'Dr. Carlos Pérez', 'inicio' => '08:00', 'fin' => '13:00'],
        2 => ['nombre' => 'Dra. María Gómez', 'inicio' => '14:00', 'fin' => '18:00'],
        3 => ['nombre' => 'Ing. Luis Quispe', 'inicio' => '09:00', 'fin' => '16:00'],
    ];

    public function listarTutores(): array
    {
        return $this->tutores;
    }

    public function existeTutor(int $id): bool
    {
        return isset($this->tutores[$id]);
    }

    public function obtenerNombre(int $id): ?string
    {
        return $this->tutores[$id]['nombre'] ?? null;
    }

    /**
     * Servicio que consume Gestión de Citas.
     * Recibe: id del tutor, hora de inicio y hora de fin.
     * Devuelve: ['disponible' => bool, 'motivo' => string]
     */
    public function estaEnHorarioAtencion(int $id, string $ini, string $fin): array
    {
        $t = $this->tutores[$id] ?? null;

        if (!$t) {
            return ['disponible' => false, 'motivo' => 'El tutor no existe.'];
        }
        if ($ini < $t['inicio'] || $fin > $t['fin']) {
            return [
                'disponible' => false,
                'motivo' => "La cita está fuera del horario de atención de {$t['nombre']} ({$t['inicio']} - {$t['fin']}).",
            ];
        }
        return ['disponible' => true, 'motivo' => ''];
    }
}