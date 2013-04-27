<?php

namespace Mongobox\Bundle\StatisticsBundle\Entity\Repository\User;

use Doctrine\ORM\EntityRepository;
use Mongobox\Bundle\StatisticsBundle\Entity\User\Connection;

/**
 * ConnectionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ConnectionRepository extends EntityRepository
{
    /**
     * Retrieve the connections peak for the given date
     *
     * @param \DateTime $date
     * @return array
     */
    public function getPeakByDate(\DateTime $date)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('connections.number')
            ->from('MongoboxStatisticsBundle:User\Connection', 'connections')
            ->where('connections.date = :date')
            ->setParameters(array(
                'date' => $date->format('Y-m-d')
            ))
            ->getQuery()
        ;

        $query = $qb->getQuery();

        try {
            return $query->getSingleScalarResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            return 0;
        }
    }

    /**
     * Update the connections peak for the given date
     *
     * @param \DateTime $date
     * @param integer $currentPeak
     * @param integer $newPeak
     * @return void
     */
    public function updatePeak(\DateTime $date, $currentPeak, $newPeak)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        if ($currentPeak === 0) {
            $connections = new Connection();
            $connections->setDate($date);
            $connections->setTime($date);
            $connections->setNumber($newPeak);

            $em->persist($connections);
            $em->flush();
        } else {
            $qb
                ->update('MongoboxStatisticsBundle:User\Connection', 'connections')
                ->set('connections.time', $qb->expr()->literal($date->format('H:i:s')))
                ->set('connections.number', $qb->expr()->literal($newPeak))
                ->where('connections.date = :date')
                ->setParameters(array(
                    'date' => $date->format('Y-m-d')
                ))
            ;

            $query = $qb->getQuery();
            $query->execute();
        }
    }

    /**
     * Retrieve the biggest connections peak
     *
     * @return \Mongobox\Bundle\StatisticsBundle\Entity\User\Connection
     */
    public function getMaximumPeak()
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('connections')
            ->from('MongoboxStatisticsBundle:User\Connection', 'connections')
            ->orderBy('connections.number', 'DESC')
            ->setMaxResults(1)
        ;

        $query = $qb->getQuery();

        return $query->getSingleResult();
    }
}
