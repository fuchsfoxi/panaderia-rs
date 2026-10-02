// BFCache puede restaurar el documento sin consultar al middleware auth.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.body.hidden = true;
        window.location.reload();
    }
});
