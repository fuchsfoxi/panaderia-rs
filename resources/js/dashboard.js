import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', function () {
    // Se leen de las variables de marca (resources/css/variables.css) en vez
    // de escribir los hexadecimales aca: si la paleta cambia, los graficos
    // cambian con ella.
    const estilos = getComputedStyle(document.documentElement);
    const colorVerdeOscuro = estilos.getPropertyValue('--verde-oscuro').trim() || '#33403a';
    const colorVerdeSage = estilos.getPropertyValue('--verde-sage').trim() || '#7d9481';
    const colorBeige = estilos.getPropertyValue('--beige').trim() || '#dcd6c4';
    const fuenteTitulos = estilos.getPropertyValue('--fuente-titulos').split(',')[0].replace(/["']/g, '').trim() || 'Baloo 2';
    const fuenteNormal = estilos.getPropertyValue('--fuente').split(',')[0].replace(/["']/g, '').trim() || 'Huninn';

    const opcionesComunes = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: colorVerdeOscuro, font: { family: fuenteNormal } }
            }
        },
        scales: {
            x: { ticks: { color: colorVerdeOscuro, font: { family: fuenteTitulos } }, grid: { display: false } },
            y: { ticks: { color: colorVerdeOscuro, font: { family: fuenteTitulos } }, grid: { color: colorBeige } }
        }
    };

    // MODIFICADO: los datos de los 3 graficos ya no estan fijos en este
    // archivo. Los calcula DashboardController (ResumenDashboard::graficos())
    // y los entrega en el <script id="datos-graficos"> de la vista Blade.
    // Si el JSON del <script id="datos-graficos"> viniera roto, un
    // JSON.parse a secas cortaria TODO el script y los 3 graficos mas los
    // modales (que viven mas abajo) dejarian de responder. Se lee con
    // try/catch y, si falla, se avisa en pantalla en vez de fallar en
    // silencio.
    const vacio = {
        dia: { categorias: [], hoy: [], ayer: [] },
        semana: { labels: [], valores: [] },
        mes: { labels: [], valores: [] },
    };

    const nodoDatos = document.getElementById('datos-graficos');
    let graficos = vacio;

    if (nodoDatos) {
        try {
            graficos = JSON.parse(nodoDatos.textContent);
        } catch (error) {
            graficos = vacio;
            console.error('No se pudieron leer los datos de los graficos:', error);
            mostrarAviso('No se pudieron cargar los gráficos de producción.');
        }
    }

    /**
     * Muestra un aviso dentro de la pagina. Existe para que un fallo de JS
     * sea visible y no solo un error en la consola que nadie ve.
     */
    function mostrarAviso(mensaje) {
        const aviso = document.createElement('p');
        aviso.className = 'alerta alerta-error';
        aviso.setAttribute('role', 'alert');
        aviso.textContent = mensaje;

        const contenedor = document.querySelector('.contenido_informacion');
        if (contenedor) {
            contenedor.prepend(aviso);
        }
    }

    // Chart.js con responsive + maintainAspectRatio:false (opcionesComunes)
    // redimensiona solo cuando cambia el ancho, tambien al girar el celular.
    // El alto lo define el CSS del <canvas> (.grafico canvas en dashboard.css).
    const canvasDia = document.getElementById('grafico_dia');
    if (canvasDia && graficos.dia.categorias.length > 0) {
        new Chart(canvasDia, {
            type: 'bar',
            data: {
                labels: graficos.dia.categorias,
                datasets: [
                    // MODIFICADO: [0, 0, 0] -> valores reales de la base.
                    // Son CANTIDADES DE LÍNEAS por categoría, no sumas de
                    // cantidad: una torta no tiene 'cantidad' (1 registro = 1
                    // torta) y sumarla con los panes no significaría nada.
                    { label: 'Producción de hoy', data: graficos.dia.categorias.map((c) => graficos.dia.hoy[c] || 0), backgroundColor: colorVerdeOscuro },
                    { label: 'Producción de ayer', data: graficos.dia.categorias.map((c) => graficos.dia.ayer[c] || 0), backgroundColor: colorVerdeSage }
                ]
            },
            options: opcionesComunes
        });
    }

    const canvasSemana = document.getElementById('grafico_semana');
    if (canvasSemana && graficos.semana.labels.length > 0) {
        new Chart(canvasSemana, {
            type: 'bar',
            data: {
                // MODIFICADO: [0, 0, 0, 0, 0, 0] -> conteo real por día.
                labels: graficos.semana.labels,
                datasets: [
                    { label: 'Producción de la semana', data: graficos.semana.valores, backgroundColor: colorVerdeOscuro }
                ]
            },
            options: opcionesComunes
        });
    }

    const canvasMes = document.getElementById('grafico_mes');
    if (canvasMes && graficos.mes.labels.length > 0) {
        new Chart(canvasMes, {
            type: 'bar',
            data: {
                // MODIFICADO: [0, 0, 0, 0] -> conteo real por semana del mes.
                labels: graficos.mes.labels,
                datasets: [
                    { label: 'Producción del mes', data: graficos.mes.valores, backgroundColor: colorVerdeOscuro }
                ]
            },
            options: opcionesComunes
        });
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const botonesAbrir = document.querySelectorAll('[data-abrir-modal]');
    const botonesCerrar = document.querySelectorAll('[data-cerrar-modal]');

    botonesAbrir.forEach(function (boton) {
        boton.addEventListener('click', function () {
            const idModal = boton.getAttribute('data-abrir-modal');
            const modal = document.getElementById(idModal);
            if (modal) {
                modal.classList.add('activo');
            }
        });
    });

    botonesCerrar.forEach(function (boton) {
        boton.addEventListener('click', function () {
            const overlay = boton.closest('.overlay-modal');
            if (overlay) {
                overlay.classList.remove('activo');
            }
        });
    });

    document.querySelectorAll('.overlay-modal').forEach(function (overlay) {
        overlay.addEventListener('click', function (evento) {
            if (evento.target === overlay) {
                overlay.classList.remove('activo');
            }
        });
    });
});