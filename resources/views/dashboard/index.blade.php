<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>
</head>
<body>

    <DIV class="Contenido_información">
        
        <div class="titulo_inicio">
            <h1> DASHBOARD DE PRODUCCIÓN</h1>
        </div>

        <div class="filtro-fecha-inicio">
            
            <div class="filtro-fecha_desde">
                <label for="fecha_inicio">Desde:</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio">
            </div>

            <div class="filtro_fecha_hasta">
                <label for="fecha_fin">Hasta:</label>
                <input type="date" id="fecha_fin" name="fecha_fin">
            </div>

            <div class="filtro_fecha_boton">
                <button type="button" id="filtrar">Aplicar</button>
            </div>
        </div>

        <div class = "detalles_cartas">

            <div class = "detalles_cartas">
                <h4>HOY</h4>
                <H4> Produccion | Mañana</H4>
            </div>

            <div class = "detalles_cartas">
                <h4>SEMANA</h4>
                <H4> Produccion | Semana</H4>
            </div>

            <div class = "detalles_cartas">
                <h4>MES</h4>
                <H4> Produccion | Mes</H4>
            </div>
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

        <div class="GRAFICOS">
            
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
        
    </DIV>

</body>
</html>