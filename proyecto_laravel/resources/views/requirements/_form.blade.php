{{--
    Formulario dinámico de requerimientos SIN JavaScript:
    - Formulario 1 (GET): cambia el tipo de dato y recarga la página con
      los operadores compatibles y el campo adaptado, conservando lo ya
      tecleado en campos ocultos.
    - Formulario 2 (POST): envío principal; el servidor revalida todo.
--}}
<h2>Formulario de actualización del tipo</h2>
<form method="GET" action="{{ $routeTarget }}" class="card" style="border-left: 4px solid var(--kdd);">
    <fieldset>
        <legend><strong>Paso 1 — Elige el tipo de dato</strong></legend>
        <p class="muted">Pulsa «Actualizar» para recargar la página con los operadores y el campo de valor adecuados. Lo que ya hayas tecleado se conserva.</p>
        <input type="hidden" name="field_key" value="{{ $fieldKey }}">
        <input type="hidden" name="operator" value="{{ $selectedOperator }}">
        <input type="hidden" name="value" value="{{ $value }}">
        @if ($mandatory)
            <input type="hidden" name="mandatory" value="1">
        @endif
        <label for="data_type" style="margin-top: 0;">Tipo de dato</label>
        <div style="display: flex; gap: 0.5rem; align-items: stretch;">
            <select id="data_type" name="data_type">
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected($type === $selectedType)>{{ \App\Services\RequirementRules::TYPE_LABELS[$type] ?? $type }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn">Actualizar</button>
        </div>
        @error('data_type') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>
</form>

<form method="POST" action="{{ $routeAction }}" class="card">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <h2>Paso 2 — Datos del requerimiento</h2>

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
                <option value="1"@selected(strtolower((string) $value) === '1' || strtolower((string) $value) === 'true' || strtolower((string) $value) === 'verdadero' || strtolower((string) $value) === 'si' || strtolower((string) $value) === 'sí')>Verdadero</option>
                <option value="0"@selected(strtolower((string) $value) === '0' || strtolower((string) $value) === 'false' || strtolower((string) $value) === 'falso' || strtolower((string) $value) === 'no')>Falso</option>
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
        <input type="checkbox" name="mandatory" value="1" @checked((bool) $mandatory) style="margin-right: 0.4rem;">
        Condición obligatoria
    </label>

    <div class="actions">
        <button type="submit" class="btn">{{ $submitLabel }}</button>
        <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
