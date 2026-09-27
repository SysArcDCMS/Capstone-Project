@if ($paginator->hasPages())
  <nav class="imraws-pagination" aria-label="Pagination">
    @if ($paginator->onFirstPage())
      <span class="imraws-pagination-btn disabled" aria-disabled="true">
        <svg class="imraws-pagination-chev" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
        </svg>
      </span>
    @else
      <a class="imraws-pagination-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">
        <svg class="imraws-pagination-chev" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
        </svg>
      </a>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))
        <span class="imraws-pagination-btn disabled" aria-disabled="true">{{ $element }}</span>
      @endif

      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <span class="imraws-pagination-btn active" aria-current="page">{{ $page }}</span>
          @else
            <a class="imraws-pagination-btn" href="{{ $url }}">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <a class="imraws-pagination-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">
        <svg class="imraws-pagination-chev" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
        </svg>
      </a>
    @else
      <span class="imraws-pagination-btn disabled" aria-disabled="true">
        <svg class="imraws-pagination-chev" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
        </svg>
      </span>
    @endif
  </nav>
@endif