{{-- =========================================================
     EDIT MENU ITEM
========================================================= --}}
<div class="modal fade"
     id="editItemModal{{ $item->id }}"
     tabindex="-1"
     aria-labelledby="editItemModalLabel{{ $item->id }}"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.menu-items.update', $item) }}"
              enctype="multipart/form-data">

            @csrf
            @method('PUT')

            <div class="modal-header">

                <div>
                    <h5 class="modal-title"
                        id="editItemModalLabel{{ $item->id }}">
                        <i class="fas fa-pen-to-square me-2 text-primary"></i>
                        Edit Menu Item
                    </h5>

                    <small class="text-muted">
                        {{ $item->name }}
                    </small>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>

            </div>

            <div class="modal-body">

                <input type="hidden"
                       name="restaurant_id"
                       value="{{ old('restaurant_id', $item->restaurant_id ?? 1) }}">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">
                            Item Name <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               class="form-control"
                               name="name"
                               value="{{ old('name', $item->name) }}"
                               required
                               maxlength="255">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Category <span class="text-danger">*</span>
                        </label>

                        <select class="form-select"
                                name="menu_category_id"
                                required>

                            <option value="">
                                Select category
                            </option>

                            @foreach($categories as $category)
                                <option value="{{ $category->id }}"
                                    @selected((string) old('menu_category_id', $item->menu_category_id) === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Selling Price <span class="text-danger">*</span>
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                {{ config('restaurant.currency', 'UGX') }}
                            </span>

                            <input type="number"
                                   min="0"
                                   step="0.01"
                                   class="form-control"
                                   name="price"
                                   value="{{ old('price', $item->price) }}"
                                   required>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Cost Price
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                {{ config('restaurant.currency', 'UGX') }}
                            </span>

                            <input type="number"
                                   min="0"
                                   step="0.01"
                                   class="form-control"
                                   name="cost_price"
                                   value="{{ old('cost_price', $item->cost_price ?? 0) }}">

                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Preparation Time
                        </label>

                        <div class="input-group">

                            <input type="number"
                                   min="0"
                                   class="form-control"
                                   name="preparation_minutes"
                                   value="{{ old('preparation_minutes', $item->preparation_minutes ?? 0) }}">

                            <span class="input-group-text">
                                minutes
                            </span>

                        </div>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label">
                            Replace Image
                        </label>

                        <input type="file"
                               class="form-control"
                               name="image"
                               accept="image/jpeg,image/png,image/webp"
                               data-image-input
                               data-preview-target="editImagePreview{{ $item->id }}"
                               data-placeholder-target="editImagePlaceholder{{ $item->id }}">

                        <small class="text-muted">
                            Leave empty to keep the current image.
                        </small>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">
                            Current / New Preview
                        </label>

                        <div class="menu-image-preview d-flex align-items-center justify-content-center">

                            @if($item->image_path)

                                <img id="editImagePreview{{ $item->id }}"
                                     src="{{ $item->image_url }}"
                                     alt="{{ $item->name }}">

                                <span id="editImagePlaceholder{{ $item->id }}"
                                      class="text-muted d-none">
                                    <i class="fas fa-image me-1"></i>
                                    No image
                                </span>

                            @else

                                <img id="editImagePreview{{ $item->id }}"
                                     alt="Image preview"
                                     class="d-none">

                                <span id="editImagePlaceholder{{ $item->id }}"
                                      class="text-muted">
                                    <i class="fas fa-image me-1"></i>
                                    No image
                                </span>

                            @endif

                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">
                            Description
                        </label>

                        <textarea class="form-control"
                                  name="description"
                                  rows="3"
                                  maxlength="2000">{{ old('description', $item->description) }}</textarea>
                    </div>

                    <div class="col-12">

                        <div class="border rounded p-3 bg-light">

                            <div class="form-check form-switch mb-3">

                                <input type="hidden"
                                       name="is_available"
                                       value="0">

                                <input class="form-check-input"
                                       type="checkbox"
                                       name="is_available"
                                       value="1"
                                       id="available{{ $item->id }}"
                                       @checked((bool) old('is_available', $item->is_available))>

                                <label class="form-check-label fw-semibold"
                                       for="available{{ $item->id }}">
                                    Available for sale
                                </label>

                            </div>

                            <div class="form-check form-switch">

                                <input type="hidden"
                                       name="track_inventory"
                                       value="0">

                                <input class="form-check-input"
                                       type="checkbox"
                                       name="track_inventory"
                                       value="1"
                                       id="trackInventory{{ $item->id }}"
                                       @checked((bool) old('track_inventory', $item->track_inventory))>

                                <label class="form-check-label fw-semibold"
                                       for="trackInventory{{ $item->id }}">
                                    Track inventory for this item
                                </label>

                            </div>

                        </div>

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
                    Update Item
                </button>

            </div>

        </form>

    </div>
</div>


{{-- =========================================================
     DELETE CONFIRMATION
========================================================= --}}
<div class="modal fade"
     id="deleteItemModal{{ $item->id }}"
     tabindex="-1"
     aria-labelledby="deleteItemModalLabel{{ $item->id }}"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.menu-items.destroy', $item) }}">

            @csrf
            @method('DELETE')

            <div class="modal-header border-0 pb-0">

                <h5 class="modal-title"
                    id="deleteItemModalLabel{{ $item->id }}">
                    Delete Menu Item
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>

            </div>

            <div class="modal-body text-center py-4">

                <div class="delete-icon-wrap bg-danger-subtle text-danger">
                    <i class="fas fa-trash fa-lg"></i>
                </div>

                <h5 class="mb-2">
                    Delete {{ $item->name }}?
                </h5>

                <p class="text-muted mb-0">
                    This item will be removed from the active menu.
                    This action cannot be undone unless your model uses soft deletes.
                </p>

            </div>

            <div class="modal-footer border-0 justify-content-center pt-0 pb-4">

                <button type="button"
                        class="btn btn-light px-4"
                        data-bs-dismiss="modal">
                    <i class="fas fa-xmark me-1"></i>
                    Cancel
                </button>

                <button type="submit"
                        class="btn btn-danger px-4">
                    <i class="fas fa-trash me-1"></i>
                    Delete
                </button>

            </div>

        </form>

    </div>
</div>
