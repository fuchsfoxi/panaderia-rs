// * DASHBOARD.JS: le da vida a resources/views/dashboard/index.blade.php
// * Hace 3 cosas: animación de entrada, selectores Latas/Unidades y el gráfico de evolución (Chart.js)
// ? El HTML ya trae todo el contenido; este archivo solo lo mejora (los selectores nacen "hidden" y aquí se muestran)

const dashboard = document.querySelector('[data-dashboard]');

// ! Si esta página no tiene el contenedor, no se hace nada (así el archivo es seguro de cargar en cualquier vista)
if (dashboard) {

    // ================= 1. REFERENCIAS AL DOM =================
    // * Se buscan una sola vez al cargar; las funciones de abajo las reutilizan
    const diario = dashboard.querySelector('[data-unidad-diaria]');   // selector Latas/Unidades de "Producción de hoy"
    const mensual = dashboard.querySelector('[data-unidad-mensual]'); // selector Latas/Unidades del ranking mensual
    const periodo = dashboard.querySelector('[data-serie]');          // selector Últimos 7 días / Mes actual
    const canvas = dashboard.querySelector('[data-grafico-turnos]');  // ? nombre histórico: hoy dibuja la evolución, no turnos
    const error = dashboard.querySelector('[data-error-grafico]');    // mensaje si Chart.js no carga

    // * Los datos del gráfico llegan desde Blade como JSON dentro de un <script type="application/json">
    const series = JSON.parse(dashboard.querySelector('[data-datos-series]').textContent);

    let grafico;      // la instancia actual de Chart.js (se destruye y se crea de nuevo al cambiar de período)
    let version = 0;  // ? "número de pedido": evita que un dibujo lento pise a uno más nuevo (ver mostrarGrafico)

    // ================= 2. ANIMACIÓN DE ENTRADA =================
    // * Solo en la primera visita de la sesión, y solo si la persona no pidió "menos movimiento" en su sistema
    const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');
    let primeraEntrada = false;
    try {
        // ? sessionStorage recuerda "ya vi la bienvenida" hasta cerrar la pestaña
        primeraEntrada = !sessionStorage.getItem('panaderia-bienvenida-dashboard');
        sessionStorage.setItem('panaderia-bienvenida-dashboard', 'vista');
    } catch {
        // ? Si el navegador bloquea sessionStorage (modo privado, etc.), se anima siempre y no pasa nada
        primeraEntrada = true;
    }
    if (primeraEntrada && !movimientoReducido.matches) {
        // * El CSS anima los elementos con [data-entrada] cuando el contenedor tiene esta clase
        dashboard.classList.add('entrada-dashboard');
    }
    // TODO: antes aquí había un contador que "subía" los números de la sección Consulta de Pan (data-contador).
    // TODO: esa sección se eliminó. Si quieres el efecto en las tarjetas de hoy, ponles data-contador="{{ valor }}" en el Blade

    // ================= 3. SELECTORES LATAS / UNIDADES =================
    // * Muestra solo el panel cuyo atributo coincide con lo elegido en el select; oculta el otro
    // ? Ejemplo: atributo = 'data-pan-diario' y select.value = 'latas' → se ve data-pan-diario="latas"
    function seleccionar(select, atributo) {
        dashboard.querySelectorAll(`[${atributo}]`).forEach(panel => {
            panel.hidden = panel.getAttribute(atributo) !== select.value;
        });
    }
    diario.addEventListener('change', () => seleccionar(diario, 'data-pan-diario'));
    mensual.addEventListener('change', () => seleccionar(mensual, 'data-pan-mensual'));

    // * Estado inicial: aplicar el valor por defecto de cada select ("latas")
    seleccionar(diario, 'data-pan-diario');
    seleccionar(mensual, 'data-pan-mensual');

    // * Recién ahora se muestran los controles (sin JS no tendrían efecto, por eso nacen ocultos)
    dashboard.querySelector('[data-control-diario]').hidden = false;
    dashboard.querySelector('[data-control-mensual]').hidden = false;
    dashboard.querySelector('[data-control-serie]').hidden = false;

    // ================= 4. GRÁFICO DE EVOLUCIÓN =================
    // * Colores por familia. Deben coincidir con la leyenda de dashboard.css (.marca-pan, .marca-torta, .marca-bocadito)
    // TODO: el azul de Bocadito (#23577A) es el único color fuera de la paleta verde/beige/marrón de la marca
    const FAMILIAS = [
        { key: 'pan', label: 'Pan', color: '#33403A' },
        { key: 'torta', label: 'Torta', color: '#855332' },
        { key: 'bocadito', label: 'Bocadito', color: '#23577A' },
    ];

    // * Muestra la tabla de datos del período elegido y oculta la otra
    function mostrarTablas() {
        dashboard.querySelectorAll('[data-tabla-serie]').forEach(tabla => {
            tabla.hidden = periodo.value !== tabla.dataset.tablaSerie;
        });
    }

    // * Dibuja (o redibuja) el gráfico según el período elegido: 'siete_dias' o 'mes_actual'
    async function mostrarGrafico() {
        // ! "version" protege de una carrera: si la persona cambia el select rápido, el import de Chart.js
        // ! de la petición vieja puede terminar DESPUÉS de la nueva. Al comparar, la vieja se descarta.
        const actual = ++version;
        const modo = periodo.value;
        mostrarTablas();

        try {
            // ? Chart.js se importa dinámicamente: solo se descarga cuando hace falta, y el dashboard carga más rápido
            const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } = await import('chart.js');

            // ! Si ya llegó un pedido más nuevo, o el canvas salió de la página, no dibujar
            if (actual !== version || !canvas.isConnected) return;

            // ? Se registran solo las piezas que se usan (barras, ejes y tooltip) para no cargar todo Chart.js
            Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

            // ! Destruir el gráfico anterior antes de crear otro sobre el mismo canvas, o Chart.js da error
            grafico?.destroy();
            grafico = null;

            const datos = series[modo]; // lista de días: [{ fecha: '2026-11-06', pan: 2, torta: 2, bocadito: 2 }, ...]

            grafico = new Chart(canvas, {
                type: 'bar',
                data: {
                    // * Eje X: "2026-11-06" → "06/11" (día/mes), cortando el texto de la fecha
                    labels: datos.map(fila => `${fila.fecha.slice(8, 10)}/${fila.fecha.slice(5, 7)}`),
                    // * Una serie de barras por familia (pan, torta, bocadito)
                    datasets: FAMILIAS.map(familia => ({
                        label: familia.label,
                        data: datos.map(fila => fila[familia.key]),
                        backgroundColor: familia.color,
                        borderRadius: 3,
                        maxBarThickness: 18,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // el alto lo manda el CSS (.dashboard-canvas)
                    animation: false,           // sin animación de Chart.js: ya hay una de entrada propia
                    plugins: {
                        legend: { display: false }, // la leyenda es la del HTML (.leyenda-dashboard)
                        tooltip: { mode: 'index', intersect: false }, // al pasar el mouse muestra las 3 familias del día
                    },
                    scales: {
                        // ? En "mes_actual" hay ~30 fechas: se limitan las etiquetas visibles para que no se amontonen
                        x: { ticks: { maxTicksLimit: modo === 'mes_actual' ? 8 : 12, precision: 0, color: '#33403A' }, grid: { color: '#DCD6C4' } },
                        y: { beginAtZero: true, ticks: { precision: 0, color: '#33403A' }, grid: { color: '#DCD6C4' } },
                    },
                },
            });
            error.hidden = true;
        } catch (fallo) {
            // ? El error real se imprime en la consola del navegador (F12). Sin esto el aviso aparece pero no se sabe por qué
            console.error('No se pudo dibujar el gráfico:', fallo);
            // ! Si Chart.js no carga o falla al dibujar, se muestra el aviso; las tablas "Ver datos" siguen funcionando
            if (actual === version) error.hidden = false;
        }
    }

    // ================= 5. ARRANQUE =================
    // * Cambiar el período redibuja el gráfico; y se dibuja una vez al cargar la página
    periodo.addEventListener('change', mostrarGrafico);
    mostrarGrafico();

    // ! Aquí estaba iniciarConsulta(...) para el filtro "Consultar período histórico" (carga parcial por fetch).
    // ! Se quitó junto con esa sección. consulta-parcial.js lo sigue usando consultas-produccion.js, NO borrar ese archivo.
}