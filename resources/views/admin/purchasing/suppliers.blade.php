@extends('layouts.app')

@section('title', 'Suppliers')
@section('page-title', 'Suppliers')

@section('own-validation-errors', '1')

@section('content')
<div class="container-fluid px-0">

    <div class="panel p-4 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="h5 mb-1">Restaurant suppliers</h2>
            <p class="text-muted small mb-0">Keep supplier contacts and tax details for purchase orders and stock receipts.</p>
        </div>

        <button type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#add-supplier">
            <i class="fas fa-plus me-1"></i>
            Add supplier
        </button>
    </div>

    <div class="panel p-4">
        <h2 class="h5">Supplier list</h2>
        <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>@forelse($suppliers as $supplier)<tr><td>{{ $supplier->name }}</td><td>{{ $supplier->contact_person ?? '—' }}</td><td>{{ $supplier->phone ?? '—' }}</td><td>{{ $supplier->email ?? '—' }}</td><td>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No suppliers recorded.</td></tr>@endforelse</tbody>
        </table></div>{{ $suppliers->links() }}
    </div>

</div>


{{-- =========================================================
     ADD SUPPLIER MODAL
     OUTSIDE THE TABLE
========================================================= --}}
<div class="modal fade"
     id="add-supplier"
     tabindex="-1"
     aria-labelledby="add-supplier-label"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.suppliers.store') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title" id="add-supplier-label">
                    <i class="fas fa-truck me-2 text-primary"></i>
                    Add Supplier
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">

                @if($errors->any())
                    <div class="alert alert-danger py-2" role="alert">
                        <strong>Please correct the following:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label" for="supplier-name">
                            Name <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input class="form-control @error('name') is-invalid @enderror"
                               id="supplier-name"
                               name="name"
                               value="{{ old('name') }}"
                               placeholder="e.g. Kampala Fresh Produce Ltd"
                               required
                               maxlength="255">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="supplier-contact_person">Contact person</label>
                        <input class="form-control @error('contact_person') is-invalid @enderror"
                               id="supplier-contact_person"
                               name="contact_person"
                               value="{{ old('contact_person') }}"
                               placeholder="e.g. Sarah Nakato"
                               maxlength="255">
                        @error('contact_person')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="supplier-phone">Phone</label>
                        <input class="form-control @error('phone') is-invalid @enderror"
                               id="supplier-phone"
                               name="phone"
                               value="{{ old('phone') }}"
                               placeholder="e.g. +256 700 000000"
                               maxlength="255">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="supplier-email">Email</label>
                        <input class="form-control @error('email') is-invalid @enderror"
                               id="supplier-email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               placeholder="e.g. orders@supplier.com"
                               maxlength="255">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="supplier-tin">Tax ID</label>
                        <input class="form-control @error('tin') is-invalid @enderror"
                               id="supplier-tin"
                               name="tin"
                               value="{{ old('tin') }}"
                               placeholder="e.g. 1000234567"
                               maxlength="255">
                        @error('tin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="supplier-address">Address</label>
                        <textarea class="form-control @error('address') is-invalid @enderror"
                                  id="supplier-address"
                                  name="address"
                                  rows="3"
                                  maxlength="2000"
                                  placeholder="e.g. Plot 12, Industrial Area">{{ old('address') }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="modal-footer">

                <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">
                    <i class="fas fa-xmark me-1"></i>
                    Cancel
                </button>

                <button type="submit"
                        class="btn btn-primary">
                    <i class="fas fa-floppy-disk me-1"></i>
                    Save Supplier
                </button>

            </div>

        </form>

    </div>
</div>

@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    @if($errors->any())
        const supplierModal = document.getElementById('add-supplier');

        if (supplierModal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(supplierModal).show();
        }
    @endif

});
</script>
@endpush
