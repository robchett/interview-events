<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
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
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @psalm-suppress UnusedClass */
final class EventController extends AbstractController
{

    #[Route('/events', methods: ['GET', 'HEAD'])]
    public function list(
        Request $request,
        EventRepository $eventManager,
        #[CurrentUser] ?User $user,
    ): Response
    {
        $eventsRequest = new GetEventsRequest();
        $form = $this->createForm(GetEventsType::class, $eventsRequest, ['csrf_protection' => false]);
        $form->submit($request->query->all(), false);

        if ($form->isSubmitted() && $form->isValid()) {
            $query = $eventsRequest->getQuery($eventManager, $user);
            return $this->json($query->execute());
        } else {
            return $this->json(["success" => false, "message" => $form->getErrors(), "submitted" => $form->isSubmitted(), 'valid' => $form->isValid()], Response::HTTP_BAD_REQUEST);
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
        #[CurrentUser] ?User   $user,
    ): Response
    {
        try {
            $eventBody = $request->getContent();
            $events = $payloadValidator->deserializeEvents($eventBody, $serializer);
            $payloadValidator->setUser($events, $user);
            $payloadValidator->checkForValidationErrors($events, $validator);
            $payloadValidator->checkLocalOverlaps($events);
            $payloadValidator->checkDatabaseOverlaps($events, $eventRepository, 0, $user);
            $payloadValidator->persistEvents($events, $entityManager);
        } catch (EventValidationException|EventDeserializationException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
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
        #[CurrentUser] ?User   $user,
    ): Response
    {
        try {
            $id = $event->getId();
            if ($id === null) {
                return $this->json(['success' => false, 'message' => 'Event not found'], Response::HTTP_BAD_REQUEST);
            }
            // Disallow updating events not owned by this user.
            $userId = $event->getUserId();
            if ($userId !== null && $userId !== 0 && $userId != $user?->getId()) {
                return $this->json(['success' => false, 'message' => 'Event not found'], Response::HTTP_BAD_REQUEST);
            }
            $eventBody = $request->getContent();
            $newEvent = $payloadValidator->deserializeEvent($eventBody, $serializer);
            /** @psalm-suppress PossiblyNullArgument */
            $event
                ->setTitle($newEvent->getTitle())
                ->setStart($newEvent->getStart())
                ->setEnd($newEvent->getEnd());
            $payloadValidator->checkForValidationErrors([$event], $validator);
            $payloadValidator->checkDatabaseOverlaps([$event], $eventRepository, $id, $user);
            $payloadValidator->persistEvents([$event], $entityManager);
        } catch (EventValidationException|EventDeserializationException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['success' => true, 'updated' => $event]);
    }

    #[Route('/events/{id:event}', methods: ['DELETE'])]
    public function delete(
        Event                  $event,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?User   $user,
    ): Response
    {
        $id = $event->getId();
        if ($id === null) {
            return $this->json(['success' => false, 'message' => 'Event not found'], Response::HTTP_BAD_REQUEST);
        }
        // Disallow deleting events not owned by this user.
        if ( !$event->isOwnedBy($user)) {
            return $this->json(['success' => false, 'message' => 'Event not found'], Response::HTTP_BAD_REQUEST);
        }
        $entityManager->remove($event);
        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Event deleted']);
    }
}
