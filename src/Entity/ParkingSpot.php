<?php

namespace App\Entity;

use App\Repository\ParkingSpotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParkingSpotRepository::class)]
class ParkingSpot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $spotNumber = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null; // 'standard', 'electric', 'disabled'

    #[ORM\Column(length: 50)]
    private ?string $status = null; // 'available', 'occupied'

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $pricePerHour = null;

    /**
     * @var Collection<int, ParkingRegistration>
     */
    #[ORM\OneToMany(targetEntity: ParkingRegistration::class, mappedBy: 'spot', orphanRemoval: true)]
    private Collection $registrations;

    public function __construct()
    {
        $this->registrations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpotNumber(): ?string
    {
        return $this->spotNumber;
    }

    public function setSpotNumber(string $spotNumber): static
    {
        $this->spotNumber = $spotNumber;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getPricePerHour(): ?string
    {
        return $this->pricePerHour;
    }

    public function setPricePerHour(string $pricePerHour): static
    {
        $this->pricePerHour = $pricePerHour;
        return $this;
    }

    /**
     * @return Collection<int, ParkingRegistration>
     */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    public function addRegistration(ParkingRegistration $registration): static
    {
        if (!$this->registrations->contains($registration)) {
            $this->registrations->add($registration);
            $registration->setSpot($this);
        }
        return $this;
    }

    public function removeRegistration(ParkingRegistration $registration): static
    {
        if ($this->registrations->removeElement($registration)) {
            // set the owning side to null (unless already changed)
            if ($registration->getSpot() === $this) {
                $registration->setSpot(null);
            }
        }
        return $this;
    }
}
