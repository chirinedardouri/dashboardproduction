<?php

namespace App\Repository;

use App\Entity\Bras;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Bras|null find($id, $lockMode = null, $lockVersion = null)
 * @method Bras|null findOneBy(array $criteria, array $orderBy = null)
 * @method Bras[]    findAll()
 * @method Bras[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BrasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Bras::class);
    }

    public function saveBras($bras): Bras
    {
        $this->entityManager->persist($bras);
        $this->entityManager->flush();

        return $bras;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findOneByNom($nom): ?Bras
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.nom = :nom')
            ->andWhere('b.valid = true')
            ->andWhere('b.deleted = false')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOrCreateByNom($nom): Bras
    {
        $bras = $this->findOneByNom($nom);
        
        if (!$bras) {
            $bras = new Bras();
            $bras->setNom($nom);
            $this->saveBras($bras);
        }

        return $bras;
    }

    public function findValidBras(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.valid = true')
            ->andWhere('b.deleted = false')
            ->orderBy('b.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function changeValidite(Bras $bras)
    {
        if ($bras->isValid()) {
            $bras->setValid(false);
        } else {
            $bras->setValid(true);
        }
        $this->entityManager->persist($bras);
        $this->entityManager->flush();

        return $bras;
    }

    public function delete(Bras $bras)
    {
        $bras->setDeleted(true);
        $this->entityManager->persist($bras);
        $this->entityManager->flush();

        return $bras;
    }
}
