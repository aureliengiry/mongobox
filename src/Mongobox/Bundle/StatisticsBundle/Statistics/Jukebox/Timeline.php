<?php

namespace Mongobox\Bundle\StatisticsBundle\Statistics\Jukebox;

use Mongobox\Bundle\JukeboxBundle\Entity\Repository\VideosRepository;

class Timeline
{
    /**
     * @var \Mongobox\Bundle\JukeboxBundle\Entity\Repository\VideosRepository
     */
    protected $_repository;

    /**
     * Constructor
     *
     * @param \Mongobox\Bundle\JukeboxBundle\Entity\Repository\VideosRepository $repository
     */
    public function __construct(VideosRepository $repository)
    {
        $this->_repository = $repository;
    }

    /**
     * Retrieve series data
     *
     * @return array|bool
     */
    public function getSeries()
    {
        $results = $this->_repository->findAll();
        if (!$nbResults = count($results)) {
            return false;
        }

        foreach ($results as $_item) {
            if (!isset($series)) {
                $series = $this->initializeTimeline($_item->getDate());
            }

            $date = $_item->getDate()->format('d/m/Y');
            if (isset($series['data'][$date])) {
                $series['data'][$date]++;
            }
        }

        $series['data'] = array_values($series['data']);

        return $series;
    }

    /**
     * Initialize series data
     *
     * @param \DateTime $startDate
     * @return array
     */
    protected function initializeTimeline(\DateTime $startDate)
    {
        $pointStart     = new \DateTime($startDate->format('Y-m-d'));
        $currentDate    = new \DateTime();
        $nbDays         = $currentDate->diff($startDate)->days;

        $defaultValues = array();
        for ($i = 0; $i <= $nbDays; $i++) {
            $defaultValues[$startDate->format('d/m/Y')] = 0;
            $startDate->modify('1 day');
        }

        return array(
            'pointInterval' => 24 * 3600 * 1000,
            'pointStart'    => $pointStart,
            'data'          => $defaultValues
        );
    }
}
