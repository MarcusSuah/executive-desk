<?php

namespace App\Services\Reminders;

use RuntimeException;

/**
 * Thrown so the queue retries a delivery after its backoff delay.
 */
class TransientDeliveryException extends RuntimeException
{
    //
}
