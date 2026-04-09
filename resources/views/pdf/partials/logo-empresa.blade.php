@php
    $__logoPdfSrc = '';
    $__logoPath = public_path('avatar.jpg');
    if ($__logoPath && is_readable($__logoPath)) {
        $__logoPdfSrc = 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($__logoPath));
    }
@endphp
@if($__logoPdfSrc !== '')
    <img src="{{ $__logoPdfSrc }}" alt="" width="90" style="max-height: 56px; width: auto; max-width: 110px; height: auto; display: block;" />
@endif
