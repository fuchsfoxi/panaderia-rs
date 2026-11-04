import { iniciarConsulta } from './consulta-parcial.js';

const dashboard = document.querySelector('[data-dashboard]');
if (dashboard) {
    const diario = dashboard.querySelector('[data-unidad-diaria]');
    const mensual = dashboard.querySelector('[data-unidad-mensual]');
    const periodo = dashboard.querySelector('[data-serie]');
    const canvas = dashboard.querySelector('[data-grafico-turnos]');
    const error = dashboard.querySelector('[data-error-grafico]');
    const descripcion = dashboard.querySelector('[data-descripcion-serie]');
    const series = JSON.parse(dashboard.querySelector('[data-datos-series]').textContent);
    let grafico;
    let version = 0;

    const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');
    let primeraEntrada = false;
    try {
        primeraEntrada = !sessionStorage.getItem('panaderia-bienvenida-dashboard');
        sessionStorage.setItem('panaderia-bienvenida-dashboard', 'vista');
    } catch { primeraEntrada = true; }
    if (primeraEntrada && !movimientoReducido.matches) {
        dashboard.classList.add('entrada-dashboard');
        dashboard.querySelectorAll('[data-contador]').forEach(elemento => {
            const destino = Number(elemento.dataset.contador);
            if (!Number.isSafeInteger(destino) || destino <= 0) return;
            const final = elemento.textContent;
            const accesible = document.createElement('span');
            accesible.className = 'solo-lectores';
            accesible.textContent = final;
            const visual = document.createElement('span');
            visual.setAttribute('aria-hidden', 'true');
            elemento.replaceChildren(accesible, visual);
            const inicio = performance.now();
            const formato = new Intl.NumberFormat('es-ES');
            function avanzar(ahora) {
                const progreso = movimientoReducido.matches ? 1 : Math.min((ahora - inicio) / 1350, 1);
                visual.textContent = progreso === 1 ? final : formato.format(Math.round(destino * (1 - (1 - progreso) ** 3)));
                if (progreso < 1) requestAnimationFrame(avanzar);
            }
            requestAnimationFrame(avanzar);
        });
    }

    function seleccionar(select, atributo) {
        dashboard.querySelectorAll(`[${atributo}]`).forEach(panel => {
            panel.hidden = panel.getAttribute(atributo) !== select.value;
        });
    }
    diario.addEventListener('change', () => seleccionar(diario, 'data-pan-diario'));
    mensual.addEventListener('change', () => seleccionar(mensual, 'data-pan-mensual'));
    seleccionar(diario, 'data-pan-diario');
    seleccionar(mensual, 'data-pan-mensual');
    dashboard.querySelector('[data-control-diario]').hidden = false;
    dashboard.querySelector('[data-control-mensual]').hidden = false;
    dashboard.querySelector('[data-control-serie]').hidden = false;

    function mostrarTablas() {
        dashboard.querySelectorAll('[data-tabla-serie]').forEach(tabla => {
            tabla.hidden = periodo.value !== tabla.dataset.tablaSerie;
        });
    }

    async function mostrarGrafico() {
        const actual = ++version;
        const modo = periodo.value;
        mostrarTablas();
        descripcion.textContent = modo === 'turnos'
            ? 'Latas de Pan por turno para el período histórico consultado. Los valores están disponibles en el resumen por turno.'
            : 'Registros diarios por familia. Las fechas futuras del mes son fechas del calendario, no predicciones.';
        dashboard.querySelector('.leyenda-dashboard').hidden = modo === 'turnos';
        try {
            const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } = await import('chart.js');
            if (actual !== version || !canvas.isConnected) return;
            Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);
            grafico?.destroy();
            grafico = null;
            const turnos = JSON.parse(dashboard.querySelector('[data-datos-turnos]').textContent);
            const datos = modo === 'turnos' ? turnos : series[modo];
            const familias = [
                { key: 'pan', label: 'Pan', color: '#33403A' },
                { key: 'torta', label: 'Torta', color: '#855332' },
                { key: 'bocadito', label: 'Bocadito', color: '#23577A' },
            ];
            grafico = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: datos.map(fila => modo === 'turnos' ? fila.nombre : `${fila.fecha.slice(8, 10)}/${fila.fecha.slice(5, 7)}`),
                    datasets: modo === 'turnos'
                        ? [{ label: 'Latas', data: datos.map(fila => fila.latas), backgroundColor: '#4F6355', borderRadius: 6, maxBarThickness: 22 }]
                        : familias.map(familia => ({ label: familia.label, data: datos.map(fila => fila[familia.key]), backgroundColor: familia.color, borderRadius: 3, maxBarThickness: 18 })),
                },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: false,
                    indexAxis: modo === 'turnos' ? 'y' : 'x',
                    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                    scales: { x: { ticks: { maxTicksLimit: modo === 'mes_actual' ? 8 : 12, precision: 0, color: '#33403A' }, grid: { color: '#DCD6C4' } }, y: { beginAtZero: true, ticks: { precision: 0, color: '#33403A' }, grid: { color: '#DCD6C4' } } },
                },
            });
            error.hidden = true;
        } catch {
            if (actual === version) error.hidden = false;
        }
    }
    periodo.addEventListener('change', mostrarGrafico);
    dashboard.addEventListener('click', event => {
        if (!event.target.closest('[data-ver-turnos]')) return;
        periodo.value = 'turnos';
        mostrarGrafico();
        periodo.focus();
    });
    function activarTurnos() {
        dashboard.querySelector('[data-ver-turnos]').hidden = false;
    }
    activarTurnos();
    mostrarGrafico();
    iniciarConsulta(dashboard.querySelector('[data-filtros-dashboard]'), {
        contenedor: dashboard, selector: '[data-dashboard-contenido]',
        despues: () => { dashboard.classList.remove('entrada-dashboard'); activarTurnos(); if (periodo.value === 'turnos') mostrarGrafico(); },
    });
}
