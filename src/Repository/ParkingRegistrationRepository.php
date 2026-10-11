<?php

namespace App\Repository;

use App\Entity\ParkingRegistration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParkingRegistration>
 */
class ParkingRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParkingRegistration::class);
    }

    /**
     * @return ParkingRegistration[] Returns an array of active registrations
     */
    public function findActiveRegistrations(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere("r.status = 'active'")
            ->orderBy('r.entryTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
