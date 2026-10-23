import { iniciarConsulta } from './consulta-parcial.js';

const dashboard = document.querySelector('[data-dashboard]');
if (dashboard) {
    const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');
    let grafico;
    async function mostrarGrafico() {
        grafico?.destroy();
        grafico = null;
        const canvas = dashboard.querySelector('[data-grafico-turnos]');
        if (!canvas) return;
        try {
            const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } = await import('chart.js');
            if (!canvas.isConnected) return;
            Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);
            const datos = JSON.parse(dashboard.querySelector('[data-datos-turnos]').textContent);
            grafico = new Chart(canvas, {
                type: 'bar',
                data: { labels: datos.map(turno => turno.nombre), datasets: [{ label: 'Latas', data: datos.map(turno => turno.latas), backgroundColor: ['#4F6355', '#7D9481', '#AEC0AC'], borderRadius: 6, maxBarThickness: 22 }] },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    animation: false,
                    plugins: { legend: { display: false }, tooltip: { displayColors: false } },
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#DCD6C4' }, border: { display: false } }, y: { grid: { display: false }, border: { display: false }, ticks: { color: '#33403A' } } },
                },
            });
        } catch {
            if (canvas.isConnected) dashboard.querySelector('[data-error-grafico]').hidden = false;
        }
    }

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
    mostrarGrafico();
    iniciarConsulta(dashboard.querySelector('[data-filtros-dashboard]'), {
        contenedor: dashboard, selector: '[data-dashboard-contenido]',
        despues: () => {
            dashboard.classList.remove('entrada-dashboard');
            mostrarGrafico();
        },
    });
}
