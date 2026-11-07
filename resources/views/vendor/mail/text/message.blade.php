@php($brand = app(\App\Support\BrandResolver::class)->current())
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="url('/')">
{{ $brand->name() }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
@if($brand->emailFooter())
{{ $brand->emailFooter() }}

@endif
© {{ date('Y') }} {{ $brand->name() }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
