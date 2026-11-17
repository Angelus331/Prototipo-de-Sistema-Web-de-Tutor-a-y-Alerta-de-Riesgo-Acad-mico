<?php
/**
 * Componente: Notificaciones
 * Responsabilidad: avisar por correo al estudiante cuando su cita cambia.
 * En el prototipo el envío se simula: el correo queda registrado en la sesión
 * (en producción aquí se invocaría SMTP / mail()).
 */
class NotificacionService
{
    public function enviarCorreo(?string $destinatario, string $asunto, string $mensaje): bool
    {
        if (!$destinatario) {
            return false;
        }
        $_SESSION['notificaciones'][] = [
            'para' => $destinatario, 'asunto' => $asunto, 'mensaje' => $mensaje,
            'fecha' => date('Y-m-d H:i'),
        ];
        return true;
    }

    public function ultimas(int $n = 5): array
    {
        return array_slice(array_reverse($_SESSION['notificaciones'] ?? []), 0, $n);
    }
}
