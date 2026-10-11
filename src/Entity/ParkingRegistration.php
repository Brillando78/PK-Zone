<?php

namespace App\Entity;

use App\Repository\ParkingRegistrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;

#[ORM\Entity(repositoryClass: ParkingRegistrationRepository::class)]
class ParkingRegistration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $vehiclePlate = null;

    #[ORM\ManyToOne(inversedBy: 'registrations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ParkingSpot $spot = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $entryTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $exitTime = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $amountPaid = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null; // 'active', 'completed'
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ? user $user = null ;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVehiclePlate(): ?string
    {
        return $this->vehiclePlate;
    }

    public function setVehiclePlate(string $vehiclePlate): static
    {
        $this->vehiclePlate = $vehiclePlate;
        return $this;
    }

    public function getSpot(): ?ParkingSpot
    {
        return $this->spot;
    }

    public function setSpot(?ParkingSpot $spot): static
    {
        $this->spot = $spot;
        return $this;
    }

    public function getEntryTime(): ?\DateTimeInterface
    {
        return $this->entryTime;
    }

    public function setEntryTime(\DateTimeInterface $entryTime): static
    {
        $this->entryTime = $entryTime;
        return $this;
    }

    public function getExitTime(): ?\DateTimeInterface
    {
        return $this->exitTime;
    }

    public function setExitTime(?\DateTimeInterface $exitTime): static
    {
        $this->exitTime = $exitTime;
        return $this;
    }

    public function getAmountPaid(): ?string
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(?string $amountPaid): static
    {
        $this->amountPaid = $amountPaid;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getUser() : ?User 
    {
        return $this ->user ;
    }

    public function setUser(?User $user): static
    {
        $this ->user = $user; 
        return $this;
    }
}
