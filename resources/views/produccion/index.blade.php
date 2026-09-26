    <!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producción</title>
    @vite(['resources/css/produccion.css', 'resources/js/produccion.js'])
    </head>
    <body>

    <div class="pagina-produccion">

        <div class="titulo-produccion">
            <h2>Sistema Panadería</h2>
            <h1>INGRESO DE PRODUCCIÓN</h1>
        </div>

        <div class="categoria-botones">
            <button type="button" class="btn-categoria activo" data-categoria="pan">Pan</button>
            <button type="button" class="btn-categoria" data-categoria="torta">Torta</button>
            <button type="button" class="btn-categoria" data-categoria="bocadito">Bocadito</button>
        </div>

        <form action="{{ route('produccion.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="categoria" id="categoria-seleccionada" value="pan">

            <div class="contenido-produccion">

                <div class="formulario-card">
                    <div class="formulario-card-header">
                        <h3>Detalles de Producción de Pan</h3>
                        <span class="estado-activo">Activo</span>
                    </div>
                    <hr>

                    <div class="formulario-produccion">

                        <div class="campo">
                            <label for="fecha">Fecha</label>
                            <input type="date" id="fecha" name="fecha">
                        </div>

                        <div class="campo campo-condicional" data-mostrar-en="pan" id="campo-turno">
                            <label for="turno">Turno</label>
                            {{-- MODIFICADO: las <option> "1 = Mañana / 2 = Noche"
                                 estaban fijas. Ahora salen de la tabla turnos y el
                                 value es el id real. --}}
                            <select id="turno" name="turno_id">
                                @foreach ($turnos as $turno)
                                    <option value="{{ $turno->id }}">{{ $turno->nombre_turnos }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="campo">
                            <label for="producto">Producto</label>
                            {{-- MODIFICADO: el select estaba vacio (solo el
                                 placeholder). Ahora se listan los productos
                                 activos de la base, ordenados por nombre. --}}
                            <select id="producto" name="producto_id">
                                <option value="">-- Selecciona un producto --</option>
                                @forelse ($productos as $producto)
                                    <option value="{{ $producto->id }}">{{ $producto->nombre_p }}</option>
                                @empty
                                    <option value="" disabled>-- No hay productos cargados --</option>
                                @endforelse
                            </select>
                        </div>

                        <div class="campo campo-condicional" data-mostrar-en="pan" id="campo-cantidad-pan">
                            <label for="cantidad_pan">Cantidad (Coches)</label>
                            <div class="campo-cantidad">
                                <input type="number" id="cantidad_pan" name="cantidad_coches" min="0">
                                <span>coches</span>
                            </div>
                        </div>

                        <div class="campo campo-condicional" data-mostrar-en="bocadito" id="campo-cantidad-bocadito">
                            <label for="cantidad_bocadito">Cantidad (Unidades)</label>
                            <div class="campo-cantidad">
                                <input type="number" id="cantidad_bocadito" name="cantidad_unidades" min="0">
                                <span>unidades</span>
                            </div>
                        </div>

                        <div class="campo campo-ancho">
                            <label>Maestro / Ayudante encargado</label>
                            <div class="empleados-tags" id="empleados-tags">
                                <button type="button" id="agregar-empleado">+</button>

                                <div class="popover-empleado" id="popover-empleado" style="display:none;">
                                    {{-- MODIFICADO: los 5 empleados y 2 roles estaban
                                         escritos a mano. Ahora salen de la base y el
                                         value es el id (la FK que necesita la tabla
                                         pivote), no el nombre. --}}
                                    <select id="select-empleado">
                                        @forelse ($empleados as $empleado)
                                            <option value="{{ $empleado->id }}">{{ $empleado->nombre_empleados }}</option>
                                        @empty
                                            <option value="" disabled>-- No hay empleados --</option>
                                        @endforelse
                                    </select>
                                    <select id="select-rol">
                                        @forelse ($rolesProduccion as $rol)
                                            <option value="{{ $rol->id }}">{{ $rol->nombre_roles_produccion }}</option>
                                        @empty
                                            <option value="" disabled>-- No hay roles --</option>
                                        @endforelse
                                    </select>
                                    <button type="button" id="confirmar-empleado">Agregar</button>
                                </div>
                            </div>
                            <div id="empleados-inputs"></div>
                        </div>

                        <div class="campo campo-ancho">
                            <label for="observaciones">Observaciones</label>
                            <textarea id="observaciones" name="observaciones" placeholder="Notas adicionales o novedades del lote..."></textarea>
                        </div>

                    </div>

                    <div class="formulario-botones">
                        <button type="button" id="btn-cancelar-form">Cancelar</button>
                        <button type="submit">Guardar</button>
                    </div>
                </div>

                <div class="panel-info campo-condicional" data-mostrar-en="torta" id="panel-info-torta">
                    <div class="panel-info-etiqueta">Información Adicional</div>
                    <h3>Variación de Categoría</h3>
                    <p>Al seleccionar la categoría <strong>Torta</strong>, el formulario despliega estos campos específicos, incluyendo una foto obligatoria:</p>
                    <hr>

                    <h4>Forma de Torta</h4>
                    <div class="forma-opciones">
                        <button type="button" class="btn-forma" data-forma="circular">Circular</button>
                        <button type="button" class="btn-forma" data-forma="rectangular">Rectang.</button>
                    </div>
                    <input type="hidden" name="forma" id="forma-seleccionada">

                    <h4>Subir foto (Obligatorio)</h4>
                    <label for="foto" class="foto-upload">
                        📷<br>Agregar foto
                    </label>
                    <input type="file" id="foto" name="foto" accept="image/*" style="display:none;">
                </div>

            </div>
        </form>

        <div class="titulo-historial">
            <h2>Registrado recientemente</h2>
        </div>

        {{-- MODIFICADO: este bloque reemplaza las 3 tarjetas de prueba fijas
             ("Pan Carioca / 3 coches / 23 Oct", "Torta de Chocolate / Circular",
             "Alfajorcitos / 150 unidades"), que estaban escritas en el HTML.

             Ahora se recorre $lineas, que ProduccionController obtiene de la
             base con LineasProduccion (limite: 6 lineas, con los empleados
             que participaron en cada una).

             Cada $linea tiene claves fijas: fecha, producto, categoria,
             cantidad, unidad, turno, forma, empleados. --}}
        <div class="contenedor-produccion" id="contenedor-produccion">
            @forelse ($lineas as $linea)
                <div class="tarjeta-produccion">
                    <div class="tarjeta-header">
                        <span class="tarjeta-categoria">{{ $linea->categoria }}</span>
                        <span class="tarjeta-fecha">
                            {{ date('d M Y', strtotime($linea->fecha)) }}
                            — Turno {{ $linea->turno ?? 'Único' }}
                        </span>
                    </div>
                    <div class="tarjeta-body">
                        <h3>{{ $linea->producto }}</h3>

                        @if ($linea->tipo === 'torta')
                            {{-- Una torta es un registro, no lleva cantidad: se
                                 muestra la forma. --}}
                            <p>Forma: {{ ucfirst($linea->forma ?? 'sin definir') }}</p>
                        @else
                            {{-- Pan y bocadito llevan cantidad. El pan usa la
                                 unidad de medida real; el bocadito no tiene
                                 columna unidad_medida_id en el esquema, asi que
                                 se rotula "unidades". --}}
                            <p>{{ rtrim(rtrim(number_format($linea->cantidad, 2, ',', '.'), '0'), ',') }}
                                {{ $linea->tipo === 'pan' ? $linea->unidad : 'unidades' }}</p>
                        @endif

                        {{-- @forelse tambien para los empleados: una linea puede
                             no tener ninguno asignado. --}}
                        <div class="tarjeta-empleados">
                            @forelse ($linea->empleados as $empleado)
                                <span class="empleado-tag">{{ $empleado->nombre }} — {{ $empleado->rol }}</span>
                            @empty
                                <span class="empleado-tag">Sin empleados asignados</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            @empty
                {{-- Caso sin datos: mensaje amigable en vez de un bloque vacio. --}}
                <div class="tarjeta-produccion">
                    <div class="tarjeta-body">
                        <h3>Todavía no hay producción registrada</h3>
                        <p>Guardá un lote con el formulario de arriba y aparecerá en esta lista.</p>
                    </div>
                </div>
            @endforelse
        </div>

    </div>

    </body>
    </html>