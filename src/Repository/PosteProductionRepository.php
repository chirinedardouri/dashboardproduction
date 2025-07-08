<?php

namespace App\Repository;

use App\Entity\PosteProduction;
use App\Entity\ProductionFile;
use App\Entity\Bras;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PosteProduction|null find($id, $lockMode = null, $lockVersion = null)
 * @method PosteProduction|null findOneBy(array $criteria, array $orderBy = null)
 * @method PosteProduction[]    findAll()
 * @method PosteProduction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PosteProductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, PosteProduction::class);
    }

    public function savePosteProduction($posteProduction): PosteProduction
    {
        $this->entityManager->persist($posteProduction);
        $this->entityManager->flush();

        return $posteProduction;
    }

    public function findByProductionFile(ProductionFile $productionFile): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.productionFile = :file')
            ->andWhere('pp.valid = true')
            ->andWhere('pp.deleted = false')
            ->setParameter('file', $productionFile)
            ->orderBy('pp.date', 'DESC')
            ->addOrderBy('pp.posteType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.date BETWEEN :start AND :end')
            ->andWhere('pp.valid = true')
            ->andWhere('pp.deleted = false')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('pp.date', 'DESC')
            ->addOrderBy('pp.posteType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByBrasAndDate(Bras $bras, \DateTime $date): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.bras = :bras')
            ->andWhere('pp.date = :date')
            ->andWhere('pp.valid = true')
            ->andWhere('pp.deleted = false')
            ->setParameter('bras', $bras)
            ->setParameter('date', $date)
            ->orderBy('pp.posteType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalsByDate(\DateTime $date): array
    {
        return $this->createQueryBuilder('pp')
            ->select('pp.posteType, SUM(pp.realisePoste) as totalRealise, SUM(pp.targetPoste) as totalTarget')
            ->andWhere('pp.date = :date')
            ->andWhere('pp.valid = true')
            ->andWhere('pp.deleted = false')
            ->setParameter('date', $date)
            ->groupBy('pp.posteType')
            ->getQuery()
            ->getResult();
    }

    public function changeValidite(PosteProduction $posteProduction)
    {
        if ($posteProduction->isValid()) {
            $posteProduction->setValid(false);
        } else {
            $posteProduction->setValid(true);
        }
        $this->entityManager->persist($posteProduction);
        $this->entityManager->flush();

        return $posteProduction;
    }

    public function delete(PosteProduction $posteProduction)
    {
        $posteProduction->setDeleted(true);
        $this->entityManager->persist($posteProduction);
        $this->entityManager->flush();

        return $posteProduction;
    }
}