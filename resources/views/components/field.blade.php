@props(['name', 'label', 'value' => '', 'type' => 'text', 'required' => false])
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span aria-hidden="true">*</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }} @required($required) @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif>
    @error($name)<div class="invalid-feedback" id="{{ $name }}-error">{{ $message }}</div>@enderror
</div>
