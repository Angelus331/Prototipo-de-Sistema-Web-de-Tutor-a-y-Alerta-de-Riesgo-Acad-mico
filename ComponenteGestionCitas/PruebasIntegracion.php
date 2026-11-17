<?php
/**
 * Pruebas de integración: Gestión de Citas <-> Tutores / Estudiantes / Persistencia / Notificaciones.
 * Ejecutar:  php PruebasIntegracion.php
 */
require_once __DIR__ . '/CitaController.php';

function entorno(int $estudianteId = 1): array
{
    $_SESSION = []; // sesión limpia: cita semilla #1 (Dr. Carlos Pérez, 2026-11-10, 09:00-10:00)
    $svc = new CitaService(new TutorService(), new EstudianteService(), new SesionCitaRepository(), new NotificacionService());
    return [new CitaController($svc, $estudianteId), $svc];
}

$total = 0; $pasaron = 0;
function verificar(string $nombre, bool $cumple, string $detalle): void
{
    global $total, $pasaron;
    $total++; $pasaron += $cumple ? 1 : 0;
    printf("[%s] %s\n      -> %s\n", $cumple ? 'CORRECTO' : 'INCORRECTO', $nombre, $detalle);
}
function msg(array $r): string { return $r['ok'] ? $r['mensaje'] : implode(' | ', $r['errores']); }

echo "=== PRUEBAS DE INTEGRACION: GESTION DE CITAS ===\n\n";

[$c, $s] = entorno(1);
$r = $c->manejar(['accion'=>'agendar','tutor_id'=>1,'fecha'=>'2026-11-20','hora_inicio'=>'09:00','hora_fin'=>'10:00']);
$correos = $_SESSION['notificaciones'] ?? [];
verificar('P1 Datos correctos (David Mamani, Dr. Carlos Pérez, 2026-11-20 09:00-10:00)',
    $r['ok'] && count($s->consultarCitas()) === 2 && count($correos) === 1, msg($r) . ' | correo a: ' . ($correos[0]['para'] ?? 'ninguno'));

[$c, $s] = entorno(1);
$r = $c->manejar(['accion'=>'agendar','tutor_id'=>1,'fecha'=>'2026-11-20','hora_inicio'=>'14:00','hora_fin'=>'15:00']);
verificar('P2 Fuera del horario del tutor (08:00-13:00; pide 14:00-15:00)',
    !$r['ok'] && str_contains(msg($r), 'fuera del horario') && count($s->consultarCitas()) === 1, msg($r));

[$c1, $s] = entorno(1);
$c1->manejar(['accion'=>'agendar','tutor_id'=>1,'fecha'=>'2026-11-20','hora_inicio'=>'09:00','hora_fin'=>'10:00']);
$c2 = new CitaController($s, 2);
$r = $c2->manejar(['accion'=>'agendar','tutor_id'=>1,'fecha'=>'2026-11-20','hora_inicio'=>'09:30','hora_fin'=>'10:30']);
verificar('P3 Cruce con cita del tutor (Rosa Condori pide 09:30-10:30)',
    !$r['ok'] && str_contains(msg($r), 'El tutor ya tiene') && count($s->consultarCitas()) === 2, msg($r));

[$c, $s] = entorno(3);
$r = $c->manejar(['accion'=>'agendar','tutor_id'=>3,'fecha'=>'2026-11-25','hora_inicio'=>'10:00','hora_fin'=>'11:00']);
verificar('P4 Estudiante sin matrícula vigente (Pedro Huanca)',
    !$r['ok'] && str_contains(msg($r), 'no tiene matrícula') && count($s->consultarCitas()) === 1, msg($r));

[$c, $s] = entorno(1);
$r = $c->manejar(['accion'=>'reprogramar','id'=>1,'fecha'=>'2026-11-21','hora_inicio'=>'11:00','hora_fin'=>'12:00']);
verificar('P5 Reprogramar cita #1 a 2026-11-21 11:00-12:00',
    $r['ok'] && $s->consultarCita(1)['estado'] === 'Reprogramada', msg($r) . ' | estado: ' . $s->consultarCita(1)['estado']);

[$c, $s] = entorno(1);
$r = $c->manejar(['accion'=>'cancelar','id'=>1]);
$r2 = $c->manejar(['accion'=>'reprogramar','id'=>1,'fecha'=>'2026-11-21','hora_inicio'=>'11:00','hora_fin'=>'12:00']);
verificar('P6 Cancelar cita #1 conserva el registro y bloquea reprogramarla',
    $r['ok'] && $s->consultarCita(1)['estado'] === 'Cancelada' && !$r2['ok'], 'estado: ' . $s->consultarCita(1)['estado'] . ' | ' . msg($r2));

[$c, $s] = entorno(1);
$c->manejar(['accion'=>'cancelar','id'=>1]);
$r = $c->manejar(['accion'=>'agendar','tutor_id'=>1,'fecha'=>'2026-11-10','hora_inicio'=>'09:00','hora_fin'=>'10:00']);
$rechazoPasada = !$r['ok'];
$r = $c->manejar(['accion'=>'agendar','tutor_id'=>1,'fecha'=>'2026-11-30','hora_inicio'=>'09:00','hora_fin'=>'10:00']);
$cita = $s->consultarCita(2);
verificar('P7 Un horario cancelado queda libre para volver a agendarse',
    $r['ok'] && $cita !== null, msg($r));

[$c, $s] = entorno(1);
$c->manejar(['accion'=>'agendar','tutor_id'=>1,'fecha'=>'2026-11-20','hora_inicio'=>'09:00','hora_fin'=>'10:00']);
$libres = $c->horariosDisponibles(1, '2026-11-20');
$lista = implode(', ', array_map(fn($h) => "{$h['inicio']}-{$h['fin']}", $libres));
verificar('P8 Horarios disponibles del Dr. Carlos Pérez el 2026-11-20 (atiende 08:00-13:00, ocupado 09:00-10:00)',
    count($libres) === 4 && !str_contains($lista, '09:00-10:00'), $lista);

[$c, $s] = entorno(1);
$c2 = new CitaController($s, 2);
$c2->manejar(['accion'=>'agendar','tutor_id'=>2,'fecha'=>'2026-11-20','hora_inicio'=>'14:00','hora_fin'=>'15:00']);
verificar('P9 El perfil de cada estudiante lista solo sus citas',
    count($c->listar()) === 1 && count($c2->listar()) === 1 && $c->listar()[0]['estudiante_id'] === 1,
    'David: ' . count($c->listar()) . ' cita(s) | Rosa: ' . count($c2->listar()) . ' cita(s)');

echo "\nRESULTADO: $pasaron de $total pruebas correctas\n";
exit($pasaron === $total ? 0 : 1);


