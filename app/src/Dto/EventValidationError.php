<?php

namespace App\Dto;

use App\Entity\Event;
use Symfony\Component\Validator\ConstraintViolationListInterface;

readonly final class EventValidationError
{

    public function __construct(
        public Event $event,
        public ConstraintViolationListInterface $error,
    )
    {

    }

    public function toString(): string
    {
        $eventJson = json_encode([
            'title' => $this->event->getTitle(),
            'start' => $this->event->getStart()?->format('Y-m-d H:i:s'),
            'end' => $this->event->getEnd()?->format('Y-m-d H:i:s'),
        ]);
        $messageString = (string) $this->error;
        return "Validation error: $messageString in event\n$eventJson";

    }
}
