<?php

namespace App\Command;

use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/** @psalm-suppress UnusedClass */
#[AsCommand(name: 'events:today')]
final class EventListCommand
{
    public function __construct(private EventRepository $eventManager)
    {

    }
    public function __invoke(OutputInterface $output): int
    {
        $today = date("Y-m-d");
        $start = date("Y-m-d") . ' 00:00:00';
        $end = date("Y-m-d") . ' 23:59:59';

        /** @var Event[] $events */
        $events = $this->eventManager
            ->createQueryBuilder('event')
            ->where("event.start BETWEEN :start AND :end")
            ->orWhere("event.end BETWEEN :start AND :end")
            ->orWhere("(event.start < :start AND event.end >= :end)")
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->execute();

        $buckets = [];
        foreach ($events as $event) {
            assert($event->getStart() !== null);
            if ($event->getStart()->format('Y-m-d') != $today) {
                $buckets['00:00'][] = $event;
            } else {
                $buckets[$event->getStart()->format('H:00')][] = $event;
            }
        }

        if ($buckets) {
            foreach ($buckets as $hour => $bucketEvents) {
                $output->writeln([
                    $hour,
                    '====='
                ]);
                foreach ($bucketEvents as $event) {
                    $output->writeln($this->formatEvent($event));
                }
            }
            return Command::SUCCESS;
        }

        $output->writeln('No Events today');
        return Command::INVALID;
    }

    protected function formatEvent(Event $event): string
    {
        assert($event->getStart() !== null);
        assert($event->getEnd() !== null);
        return "{$event->getTitle()} | {$event->getStart()->format('Y-m-d H:i:s')} - {$event->getEnd()->format('Y-m-d H:i:s')}";
    }
}
