@extends('layouts.app')

@section('title', 'Suppliers')
@section('page-title', 'Suppliers')

@section('content')
<div class="row g-4">
    <div class="col-lg-4"><div class="panel p-4">
        <h2 class="h5">Add supplier</h2>
        <form method="POST" action="{{ route('admin.suppliers.store') }}">
            @csrf
            @foreach(['name' => 'Name', 'contact_person' => 'Contact person', 'phone' => 'Phone', 'email' => 'Email', 'tin' => 'Tax ID'] as $field => $label)
                <label class="form-label" for="supplier-{{ $field }}">{{ $label }}</label>
                <input class="form-control mb-2" id="supplier-{{ $field }}" name="{{ $field }}" @required($field === 'name') maxlength="255" @if($field === 'email') type="email" @endif>
            @endforeach
            <label class="form-label" for="supplier-address">Address</label>
            <textarea class="form-control mb-3" id="supplier-address" name="address" maxlength="2000"></textarea>
            <button class="btn btn-primary" type="submit">Save supplier</button>
        </form>
    </div></div>
    <div class="col-lg-8"><div class="panel p-4">
        <h2 class="h5">Restaurant suppliers</h2>
        <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>@forelse($suppliers as $supplier)<tr><td>{{ $supplier->name }}</td><td>{{ $supplier->contact_person ?? '—' }}</td><td>{{ $supplier->phone ?? '—' }}</td><td>{{ $supplier->email ?? '—' }}</td><td>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No suppliers recorded.</td></tr>@endforelse</tbody>
        </table></div>{{ $suppliers->links() }}
    </div></div>
</div>
@endsection
