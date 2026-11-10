{{--
    Formulario dinámico de requerimientos SIN JavaScript: un único
    formulario envía el tipo de dato junto al resto de campos. El botón
    «Actualizar» usa su propia acción de recarga para volver a mostrar los
    operadores compatibles y el campo de valor adaptado conservando lo ya
    tecleado, sin guardar nada.
--}}
<form method="POST" action="{{ $routeAction }}" class="card">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <h2>Datos del requerimiento</h2>

    <label for="data_type">Tipo de dato</label>
    <div style="display: flex; gap: 0.5rem; align-items: stretch;">
        <select id="data_type" name="data_type">
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected($type === $selectedType)>{{ \App\Services\RequirementRules::TYPE_LABELS[$type] ?? $type }}</option>
            @endforeach
        </select>
        <button type="submit" formaction="{{ $routeRefresh }}" formnovalidate class="btn btn-secondary">Actualizar</button>
    </div>
    @error('data_type') <p class="field-error">{{ $message }}</p> @enderror
    <p class="muted">Pulsa «Actualizar» para recargar los operadores y el campo de valor adecuados al tipo elegido. Lo que ya hayas tecleado se conserva.</p>

    <label for="field_key">Campo</label>
    <input id="field_key" type="text" name="field_key" value="{{ $fieldKey }}" maxlength="100" placeholder="por ejemplo: almacenamiento_gb" required>
    @error('field_key') <p class="field-error">{{ $message }}</p> @enderror

    <label for="operator">Operador ({{ \App\Services\RequirementRules::TYPE_LABELS[$selectedType] ?? $selectedType }})</label>
    <select id="operator" name="operator">
        <option value="">— Elige un operador —</option>
        @foreach ($operators as $operator)
            <option value="{{ $operator }}" @selected($operator === $selectedOperator)>
                {{ $operatorLabels[$operator] ?? $operator }} ({{ $operator }})
            </option>
        @endforeach
    </select>
    @error('operator') <p class="field-error">{{ $message }}</p> @enderror

    <label for="value">Valor</label>
    @switch ($selectedType)
        @case('boolean')
            <select id="value" name="value">
                <option value="1" @selected(strtolower((string) $value) === '1' || strtolower((string) $value) === 'true' || strtolower((string) $value) === 'verdadero' || strtolower((string) $value) === 'si' || strtolower((string) $value) === 'sí')>Verdadero</option>
                <option value="0" @selected(strtolower((string) $value) === '0' || strtolower((string) $value) === 'false' || strtolower((string) $value) === 'falso' || strtolower((string) $value) === 'no')>Falso</option>
            </select>
        @break
        @case('integer')
            <input id="value" type="number" name="value" step="1" value="{{ $value }}">
        @break
        @case('decimal')
            <input id="value" type="number" name="value" step="any" value="{{ $value }}">
        @break
        @default
            <input id="value" type="text" name="value" value="{{ $value }}" maxlength="500">
    @endswitch
    @error('value') <p class="field-error">{{ $message }}</p> @enderror

    <label style="font-weight: 600; margin-top: 1rem;">
        <input type="hidden" name="mandatory" value="0">
        <input type="checkbox" name="mandatory" value="1" @checked((bool) $mandatory) style="margin-right: 0.4rem;">
        Condición obligatoria
    </label>

    <div class="actions">
        <button type="submit" class="btn">{{ $submitLabel }}</button>
        <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
