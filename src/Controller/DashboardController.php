<?php

namespace App\Controller;

use App\Command\InitParkingCommand;
use App\Entity\ParkingSpot;
use App\Entity\ParkingRegistration;
use App\Entity\OccasionalPrice;
use App\Repository\ParkingSpotRepository;
use App\Repository\ParkingRegistrationRepository;
use App\Repository\OccasionalPriceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    // Tarif unique en Ariary (utilisé pour calculs)
    private const FLAT_RATE_AR = 2000;

    // ============================================================
    //  ESPACE UTILISATEUR (CLIENT)
    // ============================================================

    #[Route('/', name: 'app_user_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function userDashboard(
        ParkingSpotRepository $spotRepository,
        ParkingRegistrationRepository $registrationRepository,
        OccasionalPriceRepository $priceRepository
    ): Response {
        $spots              = $spotRepository->findAll();
        $activeRegistrations = $registrationRepository->findActiveByUser($this->getUser());
        $totalSpots         = count($spots);
        $occupiedSpots      = count(array_filter($spots, fn($s) => $s->getStatus() === 'occupied'));
        $availableSpots     = $totalSpots - $occupiedSpots;

        $todayPrice = $priceRepository->findByDate(new \DateTime('today'));
        $flatRateAr = $todayPrice ? (int) $todayPrice->getPricePerHour() : self::FLAT_RATE_AR;

        return $this->render('dashboard/user.html.twig', [
            'spots'               => $spots,
            'activeRegistrations' => $activeRegistrations,
            'totalSpots'          => $totalSpots,
            'occupiedSpots'       => $occupiedSpots,
            'availableSpots'      => $availableSpots,
            'flatRateAr'          => $flatRateAr,
        ]);
    }

    #[Route('/user/reserve', name: 'app_user_reserve', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function userReserve(
        Request $request,
        ParkingSpotRepository $spotRepository,
        OccasionalPriceRepository $priceRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this ->isCsrfTokenValid('reserve', $request -> request -> get('_token'))){
            throw $this -> createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Les administrateurs ne peuvent pas réserver de place.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $spotId = (int) $request->request->get('spot_id', 0);
        $spot = $spotRepository->find($spotId);
        
        if (!$spot || $spot->getStatus() !== 'available') {
            $this->addFlash('error', 'Cette place n\'est pas disponible.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $vehiclePlate = strtoupper(trim($request->request->get('vehiclePlate', '')));
        
        if (empty($vehiclePlate) || strlen($vehiclePlate) < 4) {
            $this->addFlash('error', 'Plaque d\'immatriculation invalide.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        // Check if vehicle already parked
        $existing = $entityManager->getRepository(ParkingRegistration::class)->findOneBy([
            'vehiclePlate' => $vehiclePlate,
            'status'       => 'active',
        ]);
        if ($existing) {
            $this->addFlash('error', sprintf('Le véhicule %s est déjà garé.', $vehiclePlate));
            return $this->redirectToRoute('app_user_dashboard');
        }

        $registration = new ParkingRegistration();
        $registration->setVehiclePlate($vehiclePlate);
        $registration->setSpot($spot);
        $registration->setEntryTime(new \DateTime());
        $registration->setStatus('active');
        $registration->setUser($this->getUser());

        $spot->setStatus('occupied');

        $entityManager->persist($registration);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Place %s réservée pour %s !', $spot->getSpotNumber(), $vehiclePlate));
        return $this->redirectToRoute('app_user_dashboard');
    }

    #[Route('/user/exit/{id}', name: 'app_user_exit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function userExit(
        int $id,
        Request $request,
        OccasionalPriceRepository $priceRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this -> isCsrfTokenValid('exit' . $id, $request -> request -> get('_token'))){
            throw $this -> createAccessDeniedException('Jeton CSRF invalide');
        }

        $registration = $entityManager->getRepository(ParkingRegistration::class)->find($id);

        if (!$registration || $registration->getStatus() !== 'active') {
            $this->addFlash('error', 'Session de stationnement introuvable ou déjà clôturée.');
            return $this->redirectToRoute('app_user_dashboard');
        }
        
        if($registration->getUser()?->getId()!== $this-> getUser()?->getId()){
            throw $this->createAccessDeniedException();
        }

        $exitTime        = new \DateTime();
        $entryTime       = $registration->getEntryTime();
        $durationInSeconds = max(0, $exitTime->getTimestamp() - $entryTime->getTimestamp());

        $entryDate = clone $entryTime;
        $entryDate->setTime(0, 0, 0);
        $todayPrice = $priceRepository->findByDate($entryDate);
        $flatRateAr = $todayPrice ? (int) $todayPrice->getPricePerHour() : self::FLAT_RATE_AR;

        $hours      = max(1, (int) ceil($durationInSeconds / 3600));
        $amountPaid = $hours * $flatRateAr;

        $registration->setExitTime($exitTime);
        $registration->setAmountPaid((string) $amountPaid);
        $registration->setStatus('completed');

        $spot = $registration->getSpot();
        $spot->setStatus('available');

        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Paiement validé pour %s. Durée : %dh %dmin. Montant réglé : %s Ar. (Place %s libérée)',
            $registration->getVehiclePlate(),
            floor($durationInSeconds / 3600),
            floor(($durationInSeconds % 3600) / 60),
            number_format($amountPaid, 0, ',', ' '),
            $spot->getSpotNumber()
        ));

        return $this->redirectToRoute('app_user_dashboard');
    }

    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(
        ParkingSpotRepository $spotRepository,
        ParkingRegistrationRepository $registrationRepository,
        OccasionalPriceRepository $priceRepository
    ): Response {
        $spots               = $spotRepository->findAll();
        $activeRegistrations = $registrationRepository->findActiveRegistrations();
        $totalSpots          = count($spots);
        $occupiedSpots       = count(array_filter($spots, fn($s) => $s->getStatus() === 'occupied'));
        $availableSpots      = $totalSpots - $occupiedSpots;
        $occupancyRate       = $totalSpots > 0 ? round(($occupiedSpots / $totalSpots) * 100) : 0;

        // Revenue of the day
        $todayStart = new \DateTime('today');
        $todayEnd   = new \DateTime('tomorrow');

        $todayRevenue = (float) ($registrationRepository->createQueryBuilder('r')
            ->select('SUM(r.amountPaid)')
            ->where('r.status = :status')
            ->andWhere('r.exitTime >= :start')
            ->andWhere('r.exitTime < :end')
            ->setParameter('status', 'completed')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()->getSingleScalarResult() ?? 0);

        $todayPrice = $priceRepository->findByDate($todayStart);
        $flatRateAr = $todayPrice ? (int) $todayPrice->getPricePerHour() : self::FLAT_RATE_AR;

        return $this->render('dashboard/index.html.twig', [
            'spots'               => $spots,
            'activeRegistrations' => $activeRegistrations,
            'totalSpots'          => $totalSpots,
            'occupiedSpots'       => $occupiedSpots,
            'availableSpots'      => $availableSpots,
            'occupancyRate'       => $occupancyRate,
            'todayRevenue'        => $todayRevenue,
            'flatRateAr'          => $flatRateAr,
        ]);
    }

    #[Route('/admin/exit/{id}', name: 'app_parking_exit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function registerExit(
        int $id,
        OccasionalPriceRepository $priceRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $registration = $entityManager->getRepository(ParkingRegistration::class)->find($id);

        if (!$registration || $registration->getStatus() !== 'active') {
            $this->addFlash('error', 'Session de stationnement introuvable ou déjà clôturée.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $exitTime          = new \DateTime();
        $entryTime         = $registration->getEntryTime();
        $durationInSeconds = max(0, $exitTime->getTimestamp() - $entryTime->getTimestamp());

        $entryDate = clone $entryTime;
        $entryDate->setTime(0, 0, 0);
        $todayPrice = $priceRepository->findByDate($entryDate);
        $flatRateAr = $todayPrice ? (int) $todayPrice->getPricePerHour() : self::FLAT_RATE_AR;

        // Facturation : minimum 1 heure, paliers entiers
        $hours      = max(1, (int) ceil($durationInSeconds / 3600));
        $amountPaid = $hours * $flatRateAr;

        $registration->setExitTime($exitTime);
        $registration->setAmountPaid((string) $amountPaid);
        $registration->setStatus('completed');

        $spot = $registration->getSpot();
        $spot->setStatus('available');

        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Sortie enregistrée pour %s. Durée : %dh %dmin. Montant : %s Ar. (Place %s libérée)',
            $registration->getVehiclePlate(),
            floor($durationInSeconds / 3600),
            floor(($durationInSeconds % 3600) / 60),
            number_format($amountPaid, 0, ',', ' '),
            $spot->getSpotNumber()
        ));

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/spots', name: 'app_spots_management', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function manageSpots(ParkingSpotRepository $spotRepository): Response
    {
        return $this->render('dashboard/spots.html.twig', [
            'spots'      => $spotRepository->findAll(),
            'flatRateAr' => self::FLAT_RATE_AR,
        ]);
    }

    #[Route('/admin/spots/add', name: 'app_spot_add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addSpot(
        Request $request,
        ParkingSpotRepository $spotRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $spotNumber = strtoupper(trim($request->request->get('spotNumber', '')));
        $type       = $request->request->get('type', 'standard');

        if (empty($spotNumber)) {
            $this->addFlash('error', 'Le numéro de place est requis.');
            return $this->redirectToRoute('app_spots_management');
        }

        $existing = $spotRepository->findOneBy(['spotNumber' => $spotNumber]);
        if ($existing) {
            $this->addFlash('error', sprintf('La place %s existe déjà.', $spotNumber));
            return $this->redirectToRoute('app_spots_management');
        }

        $spot = new ParkingSpot();
        $spot->setSpotNumber($spotNumber);
        $spot->setType($type);
        $spot->setStatus('available');
        $spot->setPricePerHour((string) self::FLAT_RATE_AR);

        $entityManager->persist($spot);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Place %s ajoutée avec succès !', $spotNumber));
        return $this->redirectToRoute('app_spots_management');
    }

    #[Route('/admin/spots/delete/{id}', name: 'app_spot_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteSpot(
        int $id,
        ParkingSpotRepository $spotRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $spot = $spotRepository->find($id);

        if (!$spot) {
            $this->addFlash('error', 'Place introuvable.');
            return $this->redirectToRoute('app_spots_management');
        }

        if ($spot->getStatus() === 'occupied') {
            $this->addFlash('error', 'Impossible de supprimer une place occupée.');
            return $this->redirectToRoute('app_spots_management');
        }

        $registrations = $entityManager->getRepository(ParkingRegistration::class)->findBy(['spot' => $spot]);
        foreach ($registrations as $reg) {
            $entityManager->remove($reg);
        }
        $entityManager->remove($spot);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Place %s supprimée avec succès.', $spot->getSpotNumber()));
        return $this->redirectToRoute('app_spots_management');
    }

    #[Route('/admin/history', name: 'app_history', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function history(ParkingRegistrationRepository $registrationRepository): Response
    {
        $history      = $registrationRepository->findBy(['status' => 'completed'], ['exitTime' => 'DESC']);
        $totalRevenue = array_reduce($history, fn($sum, $item) => $sum + (float) $item->getAmountPaid(), 0.0);

        return $this->render('dashboard/history.html.twig', [
            'history'      => $history,
            'totalRevenue' => $totalRevenue,
        ]);
    }

    #[Route('/admin/pricing', name: 'app_occasional_pricing', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function occasionalPricing(OccasionalPriceRepository $priceRepository): Response
    {
        $occasionalPrices = $priceRepository->findBy([], ['date' => 'DESC']);

        return $this->render('dashboard/pricing.html.twig', [
            'occasionalPrices' => $occasionalPrices,
            'flatRateAr'       => self::FLAT_RATE_AR,
        ]);
    }

    #[Route('/admin/pricing/add', name: 'app_pricing_add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addOccasionalPrice(
        Request $request,
        OccasionalPriceRepository $priceRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $date = $request->request->get('date', '');
        $price = (float) $request->request->get('price', 0);
        $reason = trim($request->request->get('reason', ''));

        if (empty($date) || $price <= 0) {
            $this->addFlash('error', 'La date et le prix sont requis.');
            return $this->redirectToRoute('app_occasional_pricing');
        }

        try {
            $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj) {
                $this->addFlash('error', 'Format de date invalide.');
                return $this->redirectToRoute('app_occasional_pricing');
            }

            $existing = $priceRepository->findByDate($dateObj);
            if ($existing) {
                $existing->setPricePerHour((string) $price);
                $existing->setReason($reason ?: null);
            } else {
                $occasional = new OccasionalPrice();
                $occasional->setDate($dateObj);
                $occasional->setPricePerHour((string) $price);
                $occasional->setReason($reason ?: null);
                $entityManager->persist($occasional);
            }

            $entityManager->flush();
            $this->addFlash('success', sprintf('Tarif du %s défini à %s Ar/h.', $dateObj->format('d/m/Y'), number_format($price, 0, ',', ' ')));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la sauvegarde.');
        }

        return $this->redirectToRoute('app_occasional_pricing');
    }

    

    #[Route('/admin/pricing/delete/{id}', name: 'app_pricing_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteOccasionalPrice(
        int $id,
        OccasionalPriceRepository $priceRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $price = $priceRepository->find($id);

        if (!$price) {
            $this->addFlash('error', 'Tarif occasionnel introuvable.');
            return $this->redirectToRoute('app_occasional_pricing');
        }

        $dateStr = $price->getDate()->format('d/m/Y');
        $entityManager->remove($price);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Tarif du %s supprimé.', $dateStr));
        return $this->redirectToRoute('app_occasional_pricing');
    }
}
