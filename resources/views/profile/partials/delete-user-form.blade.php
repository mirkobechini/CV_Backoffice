<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <!-- Modal trigger button -->
    <div class="d-flex gap-2">
        <a href="{{ route('profile.export') }}" class="btn btn-outline-primary">
            {{ __('Esporta i miei dati (GDPR)') }}
        </a>
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#delete-account">
            {{ __('Delete Account') }}
        </button>
    </div>

    <!-- Modal Body -->
    <!-- if you want to close by clicking outside the modal, delete the last endpoint:data-bs-backdrop and data-bs-keyboard -->
    <div class="modal fade" id="delete-account" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
        role="dialog" aria-labelledby="delete-account" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="delete-account">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h2 class="text-lg font-medium text-gray-900">
                        {{ __('Are you sure you want to delete your account?') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>

                    @php
                        $capoGroups = auth()->user()->groups()->wherePivot('role', 'capo')->get();
                    @endphp

                    @if ($capoGroups->isNotEmpty())
                        <div class="alert alert-warning mt-3">
                            <strong>{{ __('Sei capo dei seguenti gruppi.') }}</strong>
                            <p class="mb-2">
                                {{ __('Prima di eliminare l\'account, trasferisci il ruolo capo a un altro membro.') }}
                            </p>
                            @foreach ($capoGroups as $group)
                                <div class="mb-2">
                                    <label class="form-label">{{ $group->name }}</label>
                                    <select name="successor_{{ $group->id }}" class="form-select">
                                        <option value="">{{ __('Seleziona un membro...') }}</option>
                                        @foreach ($group->users()->where('users.id', '!=', auth()->id())->get() as $member)
<option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
@endforeach
                                    </select>
                                </div>
@endforeach
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

                    <form method="post" action="{{ route('profile.destroy') }}" class="p-6"
                        data-single-submit="true">
                        @csrf
                        @method('delete')


                        <div class="input-group">

                            <input id="password" name="password" type="password" class="form-control"
                                placeholder="{{ __('Password') }}" />

                            @error('password')
<span class="invalid-feedback mt-2" role="alert">
                                    <strong>{{ $errors->userDeletion->get('password') }}</strong>
                                </span>
@enderror



                <button type="submit" class="btn btn-danger" data-loading-text="Deleting...">
                    {{ __('Delete Account') }}
                </button>
                <!--  -->
            </div>
        </form>

    </div>
</div>
</div>
</div>

</section>
