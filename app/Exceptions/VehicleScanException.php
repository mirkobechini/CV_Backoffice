<?php

namespace App\Exceptions;

/**
 * Fallimento della scansione del libretto (chiamata al provider LLM fallita,
 * risposta senza JSON valido, ecc.): il chiamante deve gestirla mostrando un
 * messaggio all'utente, mai lasciarla risalire come errore 500.
 */
class VehicleScanException extends \RuntimeException
{
}
