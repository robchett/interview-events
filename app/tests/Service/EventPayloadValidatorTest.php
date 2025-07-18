<?php

namespace Service;

use App\Entity\Event;
use App\Exception\EventDeserializationException;
use App\Exception\EventValidationException;
use App\Repository\EventRepository;
use App\Service\EventPayloadValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventPayloadValidatorTest extends KernelTestCase
{
    public function testDeserializeEvents()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('deserialize')->willReturn(['__MOCK__']);

        $eventPayloadValidator = new EventPayloadValidator();
        $res = $eventPayloadValidator->deserializeEvents('', $serializer);
        $this->assertEquals(['__MOCK__'], $res);
    }

    public static function deserializeExceptionType(): array
    {
        return [
            [NotNormalizableValueException::class],
            [UnexpectedValueException::class],
            [ExceptionInterface::class],
        ];
    }

    #[DataProvider('deserializeExceptionType')]
    public function testDeserializeEventsThrowsEventDeserializationException(string $exception)
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('deserialize')->willThrowException($this->createMock($exception));

        $eventPayloadValidator = new EventPayloadValidator();
        $this->expectException(EventDeserializationException::class);
        $eventPayloadValidator->deserializeEvents('', $serializer);
    }

    public function testCheckForValidationErrors()
{
    $testEvent = new Event();
    $validationResult = $this->createMock(ConstraintViolationListInterface::class);
    $validationResult->method('count')->willReturn(0);
    $validator = $this->createMock(ValidatorInterface::class);
    $validator->method('validate')->with($testEvent)->willReturn($validationResult);

    $eventPayloadValidator = new EventPayloadValidator();
    $eventPayloadValidator->checkForValidationErrors([$testEvent], $validator);
}

    public function testCheckForValidationErrorsThrows()
    {
        $testEvent = new Event();
        $validationResult = $this->createMock(ConstraintViolationListInterface::class);
        $validationResult->method('count')->willReturn(1);
        $validationResult->method('__toString')->willReturn("{error}");
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->with($testEvent)->willReturn($validationResult);

        $eventPayloadValidator = new EventPayloadValidator();
        $this->expectException(EventValidationException::class);
        $this->expectExceptionMessage("Validation error: {error} in event\n{\"title\":null,\"start\":null,\"end\":null}");
        $eventPayloadValidator->checkForValidationErrors([$testEvent], $validator);
    }

    public function testCheckLocalOverlaps()
    {
        $testEvent1 = new Event();
        $testEvent1->setTitle("Event 1");
        $testEvent1->setStart(new \DateTimeImmutable('2020-01-01 12:00:00'));
        $testEvent1->setEnd(new \DateTimeImmutable('2020-01-01 13:00:00'));
        $testEvent2 = new Event();
        $testEvent2->setTitle("Event 2");
        $testEvent2->setStart(new \DateTimeImmutable('2020-01-02 12:00:00'));
        $testEvent2->setEnd(new \DateTimeImmutable('2020-01-02 13:00:00'));

        $eventPayloadValidator = new EventPayloadValidator();
        // Completing the method call without throwing an exception is sufficient
        $this->expectNotToPerformAssertions();
        $eventPayloadValidator->checkLocalOverlaps([$testEvent1, $testEvent2]);
    }

    public function testCheckLocalOverlapsThrows()
    {
        $testEvent1 = new Event();
        $testEvent1->setTitle("Event 1");
        $testEvent1->setStart(new \DateTimeImmutable('2020-01-01 12:00:00'));
        $testEvent1->setEnd(new \DateTimeImmutable('2020-01-01 13:00:00'));
        $testEvent2 = new Event();
        $testEvent2->setTitle("Event 2");
        $testEvent2->setStart(new \DateTimeImmutable('2020-01-01 12:00:00'));
        $testEvent2->setEnd(new \DateTimeImmutable('2020-01-01 12:30:00'));

        $validationResult = $this->createMock(ConstraintViolationListInterface::class);
        $validationResult->method('count')->willReturn(0);

        $eventPayloadValidator = new EventPayloadValidator();
        $this->expectException(EventValidationException::class);
        $this->expectExceptionMessage("Validation error: Event {\"id\":null,\"title\":\"Event 2\",\"start\":\"2020-01-01T12:00:00\",\"end\":\"2020-01-01T12:30:00\"} overlaps with {\"id\":null,\"title\":\"Event 1\",\"start\":\"2020-01-01T12:00:00\",\"end\":\"2020-01-01T13:00:00\"}");
        $eventPayloadValidator->checkLocalOverlaps([$testEvent1, $testEvent2]);
    }

    public function testCheckDatabaseOverlaps()
    {
        self::bootKernel();
        $container = static::getContainer();
        $repository = $container->get(EventRepository::class);
        $testEvent1 = new Event();
        $testEvent1->setTitle("Event 1");
        $testEvent1->setStart(new \DateTimeImmutable('2025-01-01 11:00:00'));
        $testEvent1->setEnd(new \DateTimeImmutable('2025-01-01 12:00:00'));

        $eventPayloadValidator = new EventPayloadValidator();
        // Completing the method call without throwing an exception is sufficient
        $this->expectNotToPerformAssertions();
        $eventPayloadValidator->checkDatabaseOverlaps([$testEvent1], $repository);
    }

    public static function getInvalidTimes(): array
    {
        return [
            'startOverlap' => ['2025-01-01T12:30:00', '2025-01-01T13:30:00'],
            'endOverlap' => ['2025-01-01T11:30:00', '2025-01-01T12:30:00'],
            'contains' => ['2025-01-01T11:30:00', '2025-01-01T13:30:00'],
        ];
    }

    #[DataProvider('getInvalidTimes')]
    public function testCheckDatabaseOverlapsThrows(string $start, string $end)
    {
        self::bootKernel();
        $container = static::getContainer();
        $repository = $container->get(EventRepository::class);

        $testEvent1 = new Event();
        $testEvent1->setTitle("Event 1");
        $testEvent1->setStart(new \DateTimeImmutable($start));
        $testEvent1->setEnd(new \DateTimeImmutable($end));

        $eventPayloadValidator = new EventPayloadValidator();
        $this->expectException(EventValidationException::class);
        $this->expectExceptionMessage("Validation error: Event {\"id\":null,\"title\":\"Event 1\",\"start\":\"$start\",\"end\":\"$end\"} overlaps with {\"id\":1,\"title\":\"Test Event 0\",\"start\":\"2025-01-01T12:00:00\",\"end\":\"2025-01-01T13:00:00\"}");
        $eventPayloadValidator->checkDatabaseOverlaps([$testEvent1], $repository);
    }

    public function testPersistEvents()
    {
        $testEvent1 = new Event();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($testEvent1);
        $entityManager->expects($this->once())->method('flush');

        $eventPayloadValidator = new EventPayloadValidator();
        $eventPayloadValidator->persistEvents([$testEvent1], $entityManager);
    }
}
