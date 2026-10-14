<?php

namespace App\Exceptions;

/**
 * Fallimento della classificazione AI della parte del veicolo per i guasti
 * esistenti (chiamata al provider LLM fallita, risposta senza JSON valido,
 * ecc.): il chiamante deve gestirla mostrando un messaggio all'utente, mai
 * lasciarla risalire come errore 500.
 */
class IssuePartClassificationException extends \RuntimeException {}
