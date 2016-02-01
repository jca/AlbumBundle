<?php
namespace Jcc\Bundle\AlbumBundle\Service;

use Doctrine\ORM\EntityRepository;

class Picture extends EntityRepository
{
    public function findByTag($tag, array $sort = array())
    {
        return $this->createQueryBuilder('p')
                ->select('p')
                ->innerJoin('p.tags', 't')
                ->where('t.id = :tag')
                ->orderBy('p.originalDate', $tag->getSort())
                ->getQuery()
                ->setParameter(':tag', $tag->getId())
            ->getResult();
    }
}
