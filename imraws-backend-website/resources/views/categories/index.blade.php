@extends('layouts.app')
@section('title', 'Categories')
@section('content')
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
    <div class="page-header" style="margin-bottom:0;">
      <h1>Categories</h1>
      <p>Manage complaint categories and their properties</p>
    </div>
    <button class="btn-primary" style="border-radius:9999px;" onclick="document.getElementById('addCategoryForm').classList.toggle('hidden')"><i data-lucide="plus"></i> Add Category</button>
  </div>

  @if(session('success'))
    <div style="background:#dcfce7;color:#166534;padding:0.6rem 0.9rem;border-radius:0.5rem;font-size:0.85rem;margin-bottom:1rem;">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div style="background:#fee2e2;color:#991b1b;padding:0.6rem 0.9rem;border-radius:0.5rem;font-size:0.85rem;margin-bottom:1rem;">{{ session('error') }}</div>
  @endif

  <div id="addCategoryForm" class="hidden" style="background:white;border-radius:1rem;padding:1.25rem;border:1px solid #f1f5f9;margin-bottom:1rem;">
    <h3 style="font-weight:600;color:#1e293b;margin-bottom:0.75rem;">Add Category</h3>
    <form method="POST" action="{{ route('categories.store') }}">
      @csrf
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Category Name <span style="color:#ef4444">*</span></label>
          <input class="form-input" name="category_name" value="{{ old('category_name') }}" placeholder="e.g. Metering" required maxlength="32" />
          @error('category_name') <span style="color:#ef4444;font-size:0.75rem;margin-top:0.15rem;display:block;">{{ $message }}</span> @enderror
        </div>
        <div>
          <label class="form-label">Display Label</label>
          <input class="form-input" name="label" value="{{ old('label') }}" placeholder="e.g. Metering Issue" maxlength="64" />
        </div>
        <div>
          <label class="form-label">Color (hex)</label>
          <input class="form-input" name="color" value="{{ old('color') }}" placeholder="#3b82f6" maxlength="7" pattern="#[0-9A-Fa-f]{6}" />
        </div>
        <div>
          <label class="form-label">Description</label>
          <input class="form-input" name="description" value="{{ old('description') }}" placeholder="Brief description..." maxlength="500" />
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;margin-top:0.75rem;gap:0.5rem;">
        <button type="button" class="btn-outline" onclick="document.getElementById('addCategoryForm').classList.add('hidden')">Cancel</button>
        <button type="submit" class="btn-primary"><i data-lucide="plus"></i> Save</button>
      </div>
    </form>
  </div>

  <div class="grid grid-cols-3 gap-4">
    <div class="stat-card"><div><div class="stat-label">Total Categories</div><div class="stat-value">{{ $categories->count() }}</div></div></div>
    <div class="stat-card"><div><div class="stat-label">Active</div><div class="stat-value">{{ $categories->where('is_active')->count() }}</div></div></div>
    <div class="stat-card"><div><div class="stat-label">Total Complaints</div><div class="stat-value">{{ $incidentCount ?? 0 }}</div></div></div>
  </div>

  <div class="grid grid-cols-2 gap-4 mt-4">
    @foreach($categories as $cat)
      @php
        $count  = $counts[$cat->category_name] ?? 0;
        $color  = $cat->color ?: '#64748b';
        $label  = $cat->displayLabel();
        $hidden = !$cat->is_active;
      @endphp
      <div class="category-card" style="{{ $hidden ? 'opacity:0.45;filter:grayscale(0.4);' : '' }}">
        {{-- view / edit mode toggle --}}
        <div id="view-{{ $cat->id }}">
          <div class="card-header">
            <div class="title-wrap"><span style="color:{{ $color }};"><i data-lucide="folder"></i></span> {{ $label }}
              @if($hidden)<span class="badge-pill badge-gray" style="margin-left:0.5rem;">Hidden</span>@endif
            </div>
            <div style="display:flex;gap:0.35rem;">
              @if($cat->is_active)
                <form method="POST" action="{{ route('categories.toggle', $cat->id) }}" style="display:inline;">
                  @csrf @method('PATCH')
                  <button type="submit" class="action-icon" title="Remove from list" style="cursor:pointer;background:none;border:none;padding:0;color:inherit;"><i data-lucide="eye-off"></i></button>
                </form>
              @else
                <form method="POST" action="{{ route('categories.toggle', $cat->id) }}" style="display:inline;">
                  @csrf @method('PATCH')
                  <button type="submit" class="action-icon" title="Restore" style="cursor:pointer;background:none;border:none;padding:0;color:#2563eb;"><i data-lucide="eye"></i></button>
                </form>
              @endif
              <button class="action-icon" onclick="document.getElementById('view-{{ $cat->id }}').classList.add('hidden');document.getElementById('edit-{{ $cat->id }}').classList.remove('hidden')" style="cursor:pointer;background:none;border:none;padding:0;color:inherit;" title="Edit"><i data-lucide="pencil"></i></button>
              <form method="POST" action="{{ route('categories.destroy', $cat->id) }}" style="display:inline;" onsubmit="return confirm('Delete this category? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="action-icon" title="Delete" style="cursor:pointer;background:none;border:none;padding:0;color:inherit;"><i data-lucide="trash-2"></i></button>
              </form>
            </div>
          </div>
          <div class="card-desc">{{ $cat->description }}</div>
          <div class="card-footer">
            <span>{{ $count }} Complaint{{ $count === 1 ? '' : 's' }}</span>
            <span class="category-dot" style="background:{{ $color }};"></span>
          </div>
        </div>

        {{-- edit mode --}}
        <div id="edit-{{ $cat->id }}" class="hidden">
          <form method="POST" action="{{ route('categories.update', $cat->id) }}">
            @csrf @method('PATCH')
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
              <div>
                <label class="form-label">Category Name</label>
                <input class="form-input" name="category_name" value="{{ old('category_name', $cat->category_name) }}" required maxlength="32" />
                @error('category_name') <span style="color:#ef4444;font-size:0.75rem;display:block;">{{ $message }}</span> @enderror
              </div>
              <div>
                <label class="form-label">Display Label</label>
                <input class="form-input" name="label" value="{{ old('label', $cat->label) }}" maxlength="64" />
              </div>
              <div>
                <label class="form-label">Color</label>
                <input class="form-input" name="color" value="{{ old('color', $cat->color) }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" />
              </div>
              <div>
                <label class="form-label">Description</label>
                <input class="form-input" name="description" value="{{ old('description', $cat->description) }}" maxlength="500" />
              </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:0.75rem;gap:0.5rem;">
              <button type="button" class="btn-outline" onclick="this.closest('.hidden,div[id^=edit]').classList.add('hidden');document.getElementById('view-{{ $cat->id }}').classList.remove('hidden')">Cancel</button>
              <button type="submit" class="btn-primary"><i data-lucide="save"></i> Save</button>
            </div>
          </form>
        </div>
      </div>
    @endforeach
  </div>
@endsection