@if ($grupo->exists && ($problemas = $grupo->problemasComposicion()))
    <div class="grupo-advertencia" role="status">
        <p><strong>Revisar composición del grupo.</strong> Corrige las asignaciones antes de guardar o reactivar. Puedes desactivarlo.</p>
        <ul>@foreach ($problemas as $problema)<li>{{ $problema }}</li>@endforeach</ul>
    </div>
@endif
