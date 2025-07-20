<?php

namespace Controller;

use App\Controller\EventController;
use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\EventPayloadValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventControllerTest extends KernelTestCase
{
    public function testGetEvents()
    {
        self::bootKernel();
        $container = static::getContainer();

        $eventRepository = $this->createMock(EventRepository::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);
        $eventRepository->method('createQueryBuilder')->with('event')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('execute')->willReturn([
            new Event()->setTitle('Test1')->setStart(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T12:00:00')),
            new Event()->setTitle('Test2')->setStart(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T12:00:00')),
            new Event()->setTitle('Test3')->setStart(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T12:00:00')),
        ]);

        $controller = new EventController();
        $controller->setContainer($container);
        $response = $controller->list(
            new Request(),
            $eventRepository,
            null
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $expectedBody = <<<JSON
        [{"id":null,"title":"Test1","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00","user_id":null},{"id":null,"title":"Test2","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00","user_id":null},{"id":null,"title":"Test3","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00","user_id":null}]
        JSON;
        $this->assertEquals($expectedBody, $response->getContent());
    }

    public static function invalidPostInputs(): iterable
    {
        yield 'noBody' => [
            '',
            '{"success":false,"message":"Deserialization failed"}'
        ];
        yield 'invalidDate' => [
            <<<JSON
            [{"title": "title", "start": "invalid", "end": "2020-01-01T0:00:00"}]
            JSON,
            '{"success":false,"message":"Deserialization failed"}'
        ];
        yield 'noTitle' => [
            <<<JSON
            [{"title": "", "start": "2020-01-01T00:00:00", "end": "2020-01-01T12:00:00"}]
            JSON,
            '{"success":false,"message":"Validation error: Object(App\\\\Entity\\\\Event).title:\n    This value should not be blank. (code c1051bb4-d103-4f74-8988-acbcafc7fdc3)\n in event\n{\u0022title\u0022:\u0022\u0022,\u0022start\u0022:\u00222020-01-01 00:00:00\u0022,\u0022end\u0022:\u00222020-01-01 12:00:00\u0022}"}'
        ];
        yield 'noStart' => [
            <<<JSON
            [{"title": "title", "end": "2020-01-01T12:00:00"}]
            JSON,
            '{"success":false,"message":"Validation error: Object(App\\\\Entity\\\\Event).start:\n    This value should not be null. (code ad32d13f-c3d4-423b-909a-857b961eb720)\n in event\n{\u0022title\u0022:\u0022title\u0022,\u0022start\u0022:null,\u0022end\u0022:\u00222020-01-01 12:00:00\u0022}"}'
        ];
        yield 'noEnd' => [
            <<<JSON
            [{"title": "title", "start": "2020-01-01T12:00:00"}]
            JSON,
            '{"success":false,"message":"Validation error: Object(App\\\\Entity\\\\Event).end:\n    This value should not be null. (code ad32d13f-c3d4-423b-909a-857b961eb720)\n in event\n{\u0022title\u0022:\u0022title\u0022,\u0022start\u0022:\u00222020-01-01 12:00:00\u0022,\u0022end\u0022:null}"}'
        ];
        yield 'startAfterEnd' => [
            <<<JSON
            [{"title": "title", "start": "2020-01-01T12:00:00", "end": "2020-01-01T0:00:00"}]
            JSON,
            '{"success":false,"message":"Validation error: Object(App\\\\Entity\\\\Event).end:\n    This value should be greater than [event.start]. (code 778b7ae0-84d3-481a-9dec-35fdb64b1d78)\n in event\n{\u0022title\u0022:\u0022title\u0022,\u0022start\u0022:\u00222020-01-01 12:00:00\u0022,\u0022end\u0022:\u00222020-01-01 00:00:00\u0022}"}'
        ];
        yield 'overlappingPostedEvents' => [
            <<<JSON
              [{"title":"Test1","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"},{"title":"Test2","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"}]
            JSON,
            '{"success":false,"message":"Validation error: Event {\u0022id\u0022:null,\u0022title\u0022:\u0022Test2\u0022,\u0022start\u0022:\u00222020-01-01T00:00:00\u0022,\u0022end\u0022:\u00222020-01-01T12:00:00\u0022,\u0022user_id\u0022:null} overlaps with {\u0022id\u0022:null,\u0022title\u0022:\u0022Test1\u0022,\u0022start\u0022:\u00222020-01-01T00:00:00\u0022,\u0022end\u0022:\u00222020-01-01T12:00:00\u0022,\u0022user_id\u0022:null}"}'
        ];
        yield 'dbOverlappingPostedEvents' => [
            <<<JSON
              [{"title":"Test1","start":"2025-01-01T12:00:00","end":"2025-01-01T12:30:00"}]
            JSON,
            '{"success":false,"message":"Validation error: Event {\u0022id\u0022:null,\u0022title\u0022:\u0022Test1\u0022,\u0022start\u0022:\u00222025-01-01T12:00:00\u0022,\u0022end\u0022:\u00222025-01-01T12:30:00\u0022,\u0022user_id\u0022:null} overlaps with {\u0022id\u0022:1,\u0022title\u0022:\u0022Test Event 0\u0022,\u0022start\u0022:\u00222025-01-01T12:00:00\u0022,\u0022end\u0022:\u00222025-01-01T13:00:00\u0022,\u0022user_id\u0022:0}"}'
        ];
    }

    #[DataProvider('invalidPostInputs')]
    public function testPostEventsFailures(string $body, string $message)
    {
        self::bootKernel();
        $container = static::getContainer();

        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn($body);

        $controller = new EventController();
        $controller->setContainer($container);
        $response = $controller->create(
            $request,
            $container->get(SerializerInterface::class),
            $container->get(ValidatorInterface::class),
            $container->get(EntityManagerInterface::class),
            $container->get(EventRepository::class),
            $container->get(EventPayloadValidator::class),
            null
        );
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertEquals($message, $response->getContent());
    }

    public function testValidPostEvents()
    {
        self::bootKernel();
        $container = static::getContainer();

        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn("{body}");

        $serializer = $container->get(SerializerInterface::class);
        $validator = $container->get(ValidatorInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);
        $eventRepository = $container->get(EventRepository::class);


        $mockPayloadValidator = $this->createMock(EventPayloadValidator::class);
        $mockPayloadValidator->expects(static::once())->method('deserializeEvents')->with("{body}", $serializer)->willReturn(['__MOCK__']);
        $mockPayloadValidator->expects(static::once())->method('checkForValidationErrors')->with(['__MOCK__'], $validator);
        $mockPayloadValidator->expects(static::once())->method('checkLocalOverlaps')->with(['__MOCK__']);
        $mockPayloadValidator->expects(static::once())->method('checkDatabaseOverlaps')->with(['__MOCK__'], $eventRepository);
        $mockPayloadValidator->expects(static::once())->method('persistEvents')->with(['__MOCK__'], $entityManager);

        $controller = new EventController();
        $controller->setContainer($container);
        $response = $controller->create(
            $request,
            $serializer,
            $validator,
            $entityManager,
            $eventRepository,
            $mockPayloadValidator,
            null
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertEquals('{"success":true,"inserted":["__MOCK__"]}', $response->getContent());
    }

    public function testValidPatchEvent()
    {
        self::bootKernel();
        $container = static::getContainer();

        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn("{body}");

        $event = $this->createPartialMock(Event::class, ['getId']);
        $event->method('getId')->willReturn(999);
        $serializer = $container->get(SerializerInterface::class);
        $validator = $container->get(ValidatorInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);
        $eventRepository = $container->get(EventRepository::class);

        $parsedEvent = $this->createMock(Event::class);
        $parsedEvent->method('getTitle')->willReturn('__mock_title__');
        $parsedEvent->method('getStart')->willReturn(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'));
        $parsedEvent->method('getEnd')->willReturn(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T01:00:00'));

        $mockPayloadValidator = $this->createMock(EventPayloadValidator::class);
        $mockPayloadValidator->expects(static::once())->method('deserializeEvent')->with("{body}", $serializer)->willReturn($parsedEvent);
        $mockPayloadValidator->expects(static::once())->method('checkForValidationErrors')->with([$event], $validator);
        $mockPayloadValidator->expects(static::once())->method('checkDatabaseOverlaps')->with([$event], $eventRepository, 999, null);
        $mockPayloadValidator->expects(static::once())->method('persistEvents')->with([$event], $entityManager);

        $controller = new EventController();
        $controller->setContainer($container);
        $response = $controller->update(
            $request,
            $event,
            $serializer,
            $validator,
            $entityManager,
            $eventRepository,
            $mockPayloadValidator,
            null
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertEquals('{"success":true,"updated":{"id":999,"title":"__mock_title__","start":"2020-01-01T00:00:00","end":"2020-01-01T01:00:00","user_id":null}}', $response->getContent());
    }

    public function testInvalidPatchEvent()
    {
        self::bootKernel();
        $container = static::getContainer();

        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn("{body}");

        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(null);
        $serializer = $container->get(SerializerInterface::class);
        $validator = $container->get(ValidatorInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);
        $eventRepository = $container->get(EventRepository::class);
        $mockPayloadValidator = $this->createMock(EventPayloadValidator::class);

        $controller = new EventController();
        $controller->setContainer($container);
        $response = $controller->update(
            $request,
            $event,
            $serializer,
            $validator,
            $entityManager,
            $eventRepository,
            $mockPayloadValidator,
            null
        );
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertEquals('{"success":false,"message":"Event not found"}', $response->getContent());
    }

    public function testValidDeleteEvent()
    {
        self::bootKernel();
        $container = static::getContainer();

        $event = $this->createPartialMock(Event::class, ['getId']);
        $event->method('getId')->willReturn(999);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(static::once())->method('remove')->with($event);
        $entityManager->expects(static::once())->method('flush');

        $controller = new EventController();
        $controller->setContainer($container);
        $response = $controller->delete(
            $event,
            $entityManager,
            null
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertEquals('{"success":true,"message":"Event deleted"}', $response->getContent());
    }


    public function testinValidDeleteEvent()
    {
        self::bootKernel();
        $container = static::getContainer();

        $event = $this->createPartialMock(Event::class, ['getId']);
        $event->method('getId')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(static::never())->method('remove')->with($event);
        $entityManager->expects(static::never())->method('flush');

        $controller = new EventController();
        $controller->setContainer($container);
        $response = $controller->delete(
            $event,
            $entityManager,
            null
        );
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertEquals('{"success":false,"message":"Event not found"}', $response->getContent());
    }
}
