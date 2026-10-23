@props(['campo', 'etiqueta', 'valor' => '', 'disabled' => false, 'required' => true, 'error' => null])
@php
    $id = 'detalle-'.str_replace('_', '-', $campo);
    $valida = is_string($valor) && preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $valor);
    [$hora, $minutos] = $valida ? explode(':', $valor) : ['', ''];
@endphp
<fieldset class="selector-hora" data-selector-hora>
    <legend>{{ $etiqueta }}</legend>
    {{-- JS lo habilita con los campos de la categoría, después de sincronizar ambas partes. --}}
    <input type="hidden" id="{{ $id }}" data-hora-valor data-campo="{{ $campo }}" name="detalles[0][{{ $campo }}]" value="{{ $valida ? $valor : '' }}" disabled>
    <div class="selector-hora-controles">
        @foreach(['hora' => ['Hora', 23, $hora], 'minutos' => ['Minutos', 59, $minutos]] as $parte => [$label, $maximo, $seleccion])
            <div>
                <label for="{{ $id }}-{{ $parte }}">{{ $label }}</label>
                <select id="{{ $id }}-{{ $parte }}" data-hora-parte="{{ $parte }}" data-campo="{{ $campo }}_{{ $parte }}" @required($required) @disabled($disabled) @if($error) aria-invalid="true" aria-describedby="error-{{ $campo }}" @endif>
                    <option value="" @selected($seleccion === '')>{{ $parte === 'hora' ? 'HH' : 'MM' }}</option>
                    @for($numero = 0; $numero <= $maximo; $numero++)
                        @php($opcion = str_pad((string) $numero, 2, '0', STR_PAD_LEFT))
                        <option value="{{ $opcion }}" @selected($seleccion === $opcion)>{{ $opcion }}</option>
                    @endfor
                </select>
            </div>
        @endforeach
    </div>
</fieldset>
