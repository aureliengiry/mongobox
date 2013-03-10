<?php

namespace Mongobox\Bundle\TumblrBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * TumblrRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TumblrRepository extends EntityRepository
{
    /**
     * Function to build request in order to filter tumblrs
     *
     * @param $query
     * @param array $params
     * @param array $filters
     * @return mixed
     */
    private function _buildRequestByFilters( $query, $params = array(), $filters = array() ){

        if( isset($filters['tag']) && !empty($filters['tag']) ){
            $query
                ->innerJoin('t.tags', 'tt')
                ->andWhere("tt.system_name LIKE :tag")
            ;
            $params['tag'] = $filters['tag'];
        }

        $query->setParameters( $params );

        return $query;
    }

	public function findLast($groups, $maxResults = 0, $firstResult = 0, $filters = array() )
	{
        $params =  array(
            'groups' => $groups
        );

		$em = $this->getEntityManager();
		$qb = $em->createQueryBuilder();

		$qb->select('t')
            ->from('MongoboxTumblrBundle:Tumblr', 't')
            ->leftJoin('t.groups', 'g')
            ->where("g.id IN (:groups)")
            ->orderBy('t.date', 'DESC')
            ->groupBy('t.id_tumblr')
        ;

		if($maxResults != 0)
		{
			$qb
                ->setMaxResults($maxResults)
			    ->setFirstResult($firstResult)
            ;
		}

        if( !empty($filters) ){
            $qb = $this->_buildRequestByFilters( $qb, $params, $filters );
        }
        else{
            $qb->setParameters( $params );
        }


		$query = $qb->getQuery();

		return $query->getResult();
	}

	public function findOneByGroup($tumblrId, $groups )
	{
		$em = $this->getEntityManager();
		$qb = $em->createQueryBuilder();

		$qb->select('t')
		->from('MongoboxTumblrBundle:Tumblr', 't')
		->leftJoin('t.groups', 'g')
		->where("g.id IN (:groups)")
        ->andWhere('t.id_tumblr=:tumblrId')
		->groupBy('t.id_tumblr')
        ->setMaxResults(1)
		->setParameters( array(
				'groups' => $groups ,
				'tumblrId' => $tumblrId
		));

		$query = $qb->getQuery();

        try{
            $result = $query->getSingleResult();
            return $result;
        }
        catch (\Doctrine\ORM\NoResultException $e){
            return false;
        }
	}

    /**
     * Function to get next tumblr
     *
     * @param string|int $tumblrId
     * @return boolean|array
     */
    public function getNextEntity( $tumblrId, $groups )
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb->select('t.id_tumblr,t.text')
            ->from('MongoboxTumblrBundle:Tumblr', 't')
            ->leftJoin('t.groups', 'g')
            ->where("g.id IN (:groups)")
            ->andWhere('t.id_tumblr > :tumblrId')
            ->orderBy('t.id_tumblr', 'ASC')
            ->groupBy('t.id_tumblr')
            ->setParameters( array(
                'groups' => $groups ,
                'tumblrId' => $tumblrId
            ))
            ->setMaxResults(1)
        ;

        $query = $qb->getQuery();

        try{
            $result = $query->getSingleResult();
            return $result;
        }
        catch (\Doctrine\ORM\NoResultException $e){
            return false;
        }

    }

    /**
     * Function to get prev tumblr
     *
     * @param string|int $tumblrId
     * @return boolean|array
     */
    public function getPrevEntity( $tumblrId, $groups )
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb->select('t.id_tumblr,t.text')
            ->from('MongoboxTumblrBundle:Tumblr', 't')
            ->leftJoin('t.groups', 'g')
            ->where("g.id IN (:groups)")
            ->andWhere('t.id_tumblr < :tumblrId')
            ->orderBy('t.id_tumblr', 'DESC')
            ->groupBy('t.id_tumblr')
            ->setParameters( array(
                'groups' => $groups ,
                'tumblrId' => $tumblrId
            ))
            ->setMaxResults(1)
        ;

        $query = $qb->getQuery();

        try {
            $result = $query->getSingleResult();
            return $result;
        }
        catch (\Doctrine\ORM\NoResultException $e) {
            return false;
        }

    }
}
