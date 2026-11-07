@php $p = $branch ? 'branch' . $branch->id : 'newBranch'; @endphp
<div class="row g-2">
    <div class="col-md-7">
        <label class="form-label" for="{{ $p }}Name">Name <span class="text-danger" aria-hidden="true">*</span></label>
        <input class="form-control mb-2 @error('name') is-invalid @enderror" id="{{ $p }}Name" name="name" value="{{ $branch ? $branch->name : old('name') }}" maxlength="255" placeholder="e.g. Kampala Central" required>
        @error('name')<div class="invalid-feedback mb-2">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-5">
        <label class="form-label" for="{{ $p }}Code">Code <span class="text-danger" aria-hidden="true">*</span></label>
        <input class="form-control mb-2 @error('code') is-invalid @enderror" id="{{ $p }}Code" name="code" value="{{ $branch ? $branch->code : old('code') }}" maxlength="30" pattern="[A-Za-z0-9_\-]+" placeholder="e.g. KLA-01" required>
        @error('code')<div class="invalid-feedback mb-2">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $p }}Phone">Phone</label>
        <input class="form-control mb-2 @error('phone') is-invalid @enderror" id="{{ $p }}Phone" type="tel" name="phone" value="{{ $branch ? $branch->phone : old('phone') }}" maxlength="40" placeholder="e.g. +256 700 000 000">
        @error('phone')<div class="invalid-feedback mb-2">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $p }}Email">Email</label>
        <input class="form-control mb-2 @error('email') is-invalid @enderror" id="{{ $p }}Email" type="email" name="email" value="{{ $branch ? $branch->email : old('email') }}" maxlength="255" placeholder="e.g. kampala@restaurant.com">
        @error('email')<div class="invalid-feedback mb-2">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $p }}Address">Address</label>
        <textarea class="form-control mb-3 @error('address') is-invalid @enderror" id="{{ $p }}Address" name="address" maxlength="2000" rows="2" placeholder="e.g. Plot 12, Kampala Road">{{ $branch ? $branch->address : old('address') }}</textarea>
        @error('address')<div class="invalid-feedback mb-3">{{ $message }}</div>@enderror
    </div>
</div>
