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

    #[ORM\Column]
    private ?int $user_id = null;

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

        $this->start = $start->setTimezone(new \DateTimeZone('UTC'));

        return $this;
    }

    public function getEnd(): ?\DateTimeImmutable
    {
        return $this->end;
    }

    public function setEnd(\DateTimeImmutable $end): static
    {
        $this->end = $end->setTimezone(new \DateTimeZone('UTC'));

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $userId): static
    {
        $this->user_id = $userId;

        return $this;
    }

    public function isOwnedBy(?User $user): bool
    {
        if ($this->user_id === null || $this->user_id === 0) {
            return true;
        }
        return $this->user_id === $user?->getId();
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
            'user_id' => $this->getUserId()
        ];
    }
}
