@php $prefix = $customer ? 'customer' . $customer->id : 'newCustomer'; @endphp
<div class="mb-3">
    <label class="form-label" for="{{ $prefix }}Name">Name <span class="text-danger" aria-hidden="true">*</span></label>
    <input class="form-control @error('name') is-invalid @enderror" id="{{ $prefix }}Name" name="name" value="{{ old('name', $customer?->name) }}" maxlength="255" placeholder="e.g. Amina Nakato" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="{{ $prefix }}Phone">Phone <span class="text-danger" aria-hidden="true">*</span></label>
    <input class="form-control @error('phone') is-invalid @enderror" id="{{ $prefix }}Phone" type="tel" name="phone" value="{{ old('phone', $customer?->phone) }}" maxlength="40" placeholder="e.g. +256 700 000 000" required>
    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="{{ $prefix }}Email">Email (optional)</label>
    <input class="form-control @error('email') is-invalid @enderror" id="{{ $prefix }}Email" type="email" name="email" value="{{ old('email', $customer?->email) }}" maxlength="255" placeholder="e.g. amina@example.com">
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="{{ $prefix }}Dob">Date of birth (optional)</label>
    <input class="form-control @error('date_of_birth') is-invalid @enderror" id="{{ $prefix }}Dob" type="date" name="date_of_birth" value="{{ old('date_of_birth', $customer?->date_of_birth?->toDateString()) }}">
    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="{{ $prefix }}Address">Address (optional)</label>
    <textarea class="form-control @error('address') is-invalid @enderror" id="{{ $prefix }}Address" name="address" maxlength="2000" rows="2" placeholder="e.g. Plot 12, Kampala Road">{{ old('address', $customer?->address) }}</textarea>
    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="{{ $prefix }}Notes">Notes (optional)</label>
    <textarea class="form-control @error('notes') is-invalid @enderror" id="{{ $prefix }}Notes" name="notes" maxlength="5000" rows="2" placeholder="Preferences, allergies…">{{ old('notes', $customer?->notes) }}</textarea>
    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
