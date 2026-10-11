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
            <div class="feedback-produccion feedback-produccion-exito" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            @php
                // Los errores con ubicación propia se muestran solo junto a su campo.
                $erroresGenerales = collect($errors->getMessages())->reject(function ($mensajes, $campo) {
                    return in_array($campo, ['categoria', 'fecha', 'turno_id', 'detalles'], true)
                        || preg_match('/^detalles\.0\.(producto_id|coches|latas_adicionales|cantidad|forma|foto|observacion|participantes(?:\..+)?)$/', $campo);
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

        @if ($productosSeleccionados->isEmpty() || $turnos->isEmpty() || $empleados->isEmpty() || ! $rolesProduccion->contains('nombre_roles_produccion', 'Maestro') || ! $rolesProduccion->contains('nombre_roles_produccion', 'Ayudante'))
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
                    @if (! $rolesProduccion->contains('nombre_roles_produccion', 'Maestro') || ! $rolesProduccion->contains('nombre_roles_produccion', 'Ayudante'))
                        <li>Faltan los roles Maestro o Ayudante.</li>
                    @endif
                </ul>
                <p>Solicita que se completen estos datos antes de registrar un producto.</p>
            </div>
        @endif

        @php
            $latasPorCoche = \App\Models\DetallePan::LATAS_POR_COCHE;
        @endphp
        <form id="formulario-produccion" data-latas-por-coche="{{ $latasPorCoche }}" data-pan-url="{{ route('produccion.store') }}" data-torta-url="{{ route('produccion.torta.store') }}" data-bocadito-url="{{ route('produccion.bocadito.store') }}" action="{{ route($rutaRegistro[$categoriaSeleccionada]) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="categoria" id="categoria-seleccionada" value="{{ $categoriaSeleccionada }}">

            <div class="contenido-produccion">

                <div class="formulario-card">
                    <div class="formulario-card-header">
                        <h3>Detalles de Producción de {{ $nombreCategoria }}</h3>
                        <span class="estado-activo">Activo</span>
                    </div>
                    <hr>

                    <div class="formulario-produccion">

                        <div class="campo">
                            <label for="fecha">Fecha de producción</label>
                            <input type="date" id="fecha" name="fecha" value="{{ $fechaSeleccionada }}" required>
                            @error('fecha')
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="campo" id="campo-turno">
                            <label for="turno">Turno</label>
                            <select id="turno" name="turno_id" required>
                                <option value="">Selecciona un turno</option>
                                @foreach ($turnos as $turno)
                                    <option value="{{ $turno->id }}" @selected((string) $turnoSeleccionadoId === (string) $turno->id)>{{ $turno->nombre_turnos }}</option>
                                @endforeach
                            </select>
                            @error('turno_id')
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="campo-ancho">
                            @error('detalles')
                                <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                            @enderror
                            <button type="button" id="consultar-produccion" data-url="{{ route('produccion.index') }}">Ver producción de esta fecha y turno</button>
                            <p class="campo-condicional" data-mostrar-en="pan" style="display:{{ $categoriaSeleccionada === 'pan' ? 'block' : 'none' }}">1 coche = {{ $latasPorCoche }} latas. Cada producto tiene su propia observación y participantes.</p>
                            @php
                                // old() contiene también datos rechazados: no confiar en sus tipos.
                                $anterior = old('detalles.0', []);
                                $anterior = is_array($anterior) ? $anterior : [];
                                $detalle = [];
                                foreach (['producto_id', 'coches', 'latas_adicionales', 'cantidad', 'forma', 'observacion'] as $campo) {
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
                            @endphp
                            <fieldset class="detalle-pan" data-indice="0">
                                <legend>Producto terminado</legend>
                                <div class="formulario-produccion">
                                    <div class="campo campo-ancho">
                                        <label data-label="producto_id" for="detalle-{{ $indice }}-producto_id">Producto {{ $nombreCategoria }}</label>
                                        <select data-control="producto_id" data-campo="producto_id" id="detalle-{{ $indice }}-producto_id" name="detalles[{{ $indice }}][producto_id]" required>
                                            <option value="">Selecciona un producto</option>
                                            @foreach ($productosPorFamilia as $familia => $productos)
                                                @foreach ($productos as $producto)
                                                    <option value="{{ $producto->id }}" data-familia="{{ $familia }}" @disabled($categoriaSeleccionada !== $familia) @selected($categoriaSeleccionada === $familia && (string) $detalle['producto_id'] === (string) $producto->id)>{{ $producto->nombre_p }}</option>
                                                @endforeach
                                            @endforeach
                                        </select>
                                        @error("detalles.$indice.producto_id")
                                            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="campo campo-condicional" data-mostrar-en="pan" style="display:{{ $categoriaSeleccionada === 'pan' ? 'flex' : 'none' }}">
                                        <label data-label="coches" for="detalle-{{ $indice }}-coches">Coches</label>
                                        <input type="number" data-control="coches" data-campo="coches" id="detalle-{{ $indice }}-coches" name="detalles[{{ $indice }}][coches]" @disabled($categoriaSeleccionada !== 'pan') min="0" step="1" value="{{ $detalle['coches'] ?? '0' }}">
                                        @error("detalles.$indice.coches")
                                            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="campo campo-condicional" data-mostrar-en="pan" style="display:{{ $categoriaSeleccionada === 'pan' ? 'flex' : 'none' }}">
                                        <label data-label="latas_adicionales" for="detalle-{{ $indice }}-latas_adicionales">Latas adicionales</label>
                                        <input type="number" data-control="latas_adicionales" data-campo="latas_adicionales" id="detalle-{{ $indice }}-latas_adicionales" name="detalles[{{ $indice }}][latas_adicionales]" @disabled($categoriaSeleccionada !== 'pan') min="0" max="{{ $latasPorCoche - 1 }}" step="1" value="{{ $detalle['latas_adicionales'] ?? '0' }}">
                                        <small>0 a {{ $latasPorCoche - 1 }} latas, además de los coches.</small>
                                        @error("detalles.$indice.latas_adicionales")
                                            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="campo campo-ancho campo-condicional" data-mostrar-en="pan" style="display:{{ $categoriaSeleccionada === 'pan' ? 'flex' : 'none' }}">
                                        <span class="total-latas-etiqueta">Total de latas</span>
                                        <output data-total-latas aria-live="polite">0 latas en total</output>
                                        <small>Ingresa al menos un coche o una lata adicional.</small>
                                    </div>
                                    <div class="campo campo-condicional" data-mostrar-en="torta bocadito" style="display:{{ $categoriaSeleccionada !== 'pan' ? 'flex' : 'none' }}">
                                        <label for="detalle-cantidad">Cantidad (unidades)</label>
                                        <input type="number" data-campo="cantidad" id="detalle-cantidad" name="detalles[0][cantidad]" min="1" max="2147483647" step="1" required @disabled($categoriaSeleccionada === 'pan') value="{{ $detalle['cantidad'] }}">
                                        @error('detalles.0.cantidad')
                                            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="campo campo-condicional" data-mostrar-en="torta" style="display:{{ $categoriaSeleccionada === 'torta' ? 'flex' : 'none' }}">
                                        <label for="detalle-forma">Forma de Torta</label>
                                        <input type="text" data-campo="forma" id="detalle-forma" name="detalles[0][forma]" maxlength="30" placeholder="Circular, rectangular…" required @disabled($categoriaSeleccionada !== 'torta') value="{{ $detalle['forma'] }}">
                                        @error('detalles.0.forma')
                                            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="campo campo-ancho campo-condicional" data-mostrar-en="torta" style="display:{{ $categoriaSeleccionada === 'torta' ? 'flex' : 'none' }}">
                                        <label for="detalle-foto">Foto de Torta (opcional)</label>
                                        <input type="file" data-campo="foto" id="detalle-foto" name="detalles[0][foto]" accept="image/jpeg,image/png,image/webp" @disabled($categoriaSeleccionada !== 'torta')>
                                        <small>JPEG, PNG o WebP. Máximo 2 MB. Si hay un error, vuelve a seleccionar el archivo.</small>
                                        @error('detalles.0.foto')
                                            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="campo campo-ancho">
                                        <label data-label="observacion" for="detalle-{{ $indice }}-observacion">Observación de este producto</label>
                                        <textarea data-control="observacion" data-campo="observacion" id="detalle-{{ $indice }}-observacion" name="detalles[{{ $indice }}][observacion]" placeholder="Notas adicionales o novedades del lote...">{{ $detalle['observacion'] ?? '' }}</textarea>
                                        @error("detalles.$indice.observacion")
                                            <p data-error-familia="{{ $categoriaSeleccionada }}" class="error-campo" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="campo campo-ancho">
                                        <p><strong>Participantes</strong><br>Este producto requiere exactamente 1 Maestro y mínimo 1 Ayudante.</p>
                                        <div class="empleados-tags" data-participantes>
                                            @foreach (array_values((array) ($detalle['participantes'] ?? [])) as $participante)
                                                @php
                                                    $empleado = $empleados->firstWhere('id', $participante['empleado_id'] ?? null);
                                                    $rol = $rolesProduccion->firstWhere('id', $participante['rol_produccion_id'] ?? null);
                                                @endphp
                                                @if ($empleado && $rol && in_array($rol->nombre_roles_produccion, ['Maestro', 'Ayudante'], true))
                                                    <span class="empleado-tag" data-participante data-rol-nombre="{{ $rol->nombre_roles_produccion }}">
                                                        {{ $empleado->nombre_empleados }} — {{ $rol->nombre_roles_produccion }}
                                                        <button type="button" data-quitar-participante aria-label="Quitar a {{ $empleado->nombre_empleados }}">×</button>
                                                        <input type="hidden" data-participante-campo="empleado_id" name="detalles[{{ $indice }}][participantes][{{ $loop->index }}][empleado_id]" value="{{ $empleado->id }}">
                                                        <input type="hidden" data-participante-campo="rol_produccion_id" name="detalles[{{ $indice }}][participantes][{{ $loop->index }}][rol_produccion_id]" value="{{ $rol->id }}">
                                                    </span>
                                                @endif
                                            @endforeach
                                    </div>
                                        <div class="popover-empleado" style="display:flex;">
                                            <label data-label="empleado" for="detalle-{{ $indice }}-empleado">Empleado</label>
                                            <select data-control="empleado" id="detalle-{{ $indice }}-empleado">
                                                <option value="">Selecciona un empleado</option>
                                                @foreach ($empleados as $empleado)
                                                    <option value="{{ $empleado->id }}">{{ $empleado->nombre_empleados }}</option>
                                                @endforeach
                                            </select>
                                            <label data-label="rol" for="detalle-{{ $indice }}-rol">Rol de producción</label>
                                            <select data-control="rol" id="detalle-{{ $indice }}-rol">
                                                <option value="">Selecciona un rol</option>
                                                @foreach ($rolesProduccion as $rol)
                                                    @if (in_array($rol->nombre_roles_produccion, ['Maestro', 'Ayudante'], true))
                                                        <option value="{{ $rol->id }}" data-rol-nombre="{{ $rol->nombre_roles_produccion }}">{{ $rol->nombre_roles_produccion }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <button type="button" data-agregar-participante>Agregar participante</button>
                                        </div>
                                        <p data-estado-participantes aria-live="polite"></p>
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
                                </div>
                            </fieldset>
                        </div>
                    </div>

                    <div class="formulario-botones">
                        <button type="button" id="btn-cancelar-form">Limpiar producto</button>
                        <button type="submit">Registrar producto</button>
                    </div>
                </div>

            </div>
        </form>

        <section aria-labelledby="titulo-produccion-registrada" data-registros-familia="{{ $categoriaSeleccionada }}">
            <div class="titulo-historial">
                <h2 id="titulo-produccion-registrada">Producción registrada{{ $turnoSeleccionado ? ' — Turno '.$turnoSeleccionado->nombre_turnos : '' }}</h2>
            </div>
            @if ($errorConsulta)
                <p data-error-familia="{{ $categoriaSeleccionada }}" class="feedback-produccion feedback-produccion-error" role="alert">{{ $errorConsulta }}</p>
            @elseif ($cabecerasDuplicadas)
                @if (! $errors->has('produccion'))
                    <p data-error-familia="{{ $categoriaSeleccionada }}" class="feedback-produccion feedback-produccion-error" role="alert">Existen varias sesiones de {{ $nombreCategoria }} para esta fecha y turno. Revise las cabeceras duplicadas antes de registrar otro producto.</p>
                @endif
            @elseif (! $turnoSeleccionado)
                <p>Selecciona una fecha y un turno para consultar su producción.</p>
            @else
                <p>Fecha: {{ $fechaSeleccionada }}</p>
                <div class="contenedor-produccion" id="contenedor-produccion">
                    @forelse ($detallesRegistrados as $registro)
                        <article class="tarjeta-produccion" data-detalle-registrado="{{ $registro->id }}">
                            <div class="tarjeta-header">
                                <span class="tarjeta-categoria">{{ $nombreCategoria }}</span>
                                <span class="tarjeta-fecha">{{ $fechaSeleccionada }} — Turno {{ $turnoSeleccionado->nombre_turnos }}</span>
                            </div>
                            <div class="tarjeta-body">
                                <h3>{{ $registro->producto->nombre_p }}</h3>
                                @if ($categoriaSeleccionada === 'pan')
                                <p>{{ intdiv($registro->cantidad, $latasPorCoche) }} coches + {{ $registro->cantidad % $latasPorCoche }} latas ({{ $registro->cantidad }} latas en total)</p>
                                @else
                                    <p>{{ $registro->cantidad }} unidades</p>
                                    @if ($categoriaSeleccionada === 'torta')
                                        <p>Forma: {{ $registro->forma }}</p>
                                        @if ($registro->foto)
                                            <a href="{{ asset('storage/'.$registro->foto) }}" target="_blank" rel="noopener">Ver foto</a>
                                        @endif
                                    @endif
                                @endif
                                <div class="tarjeta-empleados">
                                    @foreach ($registro->empleados as $participanteRegistrado)
                                        @php
                                            $rolRegistrado = $rolesProduccion->firstWhere('id', $participanteRegistrado->pivot->rol_produccion_id);
                                        @endphp
                                        <span class="empleado-tag">{{ $rolRegistrado?->nombre_roles_produccion }}: {{ $participanteRegistrado->nombre_empleados }}</span>
                                    @endforeach
                                </div>
                                @if ($registro->observacion !== null && $registro->observacion !== '')
                                    <p class="tarjeta-observacion">Observación: {{ $registro->observacion }}</p>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p>Todavía no hay productos registrados para esta fecha y turno.</p>
                    @endforelse
                </div>
            @endif
        </section>

    </div>

    </body>
    </html>
