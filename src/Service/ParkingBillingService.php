<?php

namespace App\Service;

use App\Entity\ParkingRegistration;
use App\Repository\OccasionalPriceRepository;

class ParkingBillingService
{
    public const DEFAULT_RATE_AR = 2000;

    public function __construct(private OccasionalPriceRepository $priceRepository)
    {
    }

    /** Tarif horaire (Ar) d'un jour : tarif occasionnel s'il existe, sinon tarif standard. */
    public function getRateForDate(\DateTimeInterface $date): int
    {
        $day   = \DateTime::createFromInterface($date)->setTime(0, 0, 0);
        $price = $this->priceRepository->findByDate($day);

        return $price ? (int) $price->getPricePerHour() : self::DEFAULT_RATE_AR;
    }

    /**
     * Clôture une session : calcule le montant, la marque terminée, libère la place.
     */
    public function closeRegistration(ParkingRegistration $registration): array
    {
        $exitTime  = new \DateTime();
        $entryTime = $registration->getEntryTime();
        $seconds   = max(0, $exitTime->getTimestamp() - $entryTime->getTimestamp());

        // Heure entamée = heure due, minimum 1 heure ; tarif du jour d'ENTRÉE
        $hours  = max(1, (int) ceil($seconds / 3600));
        $amount = $hours * $this->getRateForDate($entryTime);

        $registration->setExitTime($exitTime);
        $registration->setAmountPaid((string) $amount);
        $registration->setStatus('completed');

        $spot = $registration->getSpot();
        $spot->setStatus('available');

        return [
            'plate'      => $registration->getVehiclePlate(),
            'seconds'    => $seconds,
            'amount'     => $amount,
            'spotNumber' => $spot->getSpotNumber(),
        ];
    }
}