{{-- Brand mark: uploaded logo or initials. Expects $brand. --}}
@if($brand->logoUrl())
    <span class="site-brand-logo has-image"><img src="{{ $brand->logoUrl() }}" alt=""></span>
@else
    <span class="site-brand-logo" aria-hidden="true">{{ $brand->initials() }}</span>
@endif
