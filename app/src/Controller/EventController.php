<?php

namespace App\Controller;

use App\Enums\EventFilter;
use App\Exception\EventDeserializationException;
use App\Exception\EventValidationException;
use App\Exception\InvalidFilterException;
use App\Repository\EventRepository;
use App\Service\EventPayloadValidator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
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
        try {
            $query = $this->getListQuery($eventManager, $request);
            return $this->json($query->execute());
        } catch (InvalidFilterException $exception) {
            return $this->json(["success" => false, "message" => $exception->getMessage()], 400);
        }
    }

    /**
     * @throws InvalidFilterException
     */
    public function getListQuery(EventRepository $eventManager, Request $request): Query
    {
        $query = $eventManager->createQueryBuilder('event');
        $this->addDateFilter('start >', EventFilter::startFrom, $request->query->getString(EventFilter::startFrom->value), $query);
        $this->addDateFilter('start <', EventFilter::startTo, $request->query->getString(EventFilter::startTo->value), $query);
        $this->addDateFilter('end >', EventFilter::endFrom, $request->query->getString(EventFilter::endFrom->value), $query);
        $this->addDateFilter('end <', EventFilter::endTo, $request->query->getString(EventFilter::endTo->value), $query);
        $this->addTextFilter('title', EventFilter::title, $request->query->getString(EventFilter::title->value), $query);
        return $query->getQuery();
    }

    /**
     * @param 'title' $column
     * @throws InvalidFilterException
     */
    protected function addTextFilter(string $column, EventFilter $filter, string $value, QueryBuilder $query): void
    {
        if (!$value) {
            return;
        }
        $query->andWhere("event.{$column} LIKE :{$filter->value}");
        $query->setParameter($filter->value, "%$value%");
    }

    /**
     * @param 'start >'|'start <'|'end >'|'end <' $column
     * @throws InvalidFilterException
     */
    protected function addDateFilter(string $column, EventFilter $filter, string $value, QueryBuilder $query): void
    {
        if (! $value) {
            return;
        }
        $parsedTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
        if ($parsedTime === false) {
            throw new InvalidFilterException("{$filter->value} is not a valid date in modified ISO8601 format");
        }
        $query->andWhere("event.{$column} :{$filter->value}");
        $query->setParameter($filter->value, $parsedTime->format('Y-m-d H:i:s'));
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

    #[Route('/events', methods: ['PATCH'])]
    public function update(): array
    {
        return [];
    }
}
