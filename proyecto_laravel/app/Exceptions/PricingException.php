<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de cálculo de precios de una arquitectura. El mensaje es apto para
 * mostrarse al usuario e identifica el elemento y la causa concreta.
 */
class PricingException extends RuntimeException {}
