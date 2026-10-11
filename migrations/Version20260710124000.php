<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the occasional_price table for managing special pricing on specific dates
 */
final class Version20260710124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create occasional_price table for managing special pricing on specific dates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE occasional_price (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, price_per_hour NUMERIC(10, 2) NOT NULL, reason VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_DATE (date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE occasional_price');
    }
}
