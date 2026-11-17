<?php
/**
 * Componente: Gestión de Estudiantes
 * Responsabilidad: administrar los datos del estudiante y su condición de matrícula.
 */
class EstudianteService
{
    private array $estudiantes = [
        1 => ['nombre' => 'David Mamani', 'correo' => 'david.mamani@est.unap.edu.pe',   'escuela' => 'Ingeniería de Sistemas', 'matriculado' => true],
        2 => ['nombre' => 'Rosa Condori', 'correo' => 'rosa.condori@est.unap.edu.pe',   'escuela' => 'Ingeniería de Sistemas', 'matriculado' => true],
        3 => ['nombre' => 'Pedro Huanca', 'correo' => 'pedro.huanca@est.unap.edu.pe',   'escuela' => 'Ingeniería de Sistemas', 'matriculado' => false],
    ];

    public function listarEstudiantes(): array
    {
        return $this->estudiantes;
    }

    public function existeEstudiante(int $id): bool
    {
        return isset($this->estudiantes[$id]);
    }

    public function obtenerNombre(int $id): ?string
    {
        return $this->estudiantes[$id]['nombre'] ?? null;
    }

    public function obtenerCorreo(int $id): ?string
    {
        return $this->estudiantes[$id]['correo'] ?? null;
    }

    /**
     * Servicio que consume Gestión de Citas.
     * Recibe: id del estudiante.
     * Devuelve: ['habilitado' => bool, 'motivo' => string]
     */
    public function puedeSolicitarCita(int $id): array
    {
        $e = $this->estudiantes[$id] ?? null;

        if (!$e) {
            return ['habilitado' => false, 'motivo' => 'El estudiante no existe.'];
        }
        if (!$e['matriculado']) {
            return ['habilitado' => false, 'motivo' => "{$e['nombre']} no tiene matrícula vigente en el semestre."];
        }
        return ['habilitado' => true, 'motivo' => ''];
    }
}
