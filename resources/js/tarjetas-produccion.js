// details/summary funcionan también sin JavaScript; name conserva un único abierto.
document.addEventListener('toggle', event => {
    const detalle = event.target;
    if (!detalle.matches?.('.detalles-produccion')) return;
    detalle.querySelector('summary').setAttribute('aria-expanded', String(detalle.open));
    if (detalle.open) {
        document.querySelectorAll(`details[name="${detalle.name}"]`).forEach(otro => {
            if (otro !== detalle) otro.open = false;
        });
    }
}, true);
