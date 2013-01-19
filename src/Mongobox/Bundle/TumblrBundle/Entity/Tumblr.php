<?php

namespace Mongobox\Bundle\TumblrBundle\Entity;
use Doctrine\ORM\Mapping as ORM;
use \Doctrine\Common\Collections\ArrayCollection;

/**
 * Mongobox\Bundle\TumblrBundle\Entity\Tumblr
 *
 * @ORM\Entity(repositoryClass="Mongobox\Bundle\TumblrBundle\Entity\Repository\TumblrRepository")
 * @ORM\Table(name="tumblr")
 */
class Tumblr
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id_tumblr;

    /**
     * @ORM\Column(type="text")
     */
    protected $image;

    /**
     * @ORM\Column(type="text")
     */
    protected $text;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $date;

    /**
     * @ORM\OneToMany(targetEntity="TumblrVote", mappedBy="tumblr", cascade={"persist"})
     * @ORM\JoinColumn(name="id_tumblr", referencedColumnName="id_tumblr")
     */
    protected $tumblr_vote;

    /**
     * @ORM\ManyToMany(targetEntity="Mongobox\Bundle\GroupBundle\Entity\Group", mappedBy="tumblrs", cascade={"persist"})
     */
    protected $groups;

    /**
     * Constructor
     */
    public function __construct()
    {
		$this->groups = new ArrayCollection();
    }

	public function setId($id_tumblr)
    {
        $this->id_tumblr = $id_tumblr;

        return $this;
    }

    public function getId()
    {
        return $this->id_tumblr;
    }

    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    public function getText()
    {
        return $this->text;
    }

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getTumblrVote()
    {
        return $this->tumblr_vote;
    }

    public function getSomme()
    {
        $somme = 0;
        $liste_vote = $this->getTumblrVote();
        foreach($liste_vote as $vote) $somme += $vote->getSens();
        return $somme;
    }

    /**
     * Get id_tumblr
     *
     * @return integer 
     */
    public function getIdTumblr()
    {
        return $this->id_tumblr;
    }

    public function addGroup($group)
    {
    	$this->groups[] = $group;
    	return $this;
    }
    
    public function getGroups()
    {
    	return $this->groups;
    }
    
    public function setGroups($groups)
    {
    	$this->groups = $groups;
    	return $this;
    }
}