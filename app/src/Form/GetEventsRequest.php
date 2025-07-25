<?php

namespace App\Form;

use App\Entity\User;
use App\Enums\EventFilter;
use App\Repository\EventRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Validator\Constraints as Assert;

final class GetEventsRequest
{
    #[Assert\DateTime]
    public ?\DateTimeImmutable $startFrom = null;
    #[Assert\DateTime]
    public ?\DateTimeImmutable $startTo = null;
    #[Assert\DateTime]
    public ?\DateTimeImmutable $endFrom = null;
    #[Assert\DateTime]
    public ?\DateTimeImmutable $endTo = null;
    public ?string $title = null;
    #[Assert\LessThan(1000)]
    public ?int $pageSize = null;
    #[Assert\GreaterThan(0)]
    public ?int $page = null;

    public function getQuery(EventRepository $eventManager, ?User $user): Query
    {
        $query = $eventManager->createQueryBuilder('event');
        $query->addOrderBy('event.start', 'ASC');
        $this->addDateFilter('start >', EventFilter::startFrom, $this->startFrom ?? null, $query);
        $this->addDateFilter('start <', EventFilter::startTo, $this->startTo ?? null, $query);
        $this->addDateFilter('end >', EventFilter::endFrom, $this->endFrom ?? null, $query);
        $this->addDateFilter('end <', EventFilter::endTo, $this->endTo ?? null, $query);
        $this->addTextFilter('title', EventFilter::title, $this->title ?? '', $query);
        $this->addPagination($this->pageSize ?? 1000, $this->page ?? 1, $query);
        $query
            ->andWhere('event.user_id = :user_id')
            ->setParameter('user_id', $user?->getId() ?? 0);
        return $query->getQuery();
    }

    /**
     * @param 'title' $column
     */
    protected function addTextFilter(string $column, EventFilter $filter, ?string $value, QueryBuilder $query): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $query->andWhere("event.{$column} LIKE :{$filter->value}");
        $query->setParameter($filter->value, "%$value%");
    }

    /**
     * @param 'start >'|'start <'|'end >'|'end <' $column
     */
    protected function addDateFilter(string $column, EventFilter $filter, ?\DateTimeImmutable $value, QueryBuilder $query): void
    {
        if (!$value) {
            return;
        }
        $query->andWhere("event.{$column} :{$filter->value}");
        $query->setParameter($filter->value, $value->format('Y-m-d H:i:s'));
    }

    protected function addPagination(int $pageSize, int $page, QueryBuilder $query): void
    {
        $query->setMaxResults($pageSize);
        $query->setFirstResult($pageSize * ($page - 1));
    }
}
