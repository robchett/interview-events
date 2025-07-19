<?php

namespace App\Controller;

use App\Entity\Event;
use App\Exception\EventDeserializationException;
use App\Exception\EventValidationException;
use App\Form\GetEventsRequest;
use App\Form\Type\GetEventsType;
use App\Repository\EventRepository;
use App\Service\EventPayloadValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @psalm-suppress UnusedClass */
final class EventController extends AbstractController
{

    #[Route('/events', methods: ['GET', 'HEAD'])]
    public function list(Request $request, EventRepository $eventManager): Response
    {
        $eventsRequest = new GetEventsRequest();
        $form = $this->createForm(GetEventsType::class, $eventsRequest);
        $form->submit($request->query->all(), false);

        if ($form->isSubmitted() && $form->isValid()) {
            $query = $eventsRequest->getQuery($eventManager);
            return $this->json($query->execute());
        } else {
            return $this->json(["success" => false, "message" => $form->getErrors(), "submitted" => $form->isSubmitted(), 'valid' => $form->isValid()], 400);
        }
    }

    #[Route('/events', methods: ['POST'])]
    public function create(
        Request                $request,
        SerializerInterface    $serializer,
        ValidatorInterface     $validator,
        EntityManagerInterface $entityManager,
        EventRepository        $eventRepository,
        EventPayloadValidator  $payloadValidator,
    ): Response
    {
        try {
            $eventBody = $request->getContent();
            $events = $payloadValidator->deserializeEvents($eventBody, $serializer);
            $payloadValidator->checkForValidationErrors($events, $validator);
            $payloadValidator->checkLocalOverlaps($events);
            $payloadValidator->checkDatabaseOverlaps($events, $eventRepository);
            $payloadValidator->persistEvents($events, $entityManager);
        } catch (EventValidationException|EventDeserializationException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 400);
        }

        return $this->json(['success' => true, 'inserted' => $events]);
    }

    #[Route('/events/{id:event}', methods: ['PATCH'])]
    public function update(
        Request                $request,
        Event                  $event,
        SerializerInterface    $serializer,
        ValidatorInterface     $validator,
        EntityManagerInterface $entityManager,
        EventRepository        $eventRepository,
        EventPayloadValidator  $payloadValidator,
    ): Response
    {
        try {
            $id = $event->getId();
            if ($id === null) {
                return $this->json(['success' => false, 'message' => 'Event not found'], 400);
            }
            $eventBody = $request->getContent();
            $newEvent = $payloadValidator->deserializeEvent($eventBody, $serializer);
            $payloadValidator->checkForValidationErrors([$newEvent], $validator);
            /** @psalm-suppress PossiblyNullArgument */
            $event
                ->setTitle($newEvent->getTitle())
                ->setStart($newEvent->getStart())
                ->setEnd($newEvent->getEnd());
            $payloadValidator->checkDatabaseOverlaps([$event], $eventRepository, $id);
            $payloadValidator->persistEvents([$event], $entityManager);
        } catch (EventValidationException|EventDeserializationException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 400);
        }

        return $this->json(['success' => true, 'updated' => $event]);
    }

    #[Route('/events/{id:event}', methods: ['DELETE'])]
    public function delete(
        Event                  $event,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $id = $event->getId();
        if ($id === null) {
            return $this->json(['success' => false, 'message' => 'Event not found'], 400);
        }
        $entityManager->remove($event);
        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Event deleted']);
    }
}
