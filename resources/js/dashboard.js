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

    const canvasDia = document.getElementById('grafico_dia');
    if (canvasDia) {
        new Chart(canvasDia, {
            type: 'bar',
            data: {
                labels: ['Torta', 'Pan', 'Bocadito'],
                datasets: [
                    { label: 'Producción de hoy', data: [0, 0, 0], backgroundColor: colorVerdeOscuro },
                    { label: 'Producción de ayer', data: [0, 0, 0], backgroundColor: colorVerdeSage }
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
                labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                datasets: [
                    { label: 'Producción de la semana', data: [0, 0, 0, 0, 0, 0], backgroundColor: colorVerdeOscuro }
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
                labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
                datasets: [
                    { label: 'Producción del mes', data: [0, 0, 0, 0], backgroundColor: colorVerdeOscuro }
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