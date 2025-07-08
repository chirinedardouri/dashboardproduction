<?php

namespace App\Repository;

use App\Entity\Poste;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Poste|null find($id, $lockMode = null, $lockVersion = null)
 * @method Poste|null findOneBy(array $criteria, array $orderBy = null)
 * @method Poste[]    findAll()
 * @method Poste[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PosteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Poste::class);
    }

    public function savePoste($poste): Poste
    {
        $this->entityManager->persist($poste);
        $this->entityManager->flush();

        return $poste;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findOneByNom($nom): ?Poste
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.nom = :nom')
            ->andWhere('p.valid = true')
            ->andWhere('p.deleted = false')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOrCreateByNom($nom): Poste
    {
        $poste = $this->findOneByNom($nom);
        
        if (!$poste) {
            $poste = new Poste();
            $poste->setNom($nom);
            $this->savePoste($poste);
        }

        return $poste;
    }

    public function findValidPostes(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.valid = true')
            ->andWhere('p.deleted = false')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function changeValidite(Poste $poste)
    {
        if ($poste->isValid()) {
            $poste->setValid(false);
        } else {
            $poste->setValid(true);
        }
        $this->entityManager->persist($poste);
        $this->entityManager->flush();

        return $poste;
    }

    public function delete(Poste $poste)
    {
        $poste->setDeleted(true);
        $this->entityManager->persist($poste);
        $this->entityManager->flush();

        return $poste;
    }
}
