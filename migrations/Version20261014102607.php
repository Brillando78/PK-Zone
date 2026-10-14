<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014102607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE occasional_price CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE parking_registration ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parking_registration ADD CONSTRAINT FK_E34A1EA4A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_E34A1EA4A76ED395 ON parking_registration (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE `user`');
        $this->addSql('ALTER TABLE occasional_price CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE parking_registration DROP FOREIGN KEY FK_E34A1EA4A76ED395');
        $this->addSql('DROP INDEX IDX_E34A1EA4A76ED395 ON parking_registration');
        $this->addSql('ALTER TABLE parking_registration DROP user_id');
    }
}
