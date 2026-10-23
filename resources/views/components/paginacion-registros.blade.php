@props(['pagina'])
@if($pagina->hasPages())
    <nav class="paginacion-historial" aria-label="Paginación de producciones">
        @if($pagina->previousPageUrl())<a href="{{ $pagina->previousPageUrl() }}" rel="prev">Anterior</a>@else<span>Anterior</span>@endif
        <span>Página {{ $pagina->currentPage() }} de {{ $pagina->lastPage() }}</span>
        @if($pagina->nextPageUrl())<a href="{{ $pagina->nextPageUrl() }}" rel="next">Siguiente</a>@else<span>Siguiente</span>@endif
    </nav>
@endif
