<?php

namespace Entity;

use App\Entity\Event;
use App\Exception\IncompleteEventException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{

    public function testGetSetTitle(): void
    {
        $entity = new Event();
        $value = uniqid();
        $entity->setTitle($value);
        $this->assertEquals($value, $entity->getTitle());
    }

    public function testGetSetStart(): void
    {
        $entity = new Event();
        $value = new DateTimeImmutable('2020-01-01 12:00:00+2:00');
        $entity->setStart($value);
        $this->assertEquals('2020-01-01 10:00:00+00:00', $entity->getStart()->format('Y-m-d H:i:sP'));
    }

    public function testGetSetEnd(): void
    {
        $entity = new Event();
        $value = new DateTimeImmutable('2020-01-01 12:00:00+2:00');
        $entity->setEnd($value);
        $this->assertEquals('2020-01-01 10:00:00+00:00', $entity->getEnd()->format('Y-m-d H:i:sP'));
    }

    public function testJsonSerialize(): void
    {
        $entity = new Event();
        $entity->setTitle('Test');
        $entity->setStart(DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'));
        $entity->setEnd(DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2021-01-01T00:00:00'));

        $this->assertEquals(
            '{"id":null,"title":"Test","start":"2020-01-01T00:00:00","end":"2021-01-01T00:00:00"}',
            json_encode($entity)
        );
    }

    public static function incompleteEventProvider(): iterable
    {
       yield 'missingTitle' => [(new Event)->setStart(DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))->setEnd(DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))];
       yield 'missingStart' => [(new Event)->setTitle('Test')->setEnd(DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))];
       yield 'missingEnd' => [(new Event)->setEnd(DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2020-01-01T00:00:00'))];
    }

    #[DataProvider('incompleteEventProvider')]
    public function testJsonSerializeThrows(Event $event): void
    {
        // json_encode should throw a IncompleteEventException if any values are null
        $this->expectException(IncompleteEventException::class);
        json_encode($event);
    }
}
