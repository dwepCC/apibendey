<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabla empresa para persistir datos de empresas (multitenant).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE empresa (
            ruc VARCHAR(11) NOT NULL,
            sol_user VARCHAR(100) NOT NULL,
            sol_pass VARCHAR(255) NOT NULL,
            certificate VARCHAR(255) DEFAULT NULL,
            logo VARCHAR(255) DEFAULT NULL,
            ambiente VARCHAR(20) DEFAULT \'pruebas\' NOT NULL,
            PRIMARY KEY(ruc)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE empresa');
    }
}
