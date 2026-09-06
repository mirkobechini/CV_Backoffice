<?php

namespace App\Services;

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
     */
    public function notifyUser(User $user, string $type, string $title, ?string $message = null, ?string $url = null): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'is_read' => false,
        ]);
    }

    /**
     * Crea una notifica in-app per tutti gli utenti che possono riceverla.
     *
     * Attualmente notifica tutti gli admin. Quando esisterà il sistema di
     * ruoli/gruppi, qui si filtrerà per ruolo/permessi.
     */
    public function notifyAdmins(string $type, string $title, ?string $message = null, ?string $url = null): void
    {
        $this->notifyRole('admin', $type, $title, $message, $url);
    }

    /**
     * Crea una notifica in-app per tutti gli utenti con un determinato ruolo.
     */
    public function notifyRole(string $role, string $type, string $title, ?string $message = null, ?string $url = null): void
    {
        $users = User::where('role', $role)->get();

        foreach ($users as $user) {
            $this->notifyUser($user, $type, $title, $message, $url);
        }
    }

    /**
     * Crea una notifica in-app per tutti gli utenti con uno dei ruoli indicati.
     *
     * @param  array<int, string>  $roles
     */
    public function notifyByRoles(array $roles, string $type, string $title, ?string $message = null, ?string $url = null): void
    {
        $users = User::whereIn('role', $roles)->get();

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
