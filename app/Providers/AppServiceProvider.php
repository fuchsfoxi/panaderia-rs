<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * ============================================================================
 * CONFIGURACION QUE SE APLICA AL ARRANCAR EL SISTEMA
 * ============================================================================
 *
 * Este provider es el lugar donde vive la configuración que tiene que estar
 * lista ANTES de que se atienda cualquier petición. Laravel llama a boot()
 * una vez por cada proceso de PHP que atiende peticiones.
 *
 * Sirve para las cosas que cambian el comportamiento global de la aplicación,
 * como el idioma de los mensajes o la zona horaria. Si algo solo afecta a una
 * pantalla, va en el controlador de esa pantalla, no acá.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * register(): se ejecuta al CONSTRUIR el contenedor de servicios.
     *
     * Sirve para registrar servicios y mostrar que "una cosa por archivo",
     * por ejemplo un cliente de API o una clase propia. No se usa porque el
     * proyecto no tiene integraciones externas.
     *
     * Un detalle de fondo: lo que se declara acá se guarda para que lo lean
     * todos los que lo necesiten. Por eso tiene que ser rápido y
     * no puede consultar la base ni usar la sesión: todavía no hay petición.
     */
    public function register(): void
    {
        //
    }

    /**
     * boot(): se ejecuta cuando la aplicación ya está construida, una vez.
     *
     * ---------------------------------------------------------------------------
     * IDIOMA DE LA INTERFAZ: ESPAÑOL
     * ---------------------------------------------------------------------------
     *
     * Hay tres lugares donde se define el idioma, y solo se escribe en uno:
     *
     *   1. config/app.php   ->   'locale' => env('APP_LOCALE', 'en')
     *   2. .env             ->   APP_LOCALE=es
     *   3. ESTE archivo     ->   config(['app.locale' => 'es'])
     *
     * Y el último pisa al primero. Se hizo así a propósito, por dos razones:
     *
     *  a) El .env es local de cada máquina. Quien clonee el proyecto y no
     *     cambie APP_LOCALE se quedaría con la aplicación en inglés, y lo
     *     vería recién cuando un usuario se equivoque al guardar producción y
     *     le salga "The producto id field is required.".
     *
     *  b) El idioma es una decisión del SISTEMA, no de una máquina. Si
     *     estuviera solo en el .env, podría haber una máquina con el sistema
     *     en español y otra en inglés, sin que nadie lo note.
     *
     * El idioma de respaldo (fallback_locale) también va en español: si
     * alguna regla de validación no estuviera traducida en lang/es/, sale en
     * español y no vuelve al inglés.
     *
     * Dónde están los textos: en lang/es/validation.php. Laravel busca la
     * clave de la regla fallida en ese archivo, usando el patrón
     * 'nombre_de_la_regla.nombre_del_campo'. Por eso el mensaje de
     * "El campo producto es obligatorio" sale de 'required.producto_id' con
     * el nombre del campo en la sección 'attributes'.
     *
     * Para agregar un mensaje nuevo alcanza con sumar una línea en
     * lang/es/validation.php. No hay que tocar ningún controlador.
     */
    public function boot(): void
    {
        config([
            'app.locale' => 'es',
            'app.fallback_locale' => 'es',
        ]);
    }
}
