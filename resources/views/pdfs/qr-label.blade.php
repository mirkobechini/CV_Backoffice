<style>
    @page {
        margin: 4mm;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        text-align: center;
    }

    .qr {
        width: 48mm;
        height: 48mm;
        margin: 0 auto 2mm;
    }

    .qr svg {
        width: 100%;
        height: 100%;
    }

    .title {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 1mm;
    }

    .subtitle {
        font-size: 11px;
        color: #555;
    }
</style>

<div class="qr">{!! $qrSvg !!}</div>
<div class="title">{{ $title }}</div>
@if ($subtitle)
    <div class="subtitle">{{ $subtitle }}</div>
@endif
