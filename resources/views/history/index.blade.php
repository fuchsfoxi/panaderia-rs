<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>historial</title>
</head>
    <body>
            <div class="pagina-historial">

            <div class="titulo-historal">
                <h1> Historial de Producción </h1>
            </div>


            <div class="filtro-categoria">
                <h2>Tipo de producción</h2>

                <div class="categoria-botones">
                    <button type="button" class="btn-categoria" data-categoria="pan">Pan</button>
                    <button type="button" class="btn-categoria" data-categoria="torta">Torta</button>
                    <button type="button" class="btn-categoria" data-categoria="bocadito">Bocadito</button>
                </div>
            </div>

        <div class="filtro-fecha-general">

        <div class="sub-titulo">
            <h2>Fecha</h2>
        </div>  

        <div class="filtro-fecha-principal">

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
        </div>


        <div class="sub-titulo">
                    Turno
        </div>

        <div class="campo campo-condicional" data-mostrar-en="pan" id="campo-turno">
            <label for="turno">Turno</label>
            <select id="turno" name="turno_id">
            <option value="1">Mañana</option>
            <option value="2">Noche</option>
        </select>
        </div>
    </div>

    <div class=" btn-filtro"> 
        <button type="button" class="btn-limpiar"> Limpiar </button>
        <button type="button" class="btn-filtrar"> Filtrar </button>
    </div>


    <div class="datos-encontrados">
        <i class="far fa-clipboard"></i> 23 registros encontrados
    </div>

    <div class="fecha-dia">
        20 OCT 2026
    </div>

    <div class="carta-historial">
        <img src="https://via.placeholder.com/60" alt="Pan carioco" class="foto-producto">
    <div class="info-carta">
        <h3>Pan carioco</h3>
        <p>Cantidad: 1 coche</p>
    </div>
        <div class="meta-carta">
            <span><i class="far fa-user"></i> ingreso: rosa</span>
            <a href="#" class="detalles-link">detalles →</a>
        </div>
    </div>

    <div class="fecha-dia">
        19 OCT 2026
    </div>

    <div class="carta-historial">
        <img src="https://via.placeholder.com/60" alt="Pan carioco" class="foto-producto">
    <div class="info-carta">
        <h3>Pan carioco</h3>
        <p>Cantidad: 4 coche</p>
    </div>
    <div class="meta-carta">
            <span><i class="far fa-user"></i> ingreso: manuel</span>
            <a href="#" class="detalles-link">detalles →</a>
        </div>
    </div>

    </div>
</body>
</html>