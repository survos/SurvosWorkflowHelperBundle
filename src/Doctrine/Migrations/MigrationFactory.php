<?php

namespace App\Doctrine\Migrations;

use App\Services\MediaService;
use App\Services\MigrationService;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

class MigrationFactory implements \Doctrine\Migrations\Version\MigrationFactory
{
    private Connection $connection;
    private LoggerInterface $logger;
    private MediaService $mediaService;
    private MigrationService $migrationService;

    public function __construct(Connection $connection, LoggerInterface $logger, MigrationService  $migrationService, MediaService $mediaService)
    {
        $this->connection = $connection;
        $this->logger     = $logger;
        $this->mediaService = $mediaService;
        $this->migrationService = $migrationService;
    }

    public function createVersion(string $migrationClassName) : AbstractMigration
    {
        $migration = new $migrationClassName(
            $this->connection,
            $this->logger
        );

        // or you can ommit this check
        if (method_exists($migration, 'setMediaService')) {
            $migration->setMediaService($this->mediaService);
        }

        if (method_exists($migration, 'setMigrationService')) {
            $migration->setMigrationService($this->migrationService);
        }

        return $migration;
    }
}
