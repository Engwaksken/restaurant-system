@php $p = $branch ? 'branch' . $branch->id : 'newBranch'; @endphp
<div class="row g-2">
    <div class="col-md-7"><label class="form-label" for="{{ $p }}Name">Name</label><input class="form-control mb-2" id="{{ $p }}Name" name="name" value="{{ $branch ? $branch->name : old('name') }}" maxlength="255" required></div>
    <div class="col-md-5"><label class="form-label" for="{{ $p }}Code">Code</label><input class="form-control mb-2" id="{{ $p }}Code" name="code" value="{{ $branch ? $branch->code : old('code') }}" maxlength="30" pattern="[A-Za-z0-9_\-]+" required></div>
    <div class="col-md-6"><label class="form-label" for="{{ $p }}Phone">Phone</label><input class="form-control mb-2" id="{{ $p }}Phone" type="tel" name="phone" value="{{ $branch ? $branch->phone : old('phone') }}" maxlength="40"></div>
    <div class="col-md-6"><label class="form-label" for="{{ $p }}Email">Email</label><input class="form-control mb-2" id="{{ $p }}Email" type="email" name="email" value="{{ $branch ? $branch->email : old('email') }}" maxlength="255"></div>
    <div class="col-12"><label class="form-label" for="{{ $p }}Address">Address</label><textarea class="form-control mb-3" id="{{ $p }}Address" name="address" maxlength="2000" rows="2">{{ $branch ? $branch->address : old('address') }}</textarea></div>
</div>
