<?php

namespace App\Repository;

use App\Entity\ProductionFile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProductionFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductionFile|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductionFile[]    findAll()
 * @method ProductionFile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductionFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, ProductionFile::class);
    }

    public function saveProductionFile($productionFile): ProductionFile
    {
        $this->entityManager->persist($productionFile);
        $this->entityManager->flush();

        return $productionFile;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findOneByFilename($filename): ?ProductionFile
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.filename = :filename')
            ->setParameter('filename', $filename)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.valid = true')
            ->andWhere('p.deleted = false')
            ->setParameter('user', $user)
            ->orderBy('p.importedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findValidFiles(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.valid = true')
            ->andWhere('p.deleted = false')
            ->orderBy('p.importedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function changeValidite(ProductionFile $productionFile)
    {
        if ($productionFile->isValid()) {
            $productionFile->setValid(false);
        } else {
            $productionFile->setValid(true);
        }
        $this->entityManager->persist($productionFile);
        $this->entityManager->flush();

        return $productionFile;
    }

    public function delete(ProductionFile $productionFile)
    {
        $productionFile->setDeleted(true);
        $this->entityManager->persist($productionFile);
        $this->entityManager->flush();

        return $productionFile;
    }
}
