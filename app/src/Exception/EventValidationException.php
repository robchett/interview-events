<?php

namespace App\Exception;

use App\Dto\EventOverlapError;
use App\Dto\EventValidationError;
use Throwable;

final class EventValidationException extends \Exception
{

    /**
     * @param (EventValidationError|EventOverlapError)[] $events
     */
    public function __construct(protected array $events, int $code = 0, ?Throwable $previous = null)
    {
        $message = implode("\n", array_map(fn($eventValidationError) => $eventValidationError->toString(), $this->events));
        parent::__construct($message, $code, $previous);
    }
}
