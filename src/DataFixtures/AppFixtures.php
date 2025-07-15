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
        // create 200 products! Bam!
        for ($i = 1; $i <= 200; $i++) {
            $event = new Event();
            $event->setTitle('Test Event ' . $i);
            $event->setStart(\DateTimeImmutable::createFromTimestamp(time() - ($i * 24 * 60 * 60)));
            $event->setEnd(\DateTimeImmutable::createFromTimestamp(time() - (($i - 1 ) * 24 * 60 * 60)));
            $manager->persist($event);

        }

        $manager->flush();
    }
}
