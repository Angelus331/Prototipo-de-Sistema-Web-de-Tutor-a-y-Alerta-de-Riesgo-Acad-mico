<?php
/**
 * Componente: Persistencia de Citas
 * Responsabilidad: guardar y recuperar citas. Gestión de Citas depende de esta
 * interfaz, no del almacenamiento; para pasar a PostgreSQL solo se crea otra
 * implementación (p. ej. PostgresCitaRepository) sin tocar CitaService.
 */
interface CitaRepository
{
    public function todas(): array;
    public function buscar(int $id): ?array;
    public function guardar(array $cita): void;   // inserta o actualiza según el id
    public function siguienteId(): int;
}

/** Implementación del prototipo: guarda las citas en la sesión PHP. */
class SesionCitaRepository implements CitaRepository
{
    public function __construct()
    {
        $invalida = !isset($_SESSION['citas'])
            || count(array_filter($_SESSION['citas'], fn($c) => !isset($c['tutor_id']) || !isset($c['estudiante_id']))) > 0;

        if ($invalida) {
            $_SESSION['citas'] = [[
                'id' => 1, 'estudiante_id' => 1, 'estudiante' => 'David Mamani',
                'tutor_id' => 1, 'tutor' => 'Dr. Carlos Pérez',
                'fecha' => '2026-11-10', 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
                'estado' => 'Confirmada',
            ]];
        }
    }

    public function todas(): array
    {
        return $_SESSION['citas'];
    }

    public function buscar(int $id): ?array
    {
        foreach ($_SESSION['citas'] as $c) {
            if ($c['id'] === $id) {
                return $c;
            }
        }
        return null;
    }

    public function guardar(array $cita): void
    {
        foreach ($_SESSION['citas'] as $i => $c) {
            if ($c['id'] === $cita['id']) {
                $_SESSION['citas'][$i] = $cita;
                return;
            }
        }
        $_SESSION['citas'][] = $cita;
    }

    public function siguienteId(): int
    {
        $ids = array_column($_SESSION['citas'], 'id');
        return $ids ? max($ids) + 1 : 1;
    }
}
