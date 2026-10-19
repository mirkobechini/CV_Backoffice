<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Notification;
use App\Models\User;

/**
 * Servizio centralizzato per la creazione di notifiche.
 *
 * Gestisce sia le notifiche in-app (tabella notifications) sia, in futuro,
 * le email automatiche. La logica per ruolo è preparata ma non attiva:
 * finché non esiste il sistema di ruoli/gruppi, le notifiche vengono
 * inviate a tutti gli utenti admin.
 */
class NotificationService
{
    /**
     * Crea una notifica in-app per un utente.
     *
     * $marker identifica univocamente la risorsa per utente/tipo, usato da
     * GenerateNotifications::alreadyNotified() per evitare duplicati —
     * mai mostrato all'utente, solo in colonna propria (non più accodato
     * al testo del messaggio, vedi audit 2026-10-19).
     */
    public function notifyUser(User $user, string $type, string $title, ?string $message = null, ?string $url = null, ?string $marker = null): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'marker' => $marker,
            'url' => $url,
            'is_read' => false,
        ]);
    }

    /**
     * Crea una notifica in-app per tutti gli utenti che possono gestire i dati
     * (capo/sottocapo in almeno un gruppo).
     */
    public function notifyAdmins(string $type, string $title, ?string $message = null, ?string $url = null): void
    {
        $users = User::whereHas('groups', function ($q) {
            $q->whereIn('group_user.role', [Group::ROLE_CAPO, Group::ROLE_SOTTOCAPO]);
        })->get();

        foreach ($users as $user) {
            $this->notifyUser($user, $type, $title, $message, $url);
        }
    }

    /**
     * Crea una notifica in-app per tutti gli utenti con un determinato ruolo
     * in almeno un gruppo.
     */
    public function notifyRole(string $role, string $type, string $title, ?string $message = null, ?string $url = null): void
    {
        $users = User::whereHas('groups', function ($q) use ($role) {
            $q->where('group_user.role', $role);
        })->get();

        foreach ($users as $user) {
            $this->notifyUser($user, $type, $title, $message, $url);
        }
    }

    /**
     * Crea una notifica in-app per tutti gli utenti con uno dei ruoli indicati
     * in almeno un gruppo.
     *
     * @param  array<int, string>  $roles
     */
    public function notifyByRoles(array $roles, string $type, string $title, ?string $message = null, ?string $url = null): void
    {
        $users = User::whereHas('groups', function ($q) use ($roles) {
            $q->whereIn('group_user.role', $roles);
        })->get();

        foreach ($users as $user) {
            $this->notifyUser($user, $type, $title, $message, $url);
        }
    }

    /**
     * Crea una notifica per un utente specifico o per tutti gli admin.
     *
     * @param  ?User  $user  Se null, notifica tutti gli admin.
     */
    public function notify(?User $user, string $type, string $title, ?string $message = null, ?string $url = null): void
    {
        if ($user) {
            $this->notifyUser($user, $type, $title, $message, $url);
        } else {
            $this->notifyAdmins($type, $title, $message, $url);
        }
    }
}
