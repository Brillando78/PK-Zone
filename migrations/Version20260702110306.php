<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260702110306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parking_registration (id INT AUTO_INCREMENT NOT NULL, vehicle_plate VARCHAR(50) NOT NULL, entry_time DATETIME NOT NULL, exit_time DATETIME DEFAULT NULL, amount_paid NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(50) NOT NULL, spot_id INT NOT NULL, INDEX IDX_E34A1EA42DF1D37C (spot_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE parking_spot (id INT AUTO_INCREMENT NOT NULL, spot_number VARCHAR(50) NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, price_per_hour NUMERIC(10, 2) NOT NULL, UNIQUE INDEX UNIQ_C1A60A00A301F630 (spot_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE parking_registration ADD CONSTRAINT FK_E34A1EA42DF1D37C FOREIGN KEY (spot_id) REFERENCES parking_spot (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parking_registration DROP FOREIGN KEY FK_E34A1EA42DF1D37C');
        $this->addSql('DROP TABLE parking_registration');
        $this->addSql('DROP TABLE parking_spot');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
