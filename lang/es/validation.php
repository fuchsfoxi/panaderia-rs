<?php

/*
|--------------------------------------------------------------------------
| ARCHIVO DE TRADUCCIONES DE VALIDACION (es)
|--------------------------------------------------------------------------
|
| ============================================================================
| CÓMO FUNCIONA ESTE ARCHIVO
| ============================================================================
|
| Cuando una regla de validación falla, Laravel arma una clave con el nombre
| de la regla y el del campo, y la busca en lang/{idioma}/validation.php.
|
| Por ejemplo, si falla la regla 'required' sobre el campo 'producto_id', busca:
|
|     'required.producto_id'
|
| Después:
|
|   1. Si existe esa clave exacta, usa ese texto.
|   2. Si no existe, prueba con la regla sola: 'required'.
|   3. Si tampoco existe, usa el idioma de respaldo (que en AppServiceProvider
|      también quedó en español).
|   4. Reemplaza los :placeholders: por los valores que le pasa el
|      validador: :attribute (el nombre del campo), :min, :max, :other, etc.
|
| ---------------------------------------------------------------------------
| POR QUÉ EXISTE ESTE ARCHIVO
| ---------------------------------------------------------------------------
|
| El framework de Laravel trae SOLO los mensajes en inglés. No hay ningún otro
| idioma en vendor/, así que sin este archivo toda validación saldría en
| inglés: "The producto id field is required." frente a un panadero.
|
| Se traduce una sola vez, en lugar de escribir los mensajes directo en cada
| controlador, porque:
|
|   - Un mensaje se escribe UNA vez y sirve para todos los lugares que usen
|     esa regla. Si mañana se agrega otro formulario, el mensaje ya está.
|   - Permite cambiar el idioma completo del sistema tocando un solo archivo
|     (o cambiando 'app.locale' en AppServiceProvider).
|
| ---------------------------------------------------------------------------
| CÓMO AGREGAR UN MENSAJE NUEVO
| ---------------------------------------------------------------------------
|
|   - Una regla que no está en la lista de abajo: copiala de los comentarios
|     de la regla 'other' y ajustá los :placeholders: a los que documents esa
|     regla. Ejemplo de la regla 'after' (un campo tiene que ser posterior a
|     otro):
|         'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
|
|   - Un mensaje más amable para un campo puntual: sumalo en la sección
|     'custom', con el nombre de la regla y el del campo:
|         'foto' => [
|             'max' => 'La foto no puede pesar más de 2 MB.',
|         ],
|     Esto PISA el mensaje genérico de la regla, solo para ese campo.
|
|   - El nombre humano de un campo: sumalo en la sección 'attributes'. Con
|     eso, 'required.producto_id' no encuentra nada pero el mensaje genérico de
|     'required' ya sale como "El campo producto es obligatorio", sin llegar a
|     escribir un mensaje a medida.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Mensajes de validación
    |--------------------------------------------------------------------------
    */

    'accepted' => 'El campo :attribute debe ser aceptado.',
    'active_url' => 'El campo :attribute no es una URL válida.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => 'El campo :attribute solo puede contener letras.',
    'alpha_dash' => 'El campo :attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => 'El campo :attribute solo puede contener letras y números.',
    'array' => 'El campo :attribute debe ser una lista.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El campo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe ser un valor entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'date_equals' => 'El campo :attribute debe ser la fecha :date.',
    'date_format' => 'El campo :attribute no coincide con el formato :format.',
    'decimal' => 'El campo :attribute debe tener :decimal decimales.',
    'declined' => 'El campo :attribute debe ser rechazado.',
    'different' => 'Los campos :attribute y :other deben ser diferentes.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'La imagen del campo :attribute no tiene las dimensiones correctas.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'email' => 'El campo :attribute no es un correo válido.',
    'ends_with' => 'El campo :attribute debe terminar con: :values.',
    'enum' => 'El valor seleccionado de :attribute no es válido.',
    'exists' => 'El valor seleccionado de :attribute no existe.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute no puede quedar vacío.',
    'gt' => [
        'array' => 'El campo :attribute debe tener más de :min elementos.',
        'file' => 'El campo :attribute debe pesar más de :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :min.',
        'string' => 'El campo :attribute debe tener más de :min caracteres.',
    ],
    'gte' => [
        'array' => 'El campo :attribute debe tener :min elementos o más.',
        'file' => 'El campo :attribute debe pesar :min kilobytes o más.',
        'numeric' => 'El campo :attribute debe ser :min o mayor.',
        'string' => 'El campo :attribute debe tener :min caracteres o más.',
    ],
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor seleccionado de :attribute no es válido.',
    'in_array' => 'El campo :attribute no tiene un valor válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'ip' => 'El campo :attribute debe ser una dirección IP válida.',
    'ipv4' => 'El campo :attribute debe ser una dirección IPv4 válida.',
    'ipv6' => 'El campo :attribute debe ser una dirección IPv6 válida.',
    'json' => 'El campo :attribute debe ser un texto JSON válido.',
    'lt' => [
        'array' => 'El campo :attribute debe tener menos de :max elementos.',
        'file' => 'El campo :attribute debe pesar menos de :max kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :max.',
        'string' => 'El campo :attribute debe tener menos de :max caracteres.',
    ],
    'lte' => [
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
        'file' => 'El campo :attribute debe pesar :max kilobytes o menos.',
        'numeric' => 'El campo :attribute debe ser :max o menor.',
        'string' => 'El campo :attribute debe tener :max caracteres o menos.',
    ],
    'mac_address' => 'El campo :attribute debe ser una dirección MAC válida.',
    'max' => [
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
        'file' => 'El campo :attribute no puede pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El campo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'multiple_of' => 'El campo :attribute debe ser múltiplo de :value.',
    'not_in' => 'El valor seleccionado de :attribute no es válido.',
    'not_regex' => 'El formato del campo :attribute no es válido.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'present' => 'El campo :attribute debe estar presente.',
    'prohibited' => 'El campo :attribute está prohibido.',
    'prohibited_if' => 'El campo :attribute está prohibido cuando :other es :value.',
    'prohibited_unless' => 'El campo :attribute está prohibido salvo que :other sea :values.',
    'prohibits' => 'El campo :attribute impide enviar el campo :other.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_array_keys' => 'Al campo :attribute le falta una entrada: :values.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_unless' => 'El campo :attribute es obligatorio salvo que :other sea :values.',
    'required_with' => 'El campo :attribute es obligatorio cuando hay :values.',
    'required_with_all' => 'El campo :attribute es obligatorio cuando hay :values.',
    'required_without' => 'El campo :attribute es obligatorio cuando no hay :values.',
    'required_without_all' => 'El campo :attribute es obligatorio cuando no hay ninguno de estos valores: :values.',
    'same' => 'Los campos :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe tener :size elementos.',
        'file' => 'El campo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'starts_with' => 'El campo :attribute debe empezar con: :values.',
    'string' => 'El campo :attribute debe ser texto.',
    'timezone' => 'El campo :attribute debe ser una zona horaria válida.',
    'unique' => 'El valor del campo :attribute ya está en uso.',
    'uploaded' => 'No se pudo subir el archivo del campo :attribute.',
    'url' => 'El campo :attribute no es una URL válida.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensajes de validación para claves personalizadas
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'nombre' => [
            'required' => 'El nombre es obligatorio.',
        ],
        'turno_id' => [
            'required' => 'Elegí un turno.',
        ],
        'cantidad_coches' => [
            'required' => 'Escribí cuántos coches se produjeron.',
        ],
        'cantidad_unidades' => [
            'required' => 'Escribí cuántas unidades se produjeron.',
        ],
        'forma' => [
            'required' => 'Elegí la forma de la torta.',
        ],
        'foto' => [
            'required' => 'Subí una foto de la torta.',
            'image' => 'La foto tiene que ser una imagen.',
            'max' => 'La foto no puede pesar más de 2 MB.',
        ],
        'empleados' => [
            'required' => 'Agregá al menos un empleado encargado.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombres de los campos
    |--------------------------------------------------------------------------
    |
    | Traducen el nombre técnico del campo al que usa el usuario. Con esto
    | "The producto id field is required." pasa a ser "El producto es
    | obligatorio.".
    |
    */

    'attributes' => [
        'fecha' => 'fecha',
        'producto_id' => 'producto',
        'turno_id' => 'turno',
        'cantidad_coches' => 'cantidad de coches',
        'cantidad_unidades' => 'cantidad de unidades',
        'forma' => 'forma de la torta',
        'foto' => 'foto',
        'observaciones' => 'observaciones',
        'empleados' => 'empleados',
        'empleados.*.empleado_id' => 'empleado',
        'empleados.*.rol_id' => 'rol',
        'username' => 'usuario',
        'password' => 'contraseña',
        'categoria' => 'tipo de producción',
        'nombre_p' => 'nombre del producto',
    ],

];
