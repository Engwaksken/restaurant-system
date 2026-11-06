@php
    $name = $field['name'];
    $label = $field['label'] ?? \Illuminate\Support\Str::headline($name);
    $type = $field['type'] ?? 'text';
    $required = (bool) ($field['required'] ?? false);
    $col = $field['col'] ?? 'col-md-6';
    $placeholder = $field['placeholder'] ?? '';
    $inputId = $prefix . '_' . str_replace(['.', '[', ']'], '_', $name);
    $current = old($name, $value);
@endphp

<div class="{{ $col }}">
    <label for="{{ $inputId }}" class="form-label">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>

    @if($type === 'textarea')
        <textarea name="{{ $name }}"
                  id="{{ $inputId }}"
                  rows="4"
                  class="form-control @error($name) is-invalid @enderror"
                  placeholder="{{ $placeholder }}"
                  @required($required)>{{ $current }}</textarea>

    @elseif($type === 'select')
        <select name="{{ $name }}"
                id="{{ $inputId }}"
                class="form-select @error($name) is-invalid @enderror"
                @required($required)>
            @unless($required)
                <option value="">Select {{ strtolower($label) }}</option>
            @endunless

            @foreach(($field['options'] ?? []) as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>

    @else
        @php
            $inputValue = $current;
            if ($type === 'datetime-local' && $current) {
                try {
                    $inputValue = \Illuminate\Support\Carbon::parse($current)->format('Y-m-d\TH:i');
                } catch (\Throwable $e) {
                    $inputValue = $current;
                }
            }
        @endphp

        <input type="{{ $type }}"
               name="{{ $name }}"
               id="{{ $inputId }}"
               value="{{ $type === 'password' ? '' : $inputValue }}"
               class="form-control @error($name) is-invalid @enderror"
               placeholder="{{ $placeholder }}"
               @if(isset($field['step'])) step="{{ $field['step'] }}" @endif
               @required($required)>
    @endif

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
