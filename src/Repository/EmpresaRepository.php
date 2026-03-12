<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Empresa>
 */
class EmpresaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Empresa::class);
    }

    /**
     * Devuelve todas las empresas como array [ ruc => [ SOL_USER => ..., ... ] ]
     * para compatibilidad con el formato esperado por SeeFactory y FileConfigProvider.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCompaniesArray(): array
    {
        $all = $this->findBy([], ['ruc' => 'ASC']);
        $result = [];
        foreach ($all as $e) {
            $result[$e->getRuc()] = [
                'SOL_USER' => $e->getSolUser(),
                'SOL_PASS' => $e->getSolPass(),
                'certificate' => $e->getCertificate(),
                'logo' => $e->getLogo(),
                'ambiente' => $e->getAmbiente(),
            ];
        }
        return $result;
    }

    public function findByRuc(string $ruc): ?Empresa
    {
        return $this->findOneBy(['ruc' => $ruc]);
    }
}
