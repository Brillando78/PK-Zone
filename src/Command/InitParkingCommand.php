<?php

namespace App\Command;

use App\Entity\ParkingSpot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-parking',
    description: 'Initializes the parking database with default spots (flat rate: 2000 Ar/h).',
)]
class InitParkingCommand extends Command
{
    // Tarif unique pour toutes les places (en Ariary)
    public const FLAT_RATE_AR = '2000';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Initialisation des Places de Parking (Tarif : 2 000 Ar/h)');

        // Clean database (registrations first, then spots)
        $io->section('Nettoyage des données existantes...');
        $this->entityManager->createQuery('DELETE FROM App\Entity\ParkingRegistration')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\ParkingSpot')->execute();

        $spots = [
            // Standard spots
            ['number' => 'A-01', 'type' => 'standard'],
            ['number' => 'A-02', 'type' => 'standard'],
            ['number' => 'A-03', 'type' => 'standard'],
            ['number' => 'A-04', 'type' => 'standard'],
            ['number' => 'A-05', 'type' => 'standard'],
            ['number' => 'A-06', 'type' => 'standard'],
            ['number' => 'A-07', 'type' => 'standard'],
            ['number' => 'A-08', 'type' => 'standard'],
            ['number' => 'A-09', 'type' => 'standard'],
            ['number' => 'A-10', 'type' => 'standard'],

            // Electric spots
            ['number' => 'E-01', 'type' => 'electric'],
            ['number' => 'E-02', 'type' => 'electric'],
            ['number' => 'E-03', 'type' => 'electric'],
            ['number' => 'E-04', 'type' => 'electric'],

            // Disabled/Handicapped spots
            ['number' => 'H-01', 'type' => 'disabled'],
            ['number' => 'H-02', 'type' => 'disabled'],
        ];

        $io->progressStart(count($spots));

        foreach ($spots as $spotData) {
            $spot = new ParkingSpot();
            $spot->setSpotNumber($spotData['number']);
            $spot->setType($spotData['type']);
            $spot->setStatus('available');
            $spot->setPricePerHour(self::FLAT_RATE_AR);

            $this->entityManager->persist($spot);
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success('Base de données initialisée avec 16 places au tarif unique de 2 000 Ar/heure !');

        return Command::SUCCESS;
    }
}
