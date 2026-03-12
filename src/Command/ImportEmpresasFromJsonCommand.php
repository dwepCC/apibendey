<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Empresa;
use App\Repository\EmpresaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importa empresas desde data/empresas.json a la tabla empresa (BD).
 * Útil la primera vez que se usa la BD; por defecto ambiente=pruebas.
 */
class ImportEmpresasFromJsonCommand extends Command
{
    protected static $defaultName = 'app:empresas:import-from-json';

    private string $dataPath;
    private EntityManagerInterface $em;
    private EmpresaRepository $repository;

    public function __construct(string $dataPath, EntityManagerInterface $em, EmpresaRepository $repository)
    {
        parent::__construct();
        $this->dataPath = $dataPath;
        $this->em = $em;
        $this->repository = $repository;
    }

    protected function configure(): void
    {
        $this->setDescription('Importa empresas desde data/empresas.json a la base de datos.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $baseDir = $this->dataPath;
        if (DIRECTORY_SEPARATOR === '\\') {
            $baseDir = str_replace('/', DIRECTORY_SEPARATOR, $baseDir);
        }
        $dirResolved = realpath($baseDir);
        if ($dirResolved !== false) {
            $baseDir = $dirResolved;
        }
        $path = $baseDir . DIRECTORY_SEPARATOR . 'empresas.json';

        if (!is_file($path)) {
            $io->warning('No existe el archivo: ' . $path . '. Nada que importar.');
            return Command::SUCCESS;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            $io->error('No se pudo leer el archivo: ' . $path);
            return Command::FAILURE;
        }
        $json = trim($json);
        if ($json === '') {
            $io->warning('El archivo está vacío. Nada que importar.');
            return Command::SUCCESS;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $io->error('empresas.json no es un JSON válido: ' . (json_last_error_msg() ?: 'error desconocido'));
            return Command::FAILURE;
        }

        $count = 0;
        foreach ($data as $ruc => $config) {
            $ruc = is_string($ruc) ? trim($ruc) : (string) $ruc;
            if ($ruc === '' || !is_array($config)) {
                continue;
            }

            $entity = $this->repository->findByRuc($ruc);
            if ($entity === null) {
                $entity = new Empresa();
                $entity->setRuc($ruc);
            }

            $entity->setSolUser((string) ($config['SOL_USER'] ?? ''));
            $entity->setSolPass((string) ($config['SOL_PASS'] ?? ''));
            $entity->setCertificate($config['certificate'] ?? null);
            $entity->setLogo($config['logo'] ?? null);
            $entity->setAmbiente((string) ($config['ambiente'] ?? 'pruebas'));

            $this->em->persist($entity);
            $count++;
        }

        $this->em->flush();
        $io->success('Importadas ' . $count . ' empresa(s) desde empresas.json.');
        return Command::SUCCESS;
    }
}
