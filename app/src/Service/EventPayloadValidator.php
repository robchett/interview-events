<?php

namespace App\Service;

use App\Dto\EventOverlapError;
use App\Dto\EventValidationError;
use App\Entity\Event;
use App\Exception\EventDeserializationException;
use App\Exception\EventValidationException;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EventPayloadValidator
{

    /**
     * @throws EventDeserializationException
     * @return Event[]
     * @psalm-suppress MixedReturnStatement
     */
    public function deserializeEvents(string $eventBody, SerializerInterface $serializer): array
    {
        try {
            return $serializer->deserialize($eventBody, Event::class . '[]', 'json');
        } catch (NotNormalizableValueException|UnexpectedValueException|ExceptionInterface $exception) {
            throw new EventDeserializationException("Deserialization failed", previous: $exception);
        }
    }

    /**
     * Checks for Entity errors on the Events.
     * @param Event[] $events
     * @throws EventValidationException
     */
    public function checkForValidationErrors(array $events, ValidatorInterface $validator): void
    {
        $invalidEvents = [];
        foreach ($events as $event) {
            $errors = $validator->validate($event);
            if (count($errors) > 0) {
                $invalidEvents[] = new EventValidationError($event, $errors);
            }
        }
        if ($invalidEvents) {
            throw new EventValidationException($invalidEvents);
        }
    }

    /**
     * Checks for overlapping event times in the POSTed dataset
     * Checks for overlaps in the persisted events are not performed
     * @param Event[] $events
     * @throws EventValidationException
     */
    public function checkLocalOverlaps(array $events): void
    {
        $validEvents = [];
        $overlappingEvents = [];
        foreach ($events as $checkEvent) {
            foreach ($validEvents as $validEvent) {
                if (
                    ($checkEvent->getStart() >= $validEvent->getStart() && $checkEvent->getStart() < $validEvent->getEnd()) ||
                    ($checkEvent->getEnd() > $validEvent->getStart() && $checkEvent->getEnd() <= $validEvent->getEnd())
                ) {
                    $overlappingEvents[] = new EventOverlapError($checkEvent, $validEvent);
                    continue 2;
                }
            }
            $validEvents[] = $checkEvent;
        }
        if ($overlappingEvents) {
            throw new EventValidationException($overlappingEvents);
        }
    }

    /**
     * Checks for overlapping event times in the POSTed dataset
     * Checks for overlaps in the persisted events are not performed
     * @param Event[] $events
     * @throws EventValidationException
    */
    public function checkDatabaseOverlaps(
        array $events, EventRepository $eventRepository): void
    {
        $overlappingEvents = [];

        foreach ($events as $event) {
            /** @var Event[] $matchedEvents */
            $matchedEvents = $eventRepository->createQueryBuilder('event')
                ->orWhere("(event.start >= :start AND event.start < :end)")
                ->orWhere("(event.end > :start AND event.end <= :end)")
                ->orWhere("(event.start >= :start AND event.end <= :end)")
                ->setParameter("start", $event->getStart())
                ->setParameter("end", $event->getEnd())
                ->getQuery()
                ->execute();
            if (count($matchedEvents)) {
                $overlappingEvents[] = new EventOverlapError($event, $matchedEvents[0]);
            }
        }
        if ($overlappingEvents) {
            throw new EventValidationException($overlappingEvents);
        }
    }

    /**
     * @param Event[] $events
     */
    public function persistEvents(array $events, EntityManagerInterface $entityManager): void
    {
        foreach ($events as $event) {
            $entityManager->persist($event);
        }
        $entityManager->flush();
    }
}
