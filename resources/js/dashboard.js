import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', function () {
    const colorVerdeOscuro = '#33403a';
    const colorVerdeSage = '#7d9481';
    const colorBeige = '#dcd6c4';

    const opcionesComunes = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: colorVerdeOscuro, font: { family: 'Huninn' } }
            }
        },
        scales: {
            x: { ticks: { color: colorVerdeOscuro, font: { family: 'Huninn' } }, grid: { display: false } },
            y: { ticks: { color: colorVerdeOscuro, font: { family: 'Huninn' } }, grid: { color: colorBeige } }
        }
    };

    // MODIFICADO: los datos de los 3 graficos ya no estan fijos en este
    // archivo. Los calcula DashboardController (ResumenDashboard::graficos())
    // y los entrega en el <script id="datos-graficos"> de la vista Blade.
    const nodoDatos = document.getElementById('datos-graficos');
    const graficos = nodoDatos
        ? JSON.parse(nodoDatos.textContent)
        : { dia: { categorias: [], hoy: [], ayer: [] }, semana: { labels: [], valores: [] }, mes: { labels: [], valores: [] } };

    const canvasDia = document.getElementById('grafico_dia');
    if (canvasDia) {
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
    if (canvasSemana) {
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
    if (canvasMes) {
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
            boton.closest('.overlay-modal').classList.remove('activo');
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