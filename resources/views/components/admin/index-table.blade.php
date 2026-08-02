@props([
    'title',
    'tableClass' => 'table table-striped table-hover my-0',
    'paginator' => null,
    'searchRoute' => null,
    'searchPlaceholder' => 'Cerca...',
    'csvRoute' => null,
])

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <h1 class="mb-0">{{ $title }}</h1>
        @isset($headingActions)
            <div class="ms-3 pt-2">{{ $headingActions }}</div>
        @endisset
        @if ($csvRoute)
            <div class="ms-1 pt-2">
                <a href="{{ $csvRoute }}" class="btn btn-sm btn-outline-secondary" title="Scarica CSV">
                    <i class="bi bi-download me-1"></i>CSV
                </a>
            </div>
        @endif
        @if ($searchRoute)
            <div class="ms-auto">
                <form action="{{ $searchRoute }}" method="GET" class="d-flex gap-2" id="search-form">
                    @foreach (request()->except('q', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <div class="input-group input-group-sm">
                        <input type="text" name="q" class="form-control" placeholder="{{ $searchPlaceholder }}"
                            value="{{ request('q') }}" style="min-width: 220px;">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        @if (request('q'))
                            <a href="{{ $searchRoute }}" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                        @endif
                    </div>
                </form>
            </div>
        @endif
    </div>

    <div class="card my-0">
        <table class="{{ $tableClass }}">
            <thead>
                <tr>
                    {{ $head }}
                </tr>
            </thead>
            <tbody>
                {{ $rows }}
            </tbody>
        </table>
        @if ($paginator)
            <div class="d-flex justify-content-center py-3">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
