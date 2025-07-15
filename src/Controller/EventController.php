<?php

namespace App\Controller;

use App\Entity\Event;
use App\Exception\OverlappingEventException;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @psalm-suppress UnusedClass */
final class EventController extends AbstractController
{

    #[Route('/events', methods: ['GET', 'HEAD'])]
    public function list(EventRepository $eventManager): Response
    {
        return $this->json($eventManager->findAll());
    }

    #[Route('/events', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): Response
    {
        try {
            /** @var Event[] $events */
            $events = $serializer->deserialize($request->getContent(), Event::class.'[]', 'json');
            $this->checkForOverlaps($events);
        } catch (JsonException|NotNormalizableValueException|UnexpectedValueException|ExceptionInterface|OverlappingEventException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 400);
        }

        $entities = [];
        $eventManager = $entityManager->getRepository(Event::class);

        // Check for errors within the POSTed dataset before looking for overlaps in the database
        // otherwise we may hit an error after performing many expensive queries
        foreach ($events as $index => $event) {
            $errors = $validator->validate($event);
            if (count($errors) > 0) {
                $errorsString = (string)$errors;

                return $this->json(['success' => false, 'message' => "Error in event[$index]: $errorsString"], 400);
            }
            $entities[] = $event;
            $entityManager->persist($event);
        }

        // Split the dataset into groups of 100 to keep the query from  becoming to complex
        foreach (array_chunk($events, 100) as $groupIndex => $eventChunk) {
            $overlappingEventsQuery = $eventManager->createQueryBuilder('event');
            foreach ($eventChunk as $index => $event) {
                $overlappingEventsQuery->orWhere("(:start{$index} BETWEEN event.start AND event.end)");
                $overlappingEventsQuery->orWhere("(:end{$index} BETWEEN event.start AND event.end)");
                $overlappingEventsQuery->setParameter("start{$index}", $event->getStart());
                $overlappingEventsQuery->setParameter("end{$index}", $event->getEnd());
            }
            if ($overlappingEventsQuery->getQuery()->execute()) {
                $groupStart = ($groupIndex * 100);
                $groupEnd = min(count($events) - 1, ($groupIndex + 1) * 100);
                return $this->json(['success' => false, 'message' => "Overlapping events detected in group {$groupStart} - {$groupEnd}"], 400);
            }
        }

        $entityManager->flush();

        return $this->json(['success' => true, 'inserted' => $entities]);
    }

    /**
     * Checks for overlapping event times in the POSTed dataset
     * Checks for overlaps in the persisted events are not performed
     * @param Event[] $events
     * @throws OverlappingEventException
     */
    protected function checkForOverlaps(array $events): void
    {
        for ($i = 0; $i < count($events); $i++) {
            for ($j = $i + 1; $j < count($events); $j++) {
                if (
                    ($events[$i]->getStart() >= $events[$j]->getStart() && $events[$i]->getStart() <= $events[$j]->getEnd()) ||
                    ($events[$i]->getEnd() >= $events[$j]->getStart() && $events[$i]->getEnd() <= $events[$j]->getEnd())
                ) {
                    throw new OverlappingEventException("Event {$i} overlaps with event {$j}");
                }
            }
        }
    }

    #[Route('/events', methods: ['PATCH'])]
    public function update(): array
    {
        return [];
    }
}
