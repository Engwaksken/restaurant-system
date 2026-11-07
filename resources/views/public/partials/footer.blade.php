{{-- Public site footer. Expects $brand. --}}
<footer class="site-footer" id="contact">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <a href="{{ route('home') }}" class="site-brand mb-3">
                    @include('public.partials.logo')
                    <span class="min-w-0">
                        <span class="site-brand-name d-block">{{ $brand->name() }}</span>
                        @if($brand->tagline())<span class="site-brand-tag">{{ $brand->tagline() }}</span>@endif
                    </span>
                </a>
                <p class="small mb-3">{{ \Illuminate\Support\Str::limit($brand->about() ?? 'Freshly prepared food, ordered online in a few taps. Pick it up or have it delivered to your door.', 180) }}</p>
                @if($brand->socialLinks())
                    <div class="social-links">
                        @foreach($brand->socialLinks() as $link)
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" aria-label="{{ $link['label'] }}"><i class="{{ $link['icon'] }}"></i></a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="col-6 col-lg-2">
                <h4>Explore</h4>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    <li><a href="{{ route('home') }}#menu">Menu</a></li>
                    @if($brand->about())<li><a href="{{ route('home') }}#about">About us</a></li>@endif
                    <li><a href="{{ route('home') }}#contact">Contact</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h4>Visit us</h4>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    @if($brand->address())<li><i class="fas fa-location-dot me-2"></i>{{ $brand->address() }}</li>@endif
                    @if($brand->phone())<li><i class="fas fa-phone me-2"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', $brand->phone()) }}">{{ $brand->phone() }}</a></li>@endif
                    @if($brand->email())<li><i class="fas fa-envelope me-2"></i><a href="mailto:{{ $brand->email() }}">{{ $brand->email() }}</a></li>@endif
                </ul>
            </div>
            <div class="col-lg-3">
                <h4>Opening hours</h4>
                <p class="small mb-0" style="white-space: pre-line">{{ $brand->openingHours() ?? 'Open daily. Call us for today\'s hours.' }}</p>
            </div>
        </div>
        <div class="site-footer-bottom d-flex flex-column flex-sm-row justify-content-between gap-2">
            <span>&copy; {{ now()->year }} {{ $brand->name() }}. All rights reserved.</span>
            <span>Order online · Pickup · Delivery</span>
        </div>
    </div>
</footer>
