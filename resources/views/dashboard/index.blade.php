<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Panificadora Amazónica</title>
@vite(['resources/css/dashboard.css', 'resources/js/dashboard.js'])
</head>
<body>

<nav class="barra-lateral">
    <a href="#" class="avatar"><i class="fas fa-user-circle"></i></a>
    <div class="nav-iconos">
        <a href="#" class="activo"><i class="fas fa-home"></i></a>
        <a href="#"><i class="fas fa-clipboard-list"></i></a>
        <a href="#"><i class="fas fa-receipt"></i></a>
        <a href="#"><i class="fas fa-history"></i></a>
    </div>
    <a href="#" class="cerrar-sesion"><i class="fas fa-sign-out-alt"></i></a>
</nav>

<div class="contenido_informacion">

    <div class="encabezado-superior">
        <div class="titulo_inicio">
            <h1>DASHBOARD DE PRODUCCIÓN</h1>
        </div>

        <div class="filtro-fecha-inicio">
            <div class="filtro-fecha_desde">
                <label for="fecha_inicio">Desde</label>
                <i class="far fa-calendar-alt"></i>
                <input type="date" id="fecha_inicio" name="fecha_inicio">
            </div>

            <div class="filtro_fecha_hasta">
                <label for="fecha_fin">Hasta</label>
                <i class="far fa-calendar-alt"></i>
                <input type="date" id="fecha_fin" name="fecha_fin">
            </div>

            <div class="filtro_fecha_boton">
                <button type="button" id="filtrar">Aplicar</button>
            </div>
        </div>
    </div>

    <div class="encabezado_periodo_1">
        <strong>HOY</strong>
        Producción | MAÑANA
    </div>

    <div class="fila_categorias">
        <div class="tarjeta_categoria">
            <img src="{{ asset('images/placeholder-pan.jpg') }}" alt="Pan" class="imagen_categoria">
            <div class="valor_categoria">
                12.5<span>Coches</span>
            </div>
            <button type="button" class="btn-detalles" data-abrir-modal="modal-pan">Detalles <i class="fas fa-arrow-right"></i></button>
        </div>

        <div class="tarjeta_categoria">
            <img src="{{ asset('images/placeholder-torta.jpg') }}" alt="Torta" class="imagen_categoria">
            <div class="valor_categoria">
                15<span>Unidades</span>
            </div>
            <button type="button" class="btn-detalles" data-abrir-modal="modal-torta">Detalles <i class="fas fa-arrow-right"></i></button>
        </div>

        <div class="tarjeta_categoria">
            <img src="{{ asset('images/placeholder-bocadito.jpg') }}" alt="Bocadito" class="imagen_categoria">
            <div class="valor_categoria">
                900<span>Unidades</span>
            </div>
            <button type="button" class="btn-detalles" data-abrir-modal="modal-bocadito">Detalles <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <div class="encabezado_periodo_2">
        <strong>HOY</strong>
        Producción | MAÑANA
    </div>

    <div class="cartas-general">
        <div class="carta">
            <h2>Producción Total</h2>
            <p id="produccion_total">0</p>
        </div>

        <div class="carta">
            <h2>Producción Pendiente</h2>
            <p id="produccion_pendiente">0</p>
        </div>

        <div class="carta">
            <h2>Producción Completada</h2>
            <p id="produccion_completada">0</p>
        </div>
    </div>

    <div class="graficos">
        <div class="grafico">
            <h2>Producción por Día</h2>
            <canvas id="grafico_dia"></canvas>
        </div>

        <div class="grafico">
            <h2>Producción por Semana</h2>
            <canvas id="grafico_semana"></canvas>
        </div>

        <div class="grafico">
            <h2>Producción por Mes</h2>
            <canvas id="grafico_mes"></canvas>
        </div>
    </div>

</div>

<!-- Modal: detalle de Pan -->
<div class="overlay-modal" id="modal-pan">
    <div class="caja-modal">
        <button type="button" class="cerrar-modal" data-cerrar-modal><i class="fas fa-times"></i></button>
        <h2>Detalle de Producción — Pan</h2>
        <p class="subtitulo-modal">Turno: Mañana — 12.5 Coches (150 latas)</p>

        <h3>Desglose por tipo</h3>
        <ul class="lista-desglose">
            <li><span>Pan Francés</span><span>60 latas</span></li>
            <li><span>Pan Yema</span><span>50 latas</span></li>
            <li><span>Pan Integral</span><span>40 latas</span></li>
        </ul>

        <h3>Empleados a cargo</h3>
        <ul class="lista-empleados">
            <li class="chip-empleado"><span>Manuel Rojas</span><span class="etiqueta-rol">Maestro</span></li>
            <li class="chip-empleado"><span>Carlos Vega</span><span class="etiqueta-rol">Ayudante</span></li>
        </ul>

        <div class="registrado-por">
            <i class="fas fa-user-check"></i>
            Registrado por <strong>rosa.diaz</strong> — hoy, 6:45 a.m.
        </div>
    </div>
</div>

<!-- Modal: detalle de Torta -->
<div class="overlay-modal" id="modal-torta">
    <div class="caja-modal">
        <button type="button" class="cerrar-modal" data-cerrar-modal><i class="fas fa-times"></i></button>
        <h2>Detalle de Producción — Torta</h2>
        <p class="subtitulo-modal">15 tortas registradas hoy</p>

        <h3>Tortas del día</h3>
        <div class="galeria-tortas">
            <div class="tarjeta-torta-individual">
                <img src="{{ asset('images/placeholder-torta.jpg') }}" alt="Torta de chocolate">
                <p class="tipo-torta">Chocolate</p>
                <p class="forma-torta">Circular</p>
            </div>
            <div class="tarjeta-torta-individual">
                <img src="{{ asset('images/placeholder-torta.jpg') }}" alt="Torta de vainilla">
                <p class="tipo-torta">Vainilla</p>
                <p class="forma-torta">Rectangular</p>
            </div>
        </div>

        <h3>Empleada a cargo</h3>
        <ul class="lista-empleados">
            <li class="chip-empleado"><span>Rosa Díaz</span><span class="etiqueta-rol">Pastelera</span></li>
        </ul>

        <div class="registrado-por">
            <i class="fas fa-user-check"></i>
            Registrado por <strong>rosa.diaz</strong> — hoy, 6:45 a.m.
        </div>
    </div>
</div>

<!-- Modal: detalle de Bocadito -->
<div class="overlay-modal" id="modal-bocadito">
    <div class="caja-modal">
        <button type="button" class="cerrar-modal" data-cerrar-modal><i class="fas fa-times"></i></button>
        <h2>Detalle de Producción — Bocadito</h2>
        <p class="subtitulo-modal">900 unidades registradas hoy</p>

        <h3>Desglose por tipo</h3>
        <ul class="lista-desglose">
            <li><span>Alfajorcitos</span><span>250 uds</span></li>
            <li><span>Conitos</span><span>200 uds</span></li>
            <li><span>Empanaditas de pollo</span><span>300 uds</span></li>
            <li><span>Pionono</span><span>150 uds</span></li>
        </ul>

        <h3>Empleados a cargo</h3>
        <ul class="lista-empleados">
            <li class="chip-empleado"><span>Ana Torres</span><span class="etiqueta-rol">Maestra</span></li>
            <li class="chip-empleado"><span>Luis Paredes</span><span class="etiqueta-rol">Ayudante</span></li>
        </ul>

        <div class="registrado-por">
            <i class="fas fa-user-check"></i>
            Registrado por <strong>ana.torres</strong> — hoy, 6:50 a.m.
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
</body>
</html>