@props(['title', 'tableClass' => 'table table-striped table-hover my-0', 'paginator' => null])

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <h1 class="mb-0">{{ $title }}</h1>
        @isset($headingActions)
            <div class="ms-3 pt-2">{{ $headingActions }}</div>
        @endisset
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
