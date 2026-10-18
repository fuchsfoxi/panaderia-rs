import { inicializarContrasenas } from './contrasenas.js';

inicializarContrasenas();

const formularioUsuario = document.querySelector('[data-crear-usuario], [data-editar-usuario]');

if (formularioUsuario) {
    const rol = formularioUsuario.querySelector('[data-rol-usuario]');
    const grupoTurno = formularioUsuario.querySelector('[data-turno-usuario]');
    const turno = grupoTurno.querySelector('select');

    const actualizarTurno = () => {
        const operador = rol.selectedOptions[0]?.dataset.operador === 'true';
        grupoTurno.hidden = !operador;
        turno.disabled = !operador;
        turno.required = operador;
    };

    rol.addEventListener('change', actualizarTurno);
    actualizarTurno();
}
