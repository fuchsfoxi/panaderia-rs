    <!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producción - Panadería RS</title>
    @vite(['resources/css/produccion.css', 'resources/js/produccion.js'])
    </head>
    <body class="pagina-con-sidebar">
    <x-sidebar />

    <div class="pagina-produccion">

        <div class="titulo-produccion">
            <p class="marca-pagina">Panadería RS</p>
            <h1>Producción</h1>
            <p class="descripcion-pagina">Registra Pan, Torta o Bocadito para la fecha y el turno seleccionados.</p>
        </div>

        @if (session('success'))
            <div class="notificacion-produccion" data-exito-produccion role="status" aria-live="polite" aria-atomic="true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m7 12 3 3 7-7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            @php
                // Los errores con ubicación propia se muestran solo junto a su campo.
                $erroresGenerales = collect($errors->getMessages())->reject(function ($mensajes, $campo) {
                    return in_array($campo, ['categoria', 'fecha', 'turno_id', 'detalles'], true)
                        || preg_match('/^detalles\.0\.(producto_id|coches|latas_adicionales|cantidad|hora_finalizacion|hora_vitrina|forma|foto|observacion|participantes(?:\..+)?)$/', $campo);
                })->flatten()->unique();
            @endphp
            <div data-error-familia="{{ $categoriaSeleccionada }}" class="feedback-produccion feedback-produccion-error" role="alert">
                <strong>No se pudo registrar la producción. Revisa los campos indicados.</strong>
                @if ($erroresGenerales->isNotEmpty())
                    <ul>
                        @foreach ($erroresGenerales as $mensaje)
                            <li>{{ $mensaje }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="categoria-botones">
            @foreach (['pan' => 'Pan', 'torta' => 'Torta', 'bocadito' => 'Bocadito'] as $clave => $nombre)
                <button type="button" class="btn-categoria {{ $categoriaSeleccionada === $clave ? 'activo' : '' }}" data-categoria="{{ $clave }}" aria-pressed="{{ $categoriaSeleccionada === $clave ? 'true' : 'false' }}">{{ $nombre }}</button>
            @endforeach
        </div>
        <noscript><p>Selecciona una familia:
            @foreach (['pan' => 'Pan', 'torta' => 'Torta', 'bocadito' => 'Bocadito'] as $clave => $nombre)
                <a href="{{ route('produccion.index', ['categoria' => $clave, 'fecha' => $fechaSeleccionada, 'turno_id' => $turnoSeleccionadoId]) }}">{{ $nombre }}</a>
            @endforeach
            . Para agregar participantes utiliza JavaScript.</p></noscript>
        @php
            $productosPorFamilia = ['pan' => $productosPan, 'torta' => $productosTorta, 'bocadito' => $productosBocadito];
            $productosSeleccionados = $productosPorFamilia[$categoriaSeleccionada];
            $rutaRegistro = ['pan' => 'produccion.store', 'torta' => 'produccion.torta.store', 'bocadito' => 'produccion.bocadito.store'];
        @endphp
        @error('categoria')
            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
        @enderror

        @if ($productosSeleccionados->isEmpty() || $turnos->isEmpty() || $empleados->isEmpty() || ! $rolesProduccion->contains('nombre_roles_produccion', 'Maestro') || ($categoriaSeleccionada !== 'torta' && ! $rolesProduccion->contains('nombre_roles_produccion', 'Ayudante')))
            <div class="feedback-produccion" role="status">
                <strong>Faltan datos para registrar la producción.</strong>
                <ul>
                    @if ($productosSeleccionados->isEmpty())
                        <li>No hay productos de {{ $nombreCategoria }} disponibles.</li>
                    @endif
                    @if ($turnos->isEmpty())
                        <li>No hay turnos disponibles.</li>
                    @endif
                    @if ($empleados->isEmpty())
                        <li>No hay empleados disponibles.</li>
                    @endif
                    @if (! $rolesProduccion->contains('nombre_roles_produccion', 'Maestro') || ($categoriaSeleccionada !== 'torta' && ! $rolesProduccion->contains('nombre_roles_produccion', 'Ayudante')))
                        <li>{{ $categoriaSeleccionada === 'torta' ? 'Falta el rol Maestro.' : 'Faltan los roles Maestro o Ayudante.' }}</li>
                    @endif
                </ul>
                <p>Solicita que se completen estos datos antes de registrar un producto.</p>
            </div>
        @endif

        @php
            $latasPorCoche = \App\Models\DetallePan::LATAS_POR_COCHE;
        @endphp
        <form id="formulario-produccion" data-latas-por-coche="{{ $latasPorCoche }}" data-turno-manana="{{ $turnoMananaId }}" @if(auth()->user()->esOperador()) data-turno-operador="{{ auth()->user()->turno_id }}" @endif data-pan-url="{{ route('produccion.store') }}" data-torta-url="{{ route('produccion.torta.store') }}" data-bocadito-url="{{ route('produccion.bocadito.store') }}" action="{{ route($rutaRegistro[$categoriaSeleccionada]) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="categoria" id="categoria-seleccionada" value="{{ $categoriaSeleccionada }}">

            @php
                // old() contiene también datos rechazados: no confiar en sus tipos.
                $anterior = old('detalles.0', []);
                $anterior = is_array($anterior) ? $anterior : [];
                $detalle = [];
                foreach (['producto_id', 'coches', 'latas_adicionales', 'cantidad', 'forma', 'hora_finalizacion', 'hora_vitrina', 'observacion'] as $campo) {
                    $valor = $anterior[$campo] ?? (in_array($campo, ['coches', 'latas_adicionales'], true) ? 0 : null);
                    $detalle[$campo] = is_scalar($valor) ? (string) $valor : '';
                }
                $participantesAnteriores = $anterior['participantes'] ?? [];
                $detalle['participantes'] = is_array($participantesAnteriores)
                    ? array_values(array_filter($participantesAnteriores, static function ($participante) {
                        return is_array($participante)
                            && is_scalar($participante['empleado_id'] ?? null)
                            && is_scalar($participante['rol_produccion_id'] ?? null);
                    })) : [];
                $indice = 0;
                $productoAnterior = $categoriaSeleccionada === 'pan' ? $productosPan->firstWhere('id', $detalle['producto_id']) : null;
                $codigoPan = $productoAnterior?->unidadMedida?->codigo;
                $panLata = $categoriaSeleccionada === 'pan' && ($codigoPan === 'LATA' || ! $productoAnterior);
                $cantidadDirecta = $categoriaSeleccionada !== 'pan' || $codigoPan === 'UNIDAD';
            @endphp
            <fieldset class="detalle-pan contenido-produccion" data-indice="0">
                <legend class="solo-lectores">Producto terminado</legend>
                <div class="formulario-card">
                    <div class="formulario-card-header">
                        <h3>Detalles de Producción de {{ $nombreCategoria }}</h3>
                    </div>
                    <hr>
                    @error('detalles')
                        <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                    @enderror
                    <div class="formulario-produccion">
                        <div class="campo">
                            <label for="fecha">Fecha de producción</label>
                            <input type="date" id="fecha" name="fecha" value="{{ $fechaSeleccionada }}" required>
                            @error('fecha')
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="campo {{ $categoriaSeleccionada !== 'pan' ? 'turno-automatico' : '' }}" id="campo-turno">
                            @if (auth()->user()->esOperador())
                            <label for="turno-asignado" data-turno-pan @if($categoriaSeleccionada !== 'pan') hidden @endif>Turno asignado</label>
                            <input id="turno-asignado" data-turno-pan @if($categoriaSeleccionada !== 'pan') hidden @endif type="text" value="{{ auth()->user()->turno?->nombre_turnos ?? 'Sin turno asignado' }}" readonly>
                            <input id="turno" type="hidden" name="turno_id" value="{{ auth()->user()->turno_id }}" @disabled($categoriaSeleccionada !== 'pan')>
                            @if (! auth()->user()->turno)
                                <p class="error-campo" role="alert">Tu cuenta no tiene un turno asignado. Solicita su configuración al Administrador.</p>
                            @endif
                            @else
                            <label for="turno" data-turno-pan @if($categoriaSeleccionada !== 'pan') hidden @endif>Turno</label>
                            <select id="turno" data-turno-pan @if($categoriaSeleccionada !== 'pan') hidden @endif name="turno_id" required @disabled($categoriaSeleccionada !== 'pan')>
                                <option value="">Selecciona un turno</option>
                                @foreach ($turnos as $turno)
                                    @continue(! in_array($turno->nombre_turnos, ['Mañana', 'Noche'], true))
                                    <option value="{{ $turno->id }}" @selected((string) $turnoSeleccionadoId === (string) $turno->id)>{{ $turno->nombre_turnos }}</option>
                                @endforeach
                            </select>
                            @endif
                            <span class="solo-lectores" data-turno-fijo @if($categoriaSeleccionada === 'pan') hidden @endif>Turno: <strong>Mañana</strong></span>
                            <input id="turno-manana" type="hidden" name="turno_id" value="{{ $turnoMananaId }}" @disabled($categoriaSeleccionada === 'pan')>
                            <p data-error-turno class="error-campo" role="alert"></p>
                            @error('turno_id')
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="campo campo-ancho">
                            <label data-label="producto_id" for="detalle-{{ $indice }}-producto_id">Producto {{ $nombreCategoria }}</label>
                            <select data-control="producto_id" data-campo="producto_id" id="detalle-{{ $indice }}-producto_id" name="detalles[{{ $indice }}][producto_id]" required>
                                <option value="">Selecciona un producto</option>
                                @foreach ($productosPorFamilia as $familia => $productos)
                                    @foreach ($productos as $producto)
                                        <option value="{{ $producto->id }}" data-familia="{{ $familia }}" @if ($familia === 'pan') data-unidad-codigo="{{ $producto->unidadMedida?->codigo }}" @if ($producto->unidadMedida?->codigo === 'LATA') data-panes-por-lata="{{ $producto->panes_por_lata }}" @endif @endif @disabled($categoriaSeleccionada !== $familia) @selected($categoriaSeleccionada === $familia && (string) $detalle['producto_id'] === (string) $producto->id)>{{ $producto->nombre_p }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            @error("detalles.$indice.producto_id")
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="campo campo-pan-cantidad campo-condicional" data-mostrar-en="pan" data-pan-registro="LATA" style="display:{{ $panLata ? 'flex' : 'none' }}">
                            <label data-label="coches" for="detalle-{{ $indice }}-coches">Coches</label>
                            <input type="number" data-control="coches" data-campo="coches" id="detalle-{{ $indice }}-coches" name="detalles[{{ $indice }}][coches]" @disabled(! $panLata) min="0" step="1" value="{{ $detalle['coches'] ?? '0' }}">
                            @error("detalles.$indice.coches")
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="campo campo-pan-cantidad campo-condicional" data-mostrar-en="pan" data-pan-registro="LATA" style="display:{{ $panLata ? 'flex' : 'none' }}">
                            <label data-label="latas_adicionales" for="detalle-{{ $indice }}-latas_adicionales">Latas adicionales</label>
                            <input type="number" data-control="latas_adicionales" data-campo="latas_adicionales" id="detalle-{{ $indice }}-latas_adicionales" name="detalles[{{ $indice }}][latas_adicionales]" @disabled(! $panLata) min="0" max="{{ $latasPorCoche - 1 }}" step="1" value="{{ $detalle['latas_adicionales'] ?? '0' }}">
                            @error("detalles.$indice.latas_adicionales")
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="campo campo-ancho resumen-latas campo-condicional" data-mostrar-en="pan" data-pan-registro="LATA" style="display:{{ $panLata ? 'flex' : 'none' }}">
                            <p data-resumen-vacio>Selecciona un producto.</p>
                            <div data-resumen-datos hidden>
                                <div class="resumen-principal"><span>Total de latas</span><output data-total-latas aria-live="polite">0 latas</output></div>
                                <output class="resumen-equivalencia" data-equivalencia-latas></output>
                                <div class="resumen-secundario"><span>Panes por lata <output data-panes-por-lata>—</output></span><span>Total estimado <output data-total-panes aria-live="polite">—</output></span></div>
                            </div>
                        </div>
                        <div class="campo campo-ancho campo-condicional" data-mostrar-en="pan" data-pan-registro="LATA" style="display:{{ $panLata ? 'flex' : 'none' }}">
                            <x-selector-hora campo="hora_finalizacion" etiqueta="Hora de finalización" :valor="$detalle['hora_finalizacion']" :disabled="! $panLata" :error="$errors->first('detalles.0.hora_finalizacion')" />
                            @error('detalles.0.hora_finalizacion')
                                <p id="error-hora_finalizacion" data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="campo campo-condicional" data-mostrar-en="pan torta bocadito" data-pan-registro="UNIDAD" style="display:{{ $cantidadDirecta ? 'flex' : 'none' }}">
                            <label for="detalle-cantidad">Cantidad producida</label>
                            <input type="number" data-campo="cantidad" id="detalle-cantidad" name="detalles[0][cantidad]" min="1" max="2147483647" step="1" required @disabled(! $cantidadDirecta) value="{{ $detalle['cantidad'] }}">
                            @error('detalles.0.cantidad')
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                            <output data-resultado-directo aria-live="polite">{{ $detalle['cantidad'] ? $detalle['cantidad'].' '.($categoriaSeleccionada === 'torta' ? 'tortas' : 'unidades') : '' }}</output>
                        </div>
                        <div class="campo campo-condicional" data-mostrar-en="torta" style="display:{{ $categoriaSeleccionada === 'torta' ? 'flex' : 'none' }}">
                            <x-selector-hora campo="hora_vitrina" etiqueta="Hora de vitrina" :valor="$detalle['hora_vitrina']" :disabled="$categoriaSeleccionada !== 'torta'" :error="$errors->first('detalles.0.hora_vitrina')" />
                            @error('detalles.0.hora_vitrina')
                                <p id="error-hora_vitrina" data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="campo campo-ancho">
                            <span class="campo-etiqueta equipo-titulo">Equipo de trabajo</span>
                            <label for="grupo-produccion" class="solo-lectores">Seleccionar grupo (opcional)</label>
                            <select id="grupo-produccion">
                                <option value="">Seleccionar grupo (opcional)</option>
                                @foreach ($gruposDisponibles as $grupo)
                                    <option value="{{ $grupo->id }}" data-familia="{{ strtolower($grupo->categoria->nombre_categorias) }}" data-empleados="{{ $grupo->integrantes->pluck('id')->prepend($grupo->maestro_id)->toJson() }}" @disabled(strtolower($grupo->categoria->nombre_categorias) !== $categoriaSeleccionada) @if(strtolower($grupo->categoria->nombre_categorias) !== $categoriaSeleccionada) hidden @endif>{{ $grupo->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="participantes-linea">
                                <div class="empleados-tags" data-participantes>
                                    @foreach (array_values((array) ($detalle['participantes'] ?? [])) as $participante)
                                        @php
                                            $empleado = $empleados->firstWhere('id', $participante['empleado_id'] ?? null);
                                            $rol = $rolesProduccion->firstWhere('id', $participante['rol_produccion_id'] ?? null);
                                        @endphp
                                        @if ($empleado && $rol && (int) $empleado->rol_produccion_id === (int) $rol->id && in_array($rol->nombre_roles_produccion, \App\Models\RolProduccion::NOMBRES, true))
                                            <span class="empleado-tag" data-participante data-rol-nombre="{{ $rol->nombre_roles_produccion }}">
                                                {{ $empleado->nombre_empleados }} — {{ $rol->nombre_roles_produccion }}
                                                <button type="button" data-quitar-participante aria-label="Quitar a {{ $empleado->nombre_empleados }}">×</button>
                                                <input type="hidden" data-participante-campo="empleado_id" name="detalles[{{ $indice }}][participantes][{{ $loop->index }}][empleado_id]" value="{{ $empleado->id }}">
                                                <input type="hidden" data-participante-campo="rol_produccion_id" name="detalles[{{ $indice }}][participantes][{{ $loop->index }}][rol_produccion_id]" value="{{ $rol->id }}">
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                                <button type="button" class="btn-agregar-participante" data-abrir-participantes aria-label="Agregar participante" aria-controls="editor-participantes" aria-expanded="false">Agregar participante</button>
                            </div>
                            <div id="editor-participantes" class="popover-empleado" hidden>
                                <label data-label="empleado" for="detalle-{{ $indice }}-empleado">Empleado</label>
                                <select data-control="empleado" id="detalle-{{ $indice }}-empleado">
                                    <option value="">Selecciona un empleado</option>
                                    @foreach ($empleados as $empleado)
                                        <option value="{{ $empleado->id }}" data-rol-id="{{ $empleado->rol_produccion_id }}" data-nombre="{{ $empleado->nombre_empleados }} · #{{ $empleado->id }}" data-categorias="{{ implode(' ', array_unique($categoriasPorEmpleado[$empleado->id] ?? [])) }}">{{ $empleado->nombre_empleados }} · #{{ $empleado->id }} · {{ $empleado->rolProduccion->nombre_roles_produccion }}</option>
                                    @endforeach
                                </select>
                                <label data-label="rol" for="detalle-{{ $indice }}-rol">Rol actual</label>
                                <select data-control="rol" id="detalle-{{ $indice }}-rol" disabled aria-describedby="ayuda-rol-actual">
                                    <option value="">Selecciona un empleado</option>
                                    @foreach ($rolesProduccion as $rol)
                                        @if (in_array($rol->nombre_roles_produccion, \App\Models\RolProduccion::NOMBRES, true))
                                            <option value="{{ $rol->id }}" data-rol-nombre="{{ $rol->nombre_roles_produccion }}">{{ $rol->nombre_roles_produccion }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <small id="ayuda-rol-actual">Rol administrado en Personal. Practicantes adicionales.</small>
                                <button type="button" data-agregar-participante>Agregar participante</button>
                            </div>
                            <p class="solo-lectores" data-estado-participantes aria-live="polite"></p>
                            <p class="error-campo" data-error-detalle role="alert"></p>
                            @php
                                $erroresParticipantes = collect($errors->get("detalles.$indice.participantes"))
                                    ->merge(collect($errors->get("detalles.$indice.participantes.*"))->flatten())
                                    ->unique();
                            @endphp
                            @if ($erroresParticipantes->isNotEmpty())
                                <div data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">
                                    <ul>
                                        @foreach ($erroresParticipantes as $mensaje)
                                            <li>{{ $mensaje }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                        <details class="observacion-opcional campo-ancho" data-observacion @if(($detalle['observacion'] ?? '') !== '' || $errors->has("detalles.$indice.observacion")) open @endif>
                            <summary><span class="observacion-abrir">Añadir observación</span><span class="observacion-cerrar">Observación</span><span class="observacion-icono" aria-hidden="true">+</span></summary>
                            <div class="campo">
                            <label class="solo-lectores" data-label="observacion" for="detalle-{{ $indice }}-observacion">Observaciones</label>
                            <textarea rows="2" data-control="observacion" data-campo="observacion" id="detalle-{{ $indice }}-observacion" name="detalles[{{ $indice }}][observacion]" placeholder="Notas adicionales o novedades del lote...">{{ $detalle['observacion'] ?? '' }}</textarea>
                            @error("detalles.$indice.observacion")
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        </details>
                    </div>
                    <div class="formulario-botones">
                        <button type="button" id="btn-cancelar-form">Cancelar</button>
                        <button type="submit" @disabled(auth()->user()->esOperador() && ! auth()->user()->turno_id)><span data-texto-guardar>Guardar</span><span data-carga-guardar hidden><span class="indicador-carga" aria-hidden="true"></span> Guardando…</span></button>
                        <span class="solo-lectores" data-estado-guardado role="status" aria-live="polite"></span>
                    </div>
                </div>
                <aside class="panel-info campo-condicional" data-mostrar-en="torta" style="display:{{ $categoriaSeleccionada === 'torta' ? 'block' : 'none' }}" aria-labelledby="titulo-info-torta">
                    <h3 id="titulo-info-torta" class="panel-info-etiqueta">Información adicional</h3>
                    <div class="campo">
                        <label for="detalle-forma">Forma de Torta</label>
                        <div class="forma-opciones" role="group" aria-label="Formas habituales">
                            @foreach (['circular' => 'Circular', 'rectangular' => 'Rectangular'] as $valor => $etiqueta)
                                <button type="button" class="btn-forma {{ strtolower($detalle['forma']) === $valor ? 'activo' : '' }}" data-forma="{{ $valor }}" aria-pressed="{{ strtolower($detalle['forma']) === $valor ? 'true' : 'false' }}" @disabled($categoriaSeleccionada !== 'torta')>{{ $etiqueta }}</button>
                            @endforeach
                        </div>
                        <input type="text" data-campo="forma" id="detalle-forma" name="detalles[0][forma]" maxlength="30" placeholder="Selecciona o escribe otra forma" required @disabled($categoriaSeleccionada !== 'torta') value="{{ $detalle['forma'] }}">
                        @error('detalles.0.forma')
                            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="campo campo-foto">
                        <span class="campo-etiqueta">Foto de la torta <small>(opcional)</small></span>
                        <div class="foto-upload">
                            <span data-icono-foto aria-hidden="true"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 5-5 4 4 4-6 5 7"/></svg></span>
                            <img data-preview-foto hidden alt="Vista previa de la torta seleccionada">
                            <p data-nombre-foto aria-live="polite">Tomar o subir foto</p>
                            <small>JPG / PNG / WEBP · Máximo 2 MB</small>
                            <div class="foto-acciones">
                                <label class="btn-foto" for="detalle-foto" data-label-foto>Subir foto</label>
                                <label class="btn-foto" for="detalle-foto-camara">Tomar foto</label>
                                <button type="button" class="btn-foto" data-quitar-foto hidden @disabled($categoriaSeleccionada !== 'torta')>Quitar foto</button>
                            </div>
                            <input class="solo-lectores" type="file" data-campo="foto" id="detalle-foto" name="detalles[0][foto]" accept="image/jpeg,image/png,image/webp" @disabled($categoriaSeleccionada !== 'torta')>
                            <input class="solo-lectores" type="file" data-campo="foto_camara" id="detalle-foto-camara" accept="image/jpeg,image/png,image/webp" capture="environment" @disabled($categoriaSeleccionada !== 'torta')>
                        </div>
                        <small>Si falla el registro, vuelve a seleccionar la foto.</small>
                        @error('detalles.0.foto')
                            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </aside>
            </fieldset>
        </form>

        <section class="registros-produccion" aria-labelledby="titulo-produccion-registrada" data-consulta-registros>
            <div class="titulo-historial"><h2 id="titulo-produccion-registrada">Producción registrada</h2></div>
            <form class="filtros-registros" method="GET" action="{{ route('history.index') }}" data-filtros-registros data-consulta-url="{{ route('produccion.registros') }}">
                <div class="campo"><label for="registros-fecha">Fecha</label><input type="date" id="registros-fecha" name="fecha_inicio" value="{{ $filtrosRegistros['fecha_inicio'] }}" required><input type="hidden" name="fecha_fin" value="{{ $filtrosRegistros['fecha_fin'] }}"></div>
                <div class="campo"><label for="registros-turno">Turno</label><select id="registros-turno" name="turno_id"><option value="">Todos</option>@foreach($turnos as $opcion)@continue(!in_array($opcion->nombre_turnos,['Mañana','Noche'],true))<option value="{{ $opcion->id }}" @selected($filtrosRegistros['turno_id'] === (string)$opcion->id)>{{ $opcion->nombre_turnos }}</option>@endforeach</select></div>
                <div class="campo"><label for="registros-categoria">Categoría</label><select id="registros-categoria" name="categoria"><option value="">Todas</option>@foreach(['pan'=>'Pan','torta'=>'Torta','bocadito'=>'Bocadito'] as $valor=>$nombre)<option value="{{ $valor }}" @selected($filtrosRegistros['categoria'] === $valor)>{{ $nombre }}</option>@endforeach</select></div>
                <button type="submit" class="btn-consultar">Consultar</button>
            </form>
            <p class="solo-lectores" data-estado-consulta role="status"></p>
            <p class="error-campo" data-error-consulta role="alert" hidden></p>
            <div class="esqueleto-listado" data-esqueleto hidden aria-hidden="true"><span></span><span></span></div>
            @if($errorConsulta)<p class="feedback-produccion feedback-produccion-error" role="alert">{{ $errorConsulta }}</p>@endif
            @if($cabecerasDuplicadas && !$errors->has('produccion'))<p class="feedback-produccion feedback-produccion-error" role="alert">Existen varias sesiones de {{ $nombreCategoria }} para esta fecha y turno. Revise las cabeceras duplicadas antes de registrar otro producto.</p>@endif
            @include('produccion.registros')
        </section>

    </div>

    </body>
    </html>
