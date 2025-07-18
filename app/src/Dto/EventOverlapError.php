<?php

namespace App\Dto;

use App\Entity\Event;

readonly final class EventOverlapError
{

    public function __construct(
        public Event $event,
        public Event $overlappingEvent,
    )
    {

    }

    public function toString(): string
    {
        $eventJson = json_encode($this->event);
        $otherJson = json_encode($this->overlappingEvent);
        return "Validation error: Event {$eventJson} overlaps with {$otherJson}";
    }

}
