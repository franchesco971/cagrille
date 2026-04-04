<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Repository;

use Cagrille\AliExpressBundle\Contract\AliExpressOrderRepositoryInterface;
use Cagrille\AliExpressBundle\Entity\AliExpressOrder;
use Cagrille\AliExpressBundle\Entity\AliExpressOrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Repository Doctrine pour les commandes AliExpress.
 *
 * Principe SRP : gère uniquement les requêtes de persistance.
 * Principe DIP : implémente AliExpressOrderRepositoryInterface.
 */
final class AliExpressOrderRepository implements AliExpressOrderRepositoryInterface
{
    /** @var EntityRepository<AliExpressOrder> */
    private readonly EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<AliExpressOrder> $repo */
        $repo = $this->entityManager->getRepository(AliExpressOrder::class);
        $this->repository = $repo;
    }

    public function save(AliExpressOrder $order, bool $flush = true): void
    {
        $this->entityManager->persist($order);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function find(int $id): ?AliExpressOrder
    {
        return $this->repository->find($id);
    }

    /**
     * @inheritdoc
     */
    public function findBySyliusOrderId(int $syliusOrderId): array
    {
        return $this->repository->findBy(
            ['syliusOrderId' => $syliusOrderId],
            ['createdAt' => 'DESC'],
        );
    }

    /**
     * @inheritdoc
     */
    public function findByStatus(AliExpressOrderStatus $status): array
    {
        return $this->repository->findBy(
            ['status' => $status],
            ['createdAt' => 'DESC'],
        );
    }

    /**
     * @inheritdoc
     */
    public function findRetryable(int $maxRetries = 3): array
    {
        /** @var AliExpressOrder[] $result */
        $result = $this->repository
            ->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.retryCount < :maxRetries')
            ->setParameter('status', AliExpressOrderStatus::Failed)
            ->setParameter('maxRetries', $maxRetries)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function findPlacedWithoutTracking(): array
    {
        /** @var AliExpressOrder[] $result */
        $result = $this->repository
            ->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.trackingNumber IS NULL')
            ->andWhere('o.aliExpressOrderId IS NOT NULL')
            ->setParameter('status', AliExpressOrderStatus::Placed)
            ->orderBy('o.placedAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function findAll(): array
    {
        return $this->repository->findBy([], ['createdAt' => 'DESC']);
    }

    /**
     * @inheritdoc
     */
    public function findPaginated(int $page, int $limit): array
    {
        /** @var AliExpressOrder[] $result */
        $result = $this->repository
            ->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countAll(): int
    {
        /** @var int $count */
        $count = $this->repository
            ->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function countByStatus(AliExpressOrderStatus $status): int
    {
        /** @var int $count */
        $count = $this->repository
            ->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }
}
