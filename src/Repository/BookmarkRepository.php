<?php

namespace App\Repository;

use App\Entity\Bookmark;
use App\Entity\Folder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<Bookmark> */
class BookmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bookmark::class);
    }

    /** @return list<Bookmark> */
    public function findForLibrary(?Folder $folder = null, bool $uncategorized = false): array
    {
        $query = $this->createQueryBuilder('bookmark')
            ->leftJoin('bookmark.folder', 'folder')->addSelect('folder')
            ->orderBy('bookmark.createdAt', SortDirection::Descending)
            ->addOrderBy('bookmark.id', SortDirection::Descending);

        if (null !== $folder) {
            $query->andWhere('bookmark.folder = :folder')->setParameter('folder', $folder);
        } elseif ($uncategorized) {
            $query->andWhere('bookmark.folder IS NULL');
        }

        return $query->getQuery()->getResult();
    }

    /** @return array{total: int, uncategorized: int, folders: array<int, int>} */
    public function countsByFolder(): array
    {
        $rows = $this->createQueryBuilder('bookmark')
            ->select('IDENTITY(bookmark.folder) AS folder_id, COUNT(bookmark.id) AS amount')
            ->groupBy('bookmark.folder')
            ->getQuery()
            ->getArrayResult();

        $counts = ['total' => 0, 'uncategorized' => 0, 'folders' => []];

        foreach ($rows as $row) {
            $amount = (int) $row['amount'];
            $counts['total'] += $amount;

            if (null === $row['folder_id']) {
                $counts['uncategorized'] = $amount;
            } else {
                $counts['folders'][(int) $row['folder_id']] = $amount;
            }
        }

        return $counts;
    }
}
