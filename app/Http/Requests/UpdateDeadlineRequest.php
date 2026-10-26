<?php

namespace App\Http\Requests;

/**
 * Identica a StoreDeadlineRequest, nessuna regola cambia tra creazione e
 * modifica (audit 2026-10-22, Q6): prima duplicata per intero invece di
 * estendere.
 */
class UpdateDeadlineRequest extends StoreDeadlineRequest {}
