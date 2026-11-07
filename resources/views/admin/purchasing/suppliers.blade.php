@extends('layouts.app')

@section('title', 'Suppliers')
@section('page-title', 'Suppliers')

@section('own-validation-errors', '1')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
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
        <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>@forelse($suppliers as $supplier)<tr><td>{{ $supplier->name }}</td><td>{{ $supplier->contact_person ?? '—' }}</td><td>{{ $supplier->phone ?? '—' }}</td><td>{{ $supplier->email ?? '—' }}</td><td>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</td><td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#edit-supplier-{{ $supplier->id }}" title="Edit {{ $supplier->name }}" aria-label="Edit {{ $supplier->name }}"><i class="fas fa-pen"></i></button><button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-supplier-{{ $supplier->id }}" title="Delete {{ $supplier->name }}" aria-label="Delete {{ $supplier->name }}"><i class="fas fa-trash"></i></button></td></tr>@empty<tr><td colspan="6" class="text-muted">No suppliers recorded.</td></tr>@endforelse</tbody>
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

{{-- =========================================================
     EDIT AND DELETE SUPPLIER MODALS
     ONE PER ROW, OUTSIDE THE TABLE
========================================================= --}}
@foreach($suppliers as $supplier)
    @php
        $editBagName = 'editSupplier' . $supplier->id;
        $editBag = $errors->getBag($editBagName);
        $editFailed = $editBag->isNotEmpty();
        $editValue = fn (string $key, $default) => $editFailed ? old($key) : $default;
        $editPrefix = 'edit-supplier-' . $supplier->id;
    @endphp

    <div class="modal fade"
         id="edit-supplier-{{ $supplier->id }}"
         tabindex="-1"
         aria-labelledby="edit-supplier-{{ $supplier->id }}-label"
         aria-hidden="true">

        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.suppliers.update', $supplier) }}">

                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title" id="edit-supplier-{{ $supplier->id }}-label">
                        <i class="fas fa-pen me-2 text-primary"></i>
                        Edit Supplier
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    @if($editFailed)
                        <div class="alert alert-danger py-2" role="alert">
                            <strong>Please correct the following:</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                @foreach($editBag->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-name">
                                Name <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input id="{{ $editPrefix }}-name"
                                   class="form-control @error('name', $editBagName) is-invalid @enderror"
                                   name="name"
                                   value="{{ $editValue('name', $supplier->name) }}"
                                   placeholder="e.g. Kampala Fresh Produce Ltd"
                                   required
                                   maxlength="255">
                            @error('name', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-contact-person">Contact person</label>
                            <input id="{{ $editPrefix }}-contact-person"
                                   class="form-control @error('contact_person', $editBagName) is-invalid @enderror"
                                   name="contact_person"
                                   value="{{ $editValue('contact_person', $supplier->contact_person) }}"
                                   placeholder="e.g. Sarah Nakato"
                                   maxlength="255">
                            @error('contact_person', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-phone">Phone</label>
                            <input id="{{ $editPrefix }}-phone"
                                   class="form-control @error('phone', $editBagName) is-invalid @enderror"
                                   name="phone"
                                   value="{{ $editValue('phone', $supplier->phone) }}"
                                   placeholder="e.g. +256 700 000000"
                                   maxlength="50">
                            @error('phone', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-email">Email</label>
                            <input id="{{ $editPrefix }}-email"
                                   class="form-control @error('email', $editBagName) is-invalid @enderror"
                                   type="email"
                                   name="email"
                                   value="{{ $editValue('email', $supplier->email) }}"
                                   placeholder="e.g. orders@supplier.com"
                                   maxlength="255">
                            @error('email', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-tin">Tax ID</label>
                            <input id="{{ $editPrefix }}-tin"
                                   class="form-control @error('tin', $editBagName) is-invalid @enderror"
                                   name="tin"
                                   value="{{ $editValue('tin', $supplier->tin) }}"
                                   placeholder="e.g. 1000234567"
                                   maxlength="100">
                            @error('tin', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="{{ $editPrefix }}-address">Address</label>
                            <textarea class="form-control @error('address', $editBagName) is-invalid @enderror"
                                      id="{{ $editPrefix }}-address"
                                      name="address"
                                      rows="3"
                                      maxlength="2000"
                                      placeholder="e.g. Plot 12, Industrial Area">{{ $editValue('address', $supplier->address) }}</textarea>
                            @error('address', $editBagName)
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
                        Save Changes
                    </button>

                </div>

            </form>

        </div>
    </div>

    <div class="modal fade"
         id="delete-supplier-{{ $supplier->id }}"
         tabindex="-1"
         aria-labelledby="delete-supplier-{{ $supplier->id }}-label"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.suppliers.destroy', $supplier) }}">

                @csrf
                @method('DELETE')

                <div class="modal-header">
                    <h5 class="modal-title" id="delete-supplier-{{ $supplier->id }}-label">
                        <i class="fas fa-trash me-2 text-danger"></i>
                        Delete Supplier
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-2">
                        Remove <strong>{{ $supplier->name }}</strong> from the supplier list?
                    </p>
                    <p class="text-muted small mb-0">
                        Past purchases keep their supplier history. Deletion is refused while this supplier has open purchase orders or purchases with an unpaid balance.
                    </p>
                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>
                        Delete Supplier
                    </button>

                </div>

            </form>

        </div>
    </div>
@endforeach

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

    @foreach($suppliers as $supplier)
        @if($errors->getBag('editSupplier' . $supplier->id)->isNotEmpty())
            (function () {
                const editModal = document.getElementById('edit-supplier-{{ $supplier->id }}');

                if (editModal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(editModal).show();
                }
            })();
        @endif
    @endforeach

});
</script>
@endpush
