<?php

namespace App\Repository;

use App\Entity\Folder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<Folder> */
class FolderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Folder::class);
    }

    /** @return list<Folder> */
    public function findForNavigation(): array
    {
        return $this->createQueryBuilder('folder')
            ->leftJoin('folder.parent', 'parent')->addSelect('parent')
            ->orderBy('folder.name', SortDirection::Ascending)
            ->addOrderBy('folder.id', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
