@php $prefix = $customer ? 'customer' . $customer->id : 'newCustomer'; @endphp
<label class="form-label" for="{{ $prefix }}Name">Name</label>
<input class="form-control mb-2" id="{{ $prefix }}Name" name="name" value="{{ old('name', $customer?->name) }}" maxlength="255" required>
<label class="form-label" for="{{ $prefix }}Phone">Phone</label>
<input class="form-control mb-2" id="{{ $prefix }}Phone" type="tel" name="phone" value="{{ old('phone', $customer?->phone) }}" maxlength="40" required>
<label class="form-label" for="{{ $prefix }}Email">Email (optional)</label>
<input class="form-control mb-2" id="{{ $prefix }}Email" type="email" name="email" value="{{ old('email', $customer?->email) }}" maxlength="255">
<label class="form-label" for="{{ $prefix }}Dob">Date of birth (optional)</label>
<input class="form-control mb-2" id="{{ $prefix }}Dob" type="date" name="date_of_birth" value="{{ old('date_of_birth', $customer?->date_of_birth?->toDateString()) }}">
<label class="form-label" for="{{ $prefix }}Address">Address (optional)</label>
<textarea class="form-control mb-2" id="{{ $prefix }}Address" name="address" maxlength="2000" rows="2">{{ old('address', $customer?->address) }}</textarea>
<label class="form-label" for="{{ $prefix }}Notes">Notes (optional)</label>
<textarea class="form-control mb-3" id="{{ $prefix }}Notes" name="notes" maxlength="5000" rows="2" placeholder="Preferences, allergies…">{{ old('notes', $customer?->notes) }}</textarea>
