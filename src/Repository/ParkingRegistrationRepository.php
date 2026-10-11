<?php

namespace App\Repository;

use App\Entity\ParkingRegistration;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ParkingRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParkingRegistration::class);
    }

    public function findActiveRegistrations(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere("r.status = 'active'")
            ->orderBy('r.entryTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere("r.status = 'active'")
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.entryTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}