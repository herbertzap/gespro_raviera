@php
use App\Helpers\EstadoHelper;

function getEstadoColor($estado) {
    return \App\Helpers\EstadoHelper::getEstadoColor($estado);
}
@endphp

<div>
    <p>Test: {{ getEstadoColor('pendiente') }}</p>
</div>
