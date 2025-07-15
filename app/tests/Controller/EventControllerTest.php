<?php

namespace Controller;

use App\Controller\EventController;
use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
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

        $query = $this->createMock(Query::class);
        $query->method('execute')->willReturn([
            new Event()->setTitle('Test1')->setStart(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T12:00:00')),
            new Event()->setTitle('Test2')->setStart(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T12:00:00')),
            new Event()->setTitle('Test3')->setStart(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T12:00:00')),
        ]);

        $controller = $this->createPartialMock(EventController::class, ['getListQuery']);
        $controller->method('getListQuery')->willReturn($query);
        $controller->setContainer($container);
        $response = $controller->list(
            new Request(),
            $container->get(EventRepository::class),
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $expectedBody = <<<JSON
        [{"id":null,"title":"Test1","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"},{"id":null,"title":"Test2","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"},{"id":null,"title":"Test3","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"}]
        JSON;
        $this->assertEquals($expectedBody, $response->getContent());
    }

    public static function invalidPostInputs(): iterable
    {
        yield 'noBody' => [
            '',
            '{"success":false,"message":"Syntax error"}'
        ];
        yield 'noEvents' => [
            '[]',
            '{"success":false,"message":"No events provided"}'
        ];
        yield 'invalidDate' => [
            <<<JSON
            [{"title": "title", "start": "invalid", "end": "2020-01-01T0:00:00"}]
            JSON,
            '{"success":false,"message":"Failed to parse time string (invalid) at position 0 (i): The timezone could not be found in the database"}'
        ];
        yield 'noTitle' => [
            <<<JSON
            [{"title": "", "start": "2020-01-01T00:00:00", "end": "2020-01-01T12:00:00"}]
            JSON,
            '{"success":false,"message":"Error in event[0]: Object(App\\\\Entity\\\\Event).title:\n    This value should not be blank. (code c1051bb4-d103-4f74-8988-acbcafc7fdc3)\n"}'
        ];
        yield 'noStart' => [
            <<<JSON
            [{"title": "title", "end": "2020-01-01T12:00:00"}]
            JSON,
            '{"success":false,"message":"Error in event[0]: Object(App\\\\Entity\\\\Event).start:\n    This value should not be null. (code ad32d13f-c3d4-423b-909a-857b961eb720)\n"}'
        ];
        yield 'noEnd' => [
            <<<JSON
            [{"title": "title", "start": "2020-01-01T12:00:00"}]
            JSON,
            '{"success":false,"message":"Error in event[0]: Object(App\\\\Entity\\\\Event).end:\n    This value should not be null. (code ad32d13f-c3d4-423b-909a-857b961eb720)\n"}'
        ];
        yield 'startAfterEnd' => [
            <<<JSON
            [{"title": "title", "start": "2020-01-01T12:00:00", "end": "2020-01-01T0:00:00"}]
            JSON,
            '{"success":false,"message":"Error in event[0]: Object(App\\\\Entity\\\\Event).end:\n    This value should be greater than [event.start]. (code 778b7ae0-84d3-481a-9dec-35fdb64b1d78)\n"}'
        ];
        yield 'overlappingPostedEvents' => [
            <<<JSON
              [{"title":"Test1","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"},{"title":"Test2","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"}]
            JSON,
            '{"success":false,"message":"Event 0 overlaps with event 1"}'
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
        );
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertEquals($message, $response->getContent());
    }
}
