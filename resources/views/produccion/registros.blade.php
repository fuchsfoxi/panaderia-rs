<div data-resultados-registros tabindex="-1">
    @if($erroresFiltros->isNotEmpty())
        <div class="feedback-produccion feedback-produccion-error" role="alert">
            <p>No se pudieron cargar los registros.</p>
            <ul>@foreach($erroresFiltros->all() as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul>
        </div>
    @else
        <p class="datos-encontrados" role="status">{{ $detalles->total() }} registros encontrados</p>
        @forelse($detalles as $registro)
            <x-registro-produccion :detalle="$registro" contexto="produccion" />
        @empty
            <p class="estado-vacio" role="status">{{ $detalles->total() ? 'No hay registros en esta página.' : 'No hay registros en esta sección.' }}</p>
        @endforelse
        <x-paginacion-registros :pagina="$detalles" />
    @endif
</div>
