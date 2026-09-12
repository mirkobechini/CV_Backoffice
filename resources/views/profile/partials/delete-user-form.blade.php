@php
    $capoGroups = auth()->user()->groups()->wherePivot('role', 'capo')->get();
@endphp

<div class="head">
    <h3>{{ __('Elimina account') }}</h3>
</div>
<div class="body">
    <p class="hint" style="margin-bottom:14px;">
        {{ __('Una volta eliminato il tuo account, tutte le risorse e i dati associati verranno eliminati definitivamente. Prima di eliminare il tuo account, scarica eventuali dati che desideri conservare.') }}
    </p>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="{{ route('profile.export') }}" class="btn outline">
            <i class="fa-solid fa-download"></i> {{ __('Esporta i miei dati (GDPR)') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal" data-bs-target="#delete-account">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina account') }}
        </button>
    </div>

    <div class="modal fade" id="delete-account" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
        role="dialog" aria-labelledby="delete-account-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <form method="post" action="{{ route('profile.destroy') }}" data-single-submit="true">
                    @csrf
                    @method('delete')

                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="delete-account-label">{{ __('Eliminare il tuo account?') }}</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="{{ __('Chiudi') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p style="margin-bottom:14px;">
                            {{ __('Una volta eliminato il tuo account, tutte le risorse e i dati associati verranno eliminati definitivamente. Inserisci la password per confermare.') }}
                        </p>

                        @if ($capoGroups->isNotEmpty())
                            <div class="alert warning">
                                <div style="width:100%;">
                                    <strong>{{ __('Sei capo dei seguenti gruppi.') }}</strong>
                                    <p style="margin:4px 0 12px;">
                                        {{ __("Prima di eliminare l'account, trasferisci il ruolo capo a un altro membro.") }}
                                    </p>
                                    @foreach ($capoGroups as $group)
                                        <div class="field" style="margin-bottom:10px;">
                                            <label>{{ $group->name }}</label>
                                            <select name="successor_{{ $group->id }}" class="select">
                                                <option value="" disabled selected>{{ __('Seleziona un membro...') }}</option>
                                                @foreach ($group->users()->where('users.id', '!=', auth()->id())->get() as $member)
                                                    <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="field" style="margin-bottom:0;">
                            <label for="password">{{ __('Password') }}</label>
                            <input id="password" name="password" type="password"
                                class="input @error('password', 'userDeletion') is-invalid @enderror"
                                placeholder="{{ __('Password') }}">
                            @error('password', 'userDeletion')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Annulla') }}</button>
                        <button type="submit" class="btn btn-danger" data-loading-text="{{ __('Eliminazione...') }}">
                            {{ __('Elimina account') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
