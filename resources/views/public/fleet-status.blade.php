<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Stato flotta') }} — {{ $groupName }}</title>
    @vite(['resources/css/app.css'])
</head>

<body style="background:var(--bg); color:var(--text); min-height:100vh; margin:0;">
    <div style="max-width:640px; margin:0 auto; padding:32px 20px;">
        <h1 style="font-size:20px; margin-bottom:4px;">{{ __('Stato flotta') }}</h1>
        <div class="hint" style="margin-bottom:24px;">{{ $groupName }} · {{ __('Aggiornato al') }} {{ now()->format('d/m/Y H:i') }}</div>

        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Mezzo') }}</th>
                            <th>{{ __('Stato') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vehicles as $vehicle)
                            <tr>
                                <td class="code">{{ $vehicle['internal_code'] }}</td>
                                <td>
                                    <span class="badge {{ $vehicle['available'] ? 'b-green' : 'b-amber' }}">
                                        {{ $vehicle['available'] ? __('Disponibile') : __('In officina') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="empty">{{ __('Nessun mezzo registrato.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
