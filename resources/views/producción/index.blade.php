<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Producción</title>
</head>
<body>

<div class="titulo-produccion">
    <h2>Sistema Panadería</h2>
    <h1>INGRESO DE PRODUCCIÓN</h1>
</div>

<div class="filtro-fecha-inicio">
    <div class="filtro-fecha-desde">
        <label for="fecha_inicio">Desde</label>
        <i class="far fa-calendar-alt"></i>
        <input type="date" id="fecha_inicio" name="fecha_inicio">
    </div>

    <div class="filtro-fecha-hasta">
        <label for="fecha_fin">Hasta</label>
        <i class="far fa-calendar-alt"></i>
        <input type="date" id="fecha_fin" name="fecha_fin">
    </div>

    <div class="filtro-fecha-boton">
        <button type="button" id="filtrar">Aplicar</button>
    </div>

    <div class="nueva-produccion-boton">
        <a href="{{ route('produccion.create') }}">
            <button type="button">+ Nueva Producción</button>
        </a>
    </div>
</div>

<div class="contenedor-produccion" id="contenedor-produccion">

    <!-- esto se va a usar para mostrar las secciones de panes, tortas y bocaditos -->

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

</body>
</html>