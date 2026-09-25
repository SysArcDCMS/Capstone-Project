{{--
  Category color dropdown — color-tinted picker for any category <select>.
  Usage:
    @include('components.category_select', [
      'name'      => 'category',          // hidden input name
      'selected'  => request('category'), // current value (''/'All')
      'categories'=> $categories,         // Category[] (has displayLabel())
      'colors'    => $category_colors,    // [category_name => hex]
      'placeholder'=> 'All Categories',
    ])
  The value lives in a hidden input so enclosing <form> submits normally.
  Rows are colored via tbl_categories.color; picking dispatches
  window.catDDChanged(dd, value, color, label) when defined.
--}}
@php
    $ddId = $id ?? ($name ?? 'category');
    $selectedLabel = '';
    $selectedColor = '#cbd5e1';
    foreach ($categories ?? [] as $c) {
        if (($c->category_name ?? null) === $selected) {
            $selectedLabel = $c->displayLabel();
            $selectedColor = ($colors[$c->category_name] ?? '#cbd5e1');
            break;
        }
    }
@endphp
<div class="cat-dd" id="{{ $ddId }}">
  <input type="hidden" name="{{ $name }}" value="{{ $selected }}" data-cat-dd-value />
  <button type="button" class="cat-dd-btn" data-cat-dd-toggle title="Select category">
    <span class="cat-dd-label">
      <span class="cat-dd-dot" style="background:{{ $selectedColor }};"></span>
      <span data-cat-dd-text>{{ $selectedLabel !== '' ? $selectedLabel : $placeholder }}</span>
    </span>
    <span class="cat-dd-caret">&#9662;</span>
  </button>
  <div class="cat-dd-panel" role="listbox">
    <button type="button" class="cat-dd-row{{ $selected === null || $selected === '' ? ' is-selected' : '' }}" data-value="" data-color="#cbd5e1">
      <span class="cat-dd-dot" style="background:#cbd5e1;"></span>{{ $placeholder }}
    </button>
    @foreach($categories ?? [] as $c)
      @php $cc = ($colors[$c->category_name] ?? '#cbd5e1'); @endphp
      <button type="button" class="cat-dd-row{{ ($c->category_name === $selected) ? ' is-selected' : '' }}" data-value="{{ $c->category_name }}" data-color="{{ $cc }}">
        <span class="cat-dd-dot" style="background:{{ $cc }};"></span>{{ $c->displayLabel() }}
      </button>
    @endforeach
  </div>
</div>