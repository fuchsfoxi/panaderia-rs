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
                        <select id="turno" name="turno_id">
                            <option value="1">Mañana</option>
                            <option value="2">Noche</option>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="producto">Producto</label>
                        <select id="producto" name="producto_id">
                            <option value="">-- Selecciona un producto --</option>
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
                                <select id="select-empleado">
                                    <option value="Carlos M.">Carlos M.</option>
                                    <option value="Ana R.">Ana R.</option>
                                    <option value="Rosa P.">Rosa P.</option>
                                    <option value="Luis F.">Luis F.</option>
                                    <option value="Marta S.">Marta S.</option>
                                </select>
                                <select id="select-rol">
                                    <option value="Maestro">Maestro</option>
                                    <option value="Ayudante">Ayudante</option>
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

    <div class="contenedor-produccion" id="contenedor-produccion">

        <div class="tarjeta-produccion">
            <div class="tarjeta-header">
                <span class="tarjeta-categoria">Pan</span>
                <span class="tarjeta-fecha">23 Oct 2026 — Turno Mañana</span>
            </div>
            <div class="tarjeta-body">
                <h3>Pan Carioca</h3>
                <p>3 coches</p>
                <div class="tarjeta-empleados">
                    <span class="empleado-tag">Carlos M. — Maestro</span>
                    <span class="empleado-tag">Ana R. — Ayudante</span>
                </div>
            </div>
        </div>

        <div class="tarjeta-produccion">
            <div class="tarjeta-header">
                <span class="tarjeta-categoria">Torta</span>
                <span class="tarjeta-fecha">23 Oct 2026 — Turno Único</span>
            </div>
            <div class="tarjeta-body">
                <h3>Torta de Chocolate</h3>
                <p>Forma: Circular</p>
                <div class="tarjeta-empleados">
                    <span class="empleado-tag">Rosa P. — Responsable</span>
                </div>
            </div>
        </div>

        <div class="tarjeta-produccion">
            <div class="tarjeta-header">
                <span class="tarjeta-categoria">Bocadito</span>
                <span class="tarjeta-fecha">22 Oct 2026 — Turno Único</span>
            </div>
            <div class="tarjeta-body">
                <h3>Alfajorcitos</h3>
                <p>150 unidades</p>
                <div class="tarjeta-empleados">
                    <span class="empleado-tag">Luis F. — Maestro</span>
                    <span class="empleado-tag">Marta S. — Ayudante</span>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>