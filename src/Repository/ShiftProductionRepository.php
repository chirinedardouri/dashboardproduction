<?php

namespace App\Repository;

use App\Entity\ShiftProduction;
use App\Entity\ProductionFile;
use App\Entity\Bras;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ShiftProduction|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShiftProduction|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShiftProduction[]    findAll()
 * @method ShiftProduction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShiftProductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, ShiftProduction::class);
    }

    public function saveShiftProduction($shiftProduction): ShiftProduction
    {
        $this->entityManager->persist($shiftProduction);
        $this->entityManager->flush();

        return $shiftProduction;
    }

    public function findByProductionFile(ProductionFile $productionFile): array
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.productionFile = :file')
            ->andWhere('sp.valid = true')
            ->andWhere('sp.deleted = false')
            ->setParameter('file', $productionFile)
            ->orderBy('sp.date', 'DESC')
            ->addOrderBy('sp.posteType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.date BETWEEN :start AND :end')
            ->andWhere('sp.valid = true')
            ->andWhere('sp.deleted = false')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('sp.date', 'DESC')
            ->addOrderBy('sp.posteType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByBrasAndDate(Bras $bras, \DateTime $date): array
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.bras = :bras')
            ->andWhere('sp.date = :date')
            ->andWhere('sp.valid = true')
            ->andWhere('sp.deleted = false')
            ->setParameter('bras', $bras)
            ->setParameter('date', $date)
            ->orderBy('sp.posteType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByRef(string $ref): array
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.ref = :ref')
            ->andWhere('sp.valid = true')
            ->andWhere('sp.deleted = false')
            ->setParameter('ref', $ref)
            ->orderBy('sp.date', 'DESC')
            ->addOrderBy('sp.posteType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalsByDate(\DateTime $date): array
    {
        return $this->createQueryBuilder('sp')
            ->select('sp.posteType, SUM(sp.realiseParPoste) as totalRealise, SUM(sp.targetParPoste) as totalTarget, SUM(sp.objectifParPoste) as totalObjectif')
            ->andWhere('sp.date = :date')
            ->andWhere('sp.valid = true')
            ->andWhere('sp.deleted = false')
            ->setParameter('date', $date)
            ->groupBy('sp.posteType')
            ->getQuery()
            ->getResult();
    }

    public function getPerformanceByBras(Bras $bras, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('sp')
            ->select('sp.date, sp.posteType, sp.realiseParPoste, sp.targetParPoste, sp.objectifParPoste')
            ->andWhere('sp.bras = :bras')
            ->andWhere('sp.date BETWEEN :start AND :end')
            ->andWhere('sp.valid = true')
            ->andWhere('sp.deleted = false')
            ->setParameter('bras', $bras)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('sp.date', 'DESC')
            ->addOrderBy('sp.posteType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function changeValidite(ShiftProduction $shiftProduction)
    {
        if ($shiftProduction->isValid()) {
            $shiftProduction->setValid(false);
        } else {
            $shiftProduction->setValid(true);
        }
        $this->entityManager->persist($shiftProduction);
        $this->entityManager->flush();

        return $shiftProduction;
    }

    public function delete(ShiftProduction $shiftProduction)
    {
        $shiftProduction->setDeleted(true);
        $this->entityManager->persist($shiftProduction);
        $this->entityManager->flush();

        return $shiftProduction;
    }
}