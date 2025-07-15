<?php

namespace App\Entity;

use App\Exception\IncompleteEventException;
use App\Repository\EventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
final class Event implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column]
    #[Assert\NotNull()]
    private ?\DateTimeImmutable $start = null;

    #[ORM\Column]
    #[Assert\NotNull()]
    #[Assert\GreaterThan(propertyPath: 'start', message: 'This value should be greater than [event.start].')]
    private ?\DateTimeImmutable $end = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStart(): ?\DateTimeImmutable
    {
        return $this->start;
    }

    public function setStart(\DateTimeImmutable $start): static
    {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): ?\DateTimeImmutable
    {
        return $this->end;
    }

    public function setEnd(\DateTimeImmutable $end): static
    {
        $this->end = $end;

        return $this;
    }

    #[\Override]
    /**
     * @throws IncompleteEventException
     */
    public function jsonSerialize(): mixed
    {
        if (
            $this->getTitle() === null ||
            $this->getStart() === null ||
            $this->getEnd() === null
        ) {
            throw new IncompleteEventException();
        }
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'start' => $this->getStart()->format('Y-m-d\TH:i:s'),
            'end' => $this->getEnd()->format('Y-m-d\TH:i:s'),
        ];
    }
}
