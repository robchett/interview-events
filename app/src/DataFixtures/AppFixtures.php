<?php

namespace App\DataFixtures;

use App\Entity\Event;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** @psalm-suppress UnusedClass */
final class AppFixtures extends Fixture
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        // Create a 12pm - 1pm appointment every day for a year, 2025-01-01 -> 2025-12-31
        $start = new \DateTimeImmutable('2025-01-01T00:00:00');
        for ($i = 0; $i < 365; $i++) {
            $date = \DateInterval::createFromDateString("$i day");
            if (! $date) {
                throw new \Exception('Error generating date');
            }
            $event = new Event();
            $event->setTitle('Test Event ' . $i);
            $event->setStart($start->setTime(12,0,0)->add($date));
            $event->setEnd($start->setTime(13,0,0)->add($date));
            $event->setUserId(0);
            $manager->persist($event);
            if ($i % 100 === 0) {
                $manager->flush();
            }
        }

        $manager->flush();
    }
}
