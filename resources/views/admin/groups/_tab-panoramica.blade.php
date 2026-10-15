<div class="table-card">
    <div class="toolbar">
        <div class="toolbar-left">
            <h2>{{ __('Membri') }}</h2>
        </div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Nome') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Ruolo') }}</th>
                    @if ($isCapo)
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($group->users as $member)
                    <tr>
                        <td>
                            <div class="user-cell">
                                <span class="avatar">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                                <span class="name">{{ $member->name }}</span>
                            </div>
                        </td>
                        <td><span class="email">{{ $member->email }}</span></td>
                        <td>
                            @if ($isCapo && $member->pivot->role !== 'capo')
                                <form method="POST" action="{{ route('admin.groups.role', [$group, $member]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" class="role-select" onchange="this.form.submit()">
                                        <option value="sottocapo" @selected($member->pivot->role === 'sottocapo')>
                                            {{ __('Sottocapo') }}</option>
                                        <option value="member" @selected($member->pivot->role === 'member')>
                                            {{ __('Membro') }}</option>
                                    </select>
                                </form>
                            @else
                                @php
                                    $roleBadge = match ($member->pivot->role) {
                                        'capo' => 'b-red',
                                        'sottocapo' => 'b-amber',
                                        default => 'b-gray',
                                    };
                                    $roleLabel = match ($member->pivot->role) {
                                        'capo' => __('Capo'),
                                        'sottocapo' => __('Sottocapo'),
                                        default => __('Membro'),
                                    };
                                @endphp
                                <span class="badge {{ $roleBadge }}">{{ $roleLabel }}</span>
                            @endif
                        </td>
                        @if ($isCapo)
                            <td>
                                @if ($member->pivot->role !== 'capo')
                                    <form method="POST"
                                        action="{{ route('admin.groups.remove-member', [$group, $member]) }}"
                                        onsubmit="return confirm('{{ __('Rimuovere questo membro?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn danger sm">{{ __('Rimuovi') }}</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
