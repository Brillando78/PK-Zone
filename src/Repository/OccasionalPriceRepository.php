<?php

namespace App\Repository;

use App\Entity\OccasionalPrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OccasionalPrice>
 */
class OccasionalPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OccasionalPrice::class);
    }

    public function findByDate(\DateTimeInterface $date): ?OccasionalPrice
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.date = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
