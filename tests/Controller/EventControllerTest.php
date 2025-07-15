<?php

namespace Controller;

use App\Controller\EventController;
use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventControllerTest extends KernelTestCase
{

    public function testGetEvents()
    {
        self::bootKernel();
        $container = static::getContainer();

        $eventManager = $this->createMock(EventRepository::class);
        $eventManager->method('findAll')->willReturn([
            new Event()->setTitle('Test1')->setStart(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T12:00:00')),
            new Event()->setTitle('Test2')->setStart(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T12:00:00')),
            new Event()->setTitle('Test3')->setStart(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T12:00:00')),
        ]);

        $controller = new EventController();
        $controller->setContainer($container);
        $response = $controller->list($eventManager);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $expectedBody = <<<JSON
        [{"id":null,"title":"Test1","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"},{"id":null,"title":"Test2","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"},{"id":null,"title":"Test3","start":"2020-01-01T00:00:00","end":"2020-01-01T12:00:00"}]
        JSON;
        $this->assertEquals($expectedBody, $response->getContent());
    }


}
