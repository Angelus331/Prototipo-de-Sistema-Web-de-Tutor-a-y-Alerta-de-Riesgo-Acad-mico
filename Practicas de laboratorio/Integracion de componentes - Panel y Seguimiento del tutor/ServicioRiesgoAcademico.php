<?php

declare(strict_types=1);

/*publica la ultima carga masiva*/
function publicarRiesgosAcademicos(array $registros): void
{
    $riesgos = [];
    foreach ($registros as $registro) {
        $riesgos[$registro['codigo']] = $registro['riesgo'];
    }

    $_SESSION['rf01_riesgos'] = $riesgos;
}

function consultarCodigosCargados(): ?array
{
    return isset($_SESSION['rf01_riesgos']) ? array_keys($_SESSION['rf01_riesgos']) : null;
}

/*servicio consumido por el panel de Tutor*/
function consultarRiesgoAcademico(string $codigoEstudiante): string
{
    $riesgo = $_SESSION['rf01_riesgos'][$codigoEstudiante] ?? null;

    if ($riesgo === null) {
        return 'Sin datos académicos';
    }

    return $riesgo ? 'En riesgo' : 'Sin riesgo';
}
