@props(['title', 'tableClass' => 'table table-striped table-hover my-0'])

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
    </div>
</div>
