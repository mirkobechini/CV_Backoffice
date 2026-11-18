<?php

namespace App\Exceptions;

/**
 * Fallimento della lookup al catalogo openvehicle-data (chiamata HTTP
 * fallita, timeout, risposta non valida): il chiamante deve sempre
 * ricadere sui dati dello scan AI, mai lasciarla risalire come errore 500
 * né bloccare l'inserimento del veicolo.
 */
class OpenVehicleCatalogException extends \RuntimeException {}
