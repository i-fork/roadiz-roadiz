<?php

declare(strict_types=1);

namespace RZ\Roadiz\Explorer\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class NodeExplorerListEvent extends Event
{
    private string $entity;
    private array $criteria;
    private array $ordering;

    public function __construct(
        string $entity,
        array $criteria,
        array $ordering
    ) {
        $this->entity = $entity;
        $this->criteria = $criteria;
        $this->ordering = $ordering;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getCriteria(): array
    {
        return $this->criteria;
    }

    public function setCriteria(array $criteria): void
    {
        $this->criteria = $criteria;
    }

    public function getOrdering(): array
    {
        return $this->ordering;
    }

    public function setOrdering(array $ordering): void
    {
        $this->ordering = $ordering;
    }
}
