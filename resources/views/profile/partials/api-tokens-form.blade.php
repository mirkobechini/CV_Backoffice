<div class="head">
    <h3>{{ __('Token API') }}</h3>
</div>
<div class="body">
    <p class="hint" style="margin-bottom:14px;">
        {{ __("Crea e gestisci i token per l'accesso API. I token permettono alle app esterne di accedere ai dati.") }}
    </p>

    @if (session('status') === 'token-created' && session('plainTextToken'))
        <div class="alert success">
            <div>
                <strong>{{ __('Token creato!') }}</strong>
                <p style="margin:4px 0 0;">{{ __('Copia ora questo token, non verrà più mostrato:') }}</p>
                <code>{{ session('plainTextToken') }}</code>
            </div>
        </div>
    @endif

    @if (session('status') === 'token-revoked')
        <div class="alert success">{{ __('Token revocato con successo.') }}</div>
    @endif

    <form method="POST" action="{{ route('profile.tokens.create') }}" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px;">
        @csrf
        <div class="field" style="flex:1; min-width:220px; margin-bottom:0;">
            <label for="token_name">{{ __('Nome token') }}</label>
            <input type="text" id="token_name" name="token_name" class="input @error('token_name') is-invalid @enderror"
                placeholder="{{ __('es. App mobile') }}" required>
            @error('token_name')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn primary">{{ __('Crea token') }}</button>
    </form>

    @if ($tokens->isNotEmpty())
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Creato il') }}</th>
                        <th>{{ __('Ultimo utilizzo') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tokens as $token)
                        <tr>
                            <td>{{ $token->name }}</td>
                            <td>{{ $token->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $token->last_used_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('profile.tokens.revoke', $token->id) }}"
                                    onsubmit="return confirm('{{ __('Revocare questo token?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn danger sm">{{ __('Revoca') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="hint">{{ __('Nessun token API creato.') }}</p>
    @endif
</div>
