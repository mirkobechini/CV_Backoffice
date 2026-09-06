<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Token API') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Crea e gestisci i token per l\'accesso API. I token permettono alle app esterne di accedere ai dati.') }}
        </p>
    </header>

    @if (session('status') === 'token-created' && session('plainTextToken'))
        <div class="alert alert-success mt-3">
            <strong>{{ __('Token creato!') }}</strong>
            <p class="mb-1">{{ __('Copia ora questo token, non verrà più mostrato:') }}</p>
            <code class="d-block p-2 bg-light rounded">{{ session('plainTextToken') }}</code>
        </div>
    @endif

    @if (session('status') === 'token-revoked')
        <div class="alert alert-success mt-3">
            {{ __('Token revocato con successo.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('profile.tokens.create') }}" class="mt-3">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="token_name" class="form-label">{{ __('Nome token') }}</label>
                <input type="text" id="token_name" name="token_name" class="form-control"
                    placeholder="{{ __('es. App mobile') }}" required>
                @error('token_name')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary">{{ __('Crea token') }}</button>
            </div>
        </div>
    </form>

    @if ($tokens->isNotEmpty())
        <table class="table mt-4">
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
                        <td class="text-end">
                            <form method="POST" action="{{ route('profile.tokens.revoke', $token->id) }}"
                                onsubmit="return confirm('{{ __('Revocare questo token?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="btn btn-sm btn-outline-danger">{{ __('Revoca') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="text-muted mt-3">{{ __('Nessun token API creato.') }}</p>
    @endif
</section>
