@props(['detalle', 'eliminable' => false, 'filtros' => [], 'pagina' => 1, 'contexto' => 'historial'])
@php
    $esPan = $detalle instanceof \App\Models\DetallePan;
    $esTorta = $detalle instanceof \App\Models\DetalleTorta;
    $familia = $esPan ? 'Pan' : ($esTorta ? 'Torta' : 'Bocadito');
    $fecha = $detalle->produccion->fecha->format('Y-m-d');
    $latasPorCoche = \App\Models\DetallePan::LATAS_POR_COCHE;
    $maestros = $detalle->empleados->filter(fn ($empleado) => $empleado->pivot->rolProduccion?->nombre_roles_produccion === 'Maestro');
    $ayudantes = $detalle->empleados->filter(fn ($empleado) => $empleado->pivot->rolProduccion?->nombre_roles_produccion === 'Ayudante');
    $practicantes = $detalle->empleados->filter(fn ($empleado) => $empleado->pivot->rolProduccion?->nombre_roles_produccion === 'Practicante');
@endphp
                <article class="carta-historial" data-familia="{{ strtolower($familia) }}" data-detalle-id="{{ $detalle->id }}" @if($contexto === 'produccion') data-detalle-registrado="{{ $detalle->id }}" @endif>
                    <header class="cabecera-carta">
                        <span class="etiqueta-familia">{{ $familia }}</span>
                        <time datetime="{{ $fecha }}">{{ $detalle->produccion->fecha->format('d/m/Y') }}</time>
                    </header>
                    <div class="registro-principal"><h3>{{ $detalle->producto?->nombre_p ?? 'Producto no disponible' }}</h3>
                        <p class="registro-cantidad"><span class="solo-lectores">Cantidad: </span>{{ $detalle->cantidad }} <span>{{ $esPan ? ($detalle->unidadMedida?->codigo === 'LATA' ? 'latas' : ($detalle->unidadMedida?->codigo === 'UNIDAD' ? 'unidades' : 'de unidad no identificada')) : ($esTorta ? 'tortas' : 'bocaditos') }}</span></p>
                    </div>
                    @if($contexto === 'historial')<p class="registro-maestro">Maestro: <strong>{{ $maestros->pluck('nombre_empleados')->implode(', ') ?: 'Sin Maestro registrado' }}</strong></p>@endif
                    <p class="turno-carta">Turno: <strong>{{ $detalle->produccion->turno?->nombre_turnos ?? 'Sin turno registrado' }}</strong></p>
                    <details class="detalles-produccion" name="registros-{{ $contexto }}">
                        <summary aria-expanded="false"><span class="detalle-abrir">Ver detalles</span><span class="detalle-cerrar">Ocultar detalles</span><span class="detalle-flecha" aria-hidden="true">↗</span></summary>
                        <div class="contenido-detalles">
                            @if ($esPan && $detalle->unidadMedida?->codigo === 'LATA')
                                <section class="grupo-detalles">
                                    <h4>Producción</h4>
                                    <p>{{ intdiv($detalle->cantidad, $latasPorCoche) }} coches + {{ $detalle->cantidad % $latasPorCoche }} latas adicionales</p>
                                    <p>Total de latas: <strong>{{ $detalle->cantidad }}</strong></p>
                                    <p>Unidad de medida: {{ $detalle->unidadMedida?->nombre_unidades_medida ?? 'Sin unidad registrada' }}</p>
                                    <p>Panes por lata utilizado: <strong>{{ $detalle->panes_por_lata_usado ?? 'Sin factor registrado' }}</strong></p>
                                    @if ($detalle->panes_por_lata_usado > 0)
                                        <p>Total estimado de panes: <strong>{{ $detalle->cantidad * $detalle->panes_por_lata_usado }}</strong></p>
                                    @endif
                                    <p>Hora de finalización: {{ $detalle->hora_finalizacion !== null ? substr($detalle->hora_finalizacion, 0, 5) : 'Sin hora registrada' }}</p>
                                </section>
                            @elseif ($esPan)
                                <section class="grupo-detalles">
                                    <h4>Producción</h4>
                                    <p>Unidad de medida: {{ $detalle->unidadMedida?->codigo === 'UNIDAD' ? 'UNIDAD' : 'Unidad histórica no identificada' }}</p>
                                </section>
                            @elseif ($esTorta)
                                <section class="grupo-detalles">
                                    <h4>Producción</h4>
                                    <p>Hora de vitrina: {{ $detalle->hora_vitrina !== null ? substr($detalle->hora_vitrina, 0, 5) : 'Sin hora registrada' }}</p>
                                    <p>Forma: {{ $detalle->forma }}</p>
                                    @if ($detalle->foto)
                                        <a href="{{ asset('storage/'.$detalle->foto) }}" target="_blank" rel="noopener">
                                            <span>Ver foto</span>
                                            <img class="foto-torta" src="{{ asset('storage/'.$detalle->foto) }}" alt="Foto de {{ $detalle->producto?->nombre_p ?? 'la torta' }}" loading="lazy" width="480" height="320">
                                        </a>
                                    @endif
                                </section>
                            @endif
                            <section class="grupo-detalles">
                                <h4>Equipo</h4>
                                @if($contexto === 'produccion')<p>Maestro: {{ $maestros->pluck('nombre_empleados')->implode(', ') ?: 'Sin Maestro registrado' }}</p>@endif
                                <p>Ayudantes: {{ $ayudantes->pluck('nombre_empleados')->implode(', ') ?: 'Sin Ayudantes registrados' }}</p>
                                @if ($practicantes->isNotEmpty())
                                    <p>Practicantes: {{ $practicantes->pluck('nombre_empleados')->implode(', ') }}</p>
                                @endif
                            </section>
                            <section class="grupo-detalles">
                                <h4>Información general</h4>
                                @if ($detalle->registrado_por_usuario_id !== null)
                                    <p>Registró el lote: {{ $detalle->usuario?->username ?? 'Usuario no disponible' }}</p>
                                @else
                                    <p>Registrador del lote: desconocido (registro histórico).</p>
                                    <p>Registró la sesión: {{ $detalle->produccion->usuario?->username ?? 'Usuario no disponible' }}</p>
                                @endif
                            </section>
                            @if ($detalle->observacion !== null && $detalle->observacion !== '')
                                <section class="grupo-detalles grupo-observacion">
                                    <h4>Observaciones</h4>
                                    <p class="observacion">{{ $detalle->observacion }}</p>
                                </section>
                            @endif
                            @if($eliminable && auth()->user()->can('administrar-sistema'))
                                <form class="acciones-registro" method="POST" action="{{ route('history.destroy', ['familia' => strtolower($familia), 'detalle' => $detalle->id] + array_filter($filtros, fn ($valor) => $valor !== '') + ['page' => $pagina]) }}" data-eliminar-registro
                                      data-producto="{{ $detalle->producto?->nombre_p ?? 'Producto no disponible' }}"
                                      data-fecha="{{ $detalle->produccion->fecha->format('d/m/Y') }}"
                                      data-turno="{{ $detalle->produccion->turno?->nombre_turnos ?? 'Sin turno registrado' }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="confirmado" value="0">
                                    <button type="button" class="boton-eliminar-registro" data-abrir-eliminacion disabled>
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6" /></svg>
                                        Eliminar registro
                                    </button>
                                    <noscript><p class="error-filtro">Activa JavaScript para confirmar la eliminación.</p></noscript>
                                </form>
                            @endif
                        </div>
                    </details>
                </article>
