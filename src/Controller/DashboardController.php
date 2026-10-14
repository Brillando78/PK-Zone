<?php

namespace App\Controller;

use App\Service\ParkingBillingService;
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
use Doctrine\DBAL\LockMode;

class DashboardController extends AbstractController
{

    // ============================================================
    //  ESPACE UTILISATEUR (CLIENT)
    // ============================================================

    #[Route('/', name: 'app_user_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function userDashboard(
        ParkingSpotRepository $spotRepository,
        ParkingRegistrationRepository $registrationRepository,
        ParkingBillingService $billing
    ): Response {
        $spots              = $spotRepository->findAll();
        $activeRegistrations = $registrationRepository->findActiveByUser($this->getUser());
        $totalSpots         = count($spots);
        $occupiedSpots      = count(array_filter($spots, fn($s) => $s->getStatus() === 'occupied'));
        $availableSpots     = $totalSpots - $occupiedSpots;

        $flatRateAr = $billing->getRateForDate(new \DateTime('today'));
        
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
    EntityManagerInterface $entityManager
): Response {
    if (!$this->isCsrfTokenValid('reserve', $request->request->get('_token'))) {
        throw $this->createAccessDeniedException('Jeton CSRF invalide.');
    }

    if ($this->isGranted('ROLE_ADMIN')) {
        $this->addFlash('error', 'Les administrateurs ne peuvent pas réserver de place.');
        return $this->redirectToRoute('app_user_dashboard');
    }

    $spotId       = (int) $request->request->get('spot_id', 0);
    $vehiclePlate = strtoupper(trim($request->request->get('vehiclePlate', '')));

    if (!preg_match('/^[A-Z0-9 -]{6,20}$/', $vehiclePlate)) {
    $this->addFlash('error', 'Plaque invalide (6 à 20 caractères : lettres, chiffres, espace, tiret).');
    return $this->redirectToRoute('app_user_dashboard');
}

    $user = $this->getUser();

    // Tout ce qui suit est une seule opération atomique, avec la place verrouillée
    $error = $entityManager->wrapInTransaction(
        function () use ($entityManager, $spotId, $vehiclePlate, $user): ?string {
            $spot = $entityManager->find(ParkingSpot::class, $spotId, LockMode::PESSIMISTIC_WRITE);

            if (!$spot || $spot->getStatus() !== 'available') {
                return 'Cette place n\'est pas disponible.';
            }

            $existing = $entityManager->getRepository(ParkingRegistration::class)->findOneBy([
                'vehiclePlate' => $vehiclePlate,
                'status'       => 'active',
            ]);
            if ($existing) {
                return sprintf('Le véhicule %s est déjà garé.', $vehiclePlate);
            }

            $registration = new ParkingRegistration();
            $registration->setVehiclePlate($vehiclePlate);
            $registration->setSpot($spot);
            $registration->setEntryTime(new \DateTime());
            $registration->setStatus('active');
            $registration->setUser($user);

            $spot->setStatus('occupied');
            $entityManager->persist($registration);

            return null; 
        }
    );

    if ($error !== null) {
        $this->addFlash('error', $error);
        return $this->redirectToRoute('app_user_dashboard');
    }

    $spot = $entityManager->find(ParkingSpot::class, $spotId);
    $this->addFlash('success', sprintf('Place %s réservée pour %s !', $spot->getSpotNumber(), $vehiclePlate));
    return $this->redirectToRoute('app_user_dashboard');
}


    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(
        ParkingSpotRepository $spotRepository,
        ParkingRegistrationRepository $registrationRepository,
        ParkingBillingService $billing
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

        $flatRateAr = $billing->getRateForDate($todayStart);

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

    #[Route('/admin/exit/{id}', name: 'app_admin_exit', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
public function adminExit(
    int $id,
    Request $request,
    ParkingBillingService $billing,
    EntityManagerInterface $entityManager
): Response {
    if (!$this->isCsrfTokenValid('admin_exit' . $id, $request->request->get('_token'))) {
        throw $this->createAccessDeniedException('Jeton CSRF invalide.');
    }

    $result = $entityManager->wrapInTransaction(
        function () use ($entityManager, $billing, $id): array {
            $registration = $entityManager->find(ParkingRegistration::class, $id, LockMode::PESSIMISTIC_WRITE);

            if (!$registration || $registration->getStatus() !== 'active') {
                return ['error' => 'Session de stationnement introuvable ou déjà clôturée.'];
            }

            return $billing->closeRegistration($registration);
        }
    );

    if (isset($result['error'])) {
        $this->addFlash('error', $result['error']);
        return $this->redirectToRoute('app_admin_dashboard');
    }

    $this->addFlash('success', sprintf(
        'Paiement encaissé pour %s. Durée : %dh %dmin. Montant : %s Ar. (Place %s libérée)',
        $result['plate'],
        floor($result['seconds'] / 3600),
        floor(($result['seconds'] % 3600) / 60),
        number_format($result['amount'], 0, ',', ' '),
        $result['spotNumber']
    ));

    return $this->redirectToRoute('app_admin_dashboard');
}

    #[Route('/admin/spots', name: 'app_spots_management', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function manageSpots(ParkingSpotRepository $spotRepository): Response
    {
        return $this->render('dashboard/spots.html.twig', [
            'spots'      => $spotRepository->findAll(),
            'flatRateAr' => ParkingBillingService::DEFAULT_RATE_AR,
        ]);
    }

    #[Route('/admin/spots/add', name: 'app_spot_add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addSpot(
        Request $request,
        ParkingSpotRepository $spotRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if(!$this -> isCsrfTokenValid('spot_add',$request->request->get('_token'))){
            throw $this ->createAccessDeniedException('Jeton CSRF invalide');
        }
        $spotNumber = strtoupper(trim($request->request->get('spotNumber', '')));
        $type = 'standard';

        if (!preg_match('/^[A-Z0-9-]{1,20}$/', $spotNumber)) {
            $this->addFlash('error', 'Numéro de place invalide (1 à 20 caractères : lettres, chiffres, tiret).');
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
        $spot->setPricePerHour((string) ParkingBillingService::DEFAULT_RATE_AR);

        $entityManager->persist($spot);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Place %s ajoutée avec succès !', $spotNumber));
        return $this->redirectToRoute('app_spots_management');
    }

    #[Route('/admin/spots/delete/{id}', name: 'app_spot_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteSpot(
        int $id,
        Request $request,
        ParkingSpotRepository $spotRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if(!$this -> isCsrfTokenValid('spot_delete'. $id,$request->request->get('_token'))){
            throw $this ->createAccessDeniedException('Jeton CSRF invalide');

        }

        $spot = $spotRepository->find($id);

        if (!$spot) {
            $this->addFlash('error', 'Place introuvable.');
            return $this->redirectToRoute('app_spots_management');
        }

        if ($spot->getStatus() === 'occupied') {
            $this->addFlash('error', 'Impossible de supprimer une place occupée.');
            return $this->redirectToRoute('app_spots_management');
        }

        $hasHistory = $entityManager->getRepository(ParkingRegistration::class)->count(['spot' => $spot]) > 0;
        if ($hasHistory) {
        $this->addFlash('error', sprintf(
            'La place %s a un historique de stationnement : impossible de la supprimer.',
            $spot->getSpotNumber()
        ));
        return $this->redirectToRoute('app_spots_management');
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
            'flatRateAr'       => ParkingBillingService::DEFAULT_RATE_AR,
        ]);
    }

    #[Route('/admin/pricing/add', name: 'app_pricing_add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addOccasionalPrice(
        Request $request,
        OccasionalPriceRepository $priceRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('pricing_add', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF refuse : pricing_add');
        }

        $date = $request->request->get('date', '');
        $price = (float) $request->request->get('price', 0);
        $reason = trim($request->request->get('reason', ''));

        if (empty($date) || $price < 100 || $price > 1000000) {
            $this->addFlash('error', 'La date est requise et le prix doit être compris entre 100 et 1 000 000 Ar.');
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
        Request $request,
        OccasionalPriceRepository $priceRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('pricing_delete' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF refuse : pricing_delete' . $id);
        }

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
