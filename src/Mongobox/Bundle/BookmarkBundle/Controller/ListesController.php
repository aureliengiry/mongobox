<?php

namespace Mongobox\Bundle\BookmarkBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Mongobox\Bundle\BookmarkBundle\Entity\ListeFavoris;
use Mongobox\Bundle\BookmarkBundle\Entity\UserFavoris;


class ListesController extends Controller
{
    const _limitation_listes = 5;

    /**
     * Fonction pour voir les listes de favoris de l'utilisateur
     * @Route("/profil/listes", name="user_voir_listes")
     * @Template()
     */
    public function voirListeFavorisAction()
    {
        $manager = $this->getDoctrine()->getManager();
        $user = $this->getUser();

		$nombre_favoris = $manager->getRepository('MongoboxBookmarkBundle:UserFavoris')->getBookmarkNumber($user);
		$nombre_listes = $manager->getRepository('MongoboxBookmarkBundle:UserFavoris')->getListsNumber($user);

        $listes = $user->getListesFavoris();

        return array(
            'nombre_favoris' => $nombre_favoris,
            'nombre_listes' => $nombre_listes,
            'listes' => $listes
        );
    }

    /**
     * Fonction permettant de récupérer via JSON la liste des listes de favoris de l'utilisateur
     * @Route("/ajax_list_search", name="ajax_list_search")
     */
    public function ajaxListSearchAction(Request $request)
    {
        $value = $request->get('term');
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();

		$lists = $em->getRepository('MongoboxBookmarkBundle:ListeFavoris')->findList($user, $value);

        $json = array();
        foreach ($lists as $list) {
            $json[] = array(
                'label' => $list->getName(),
                'value' => $list->getId()
            );
        }

        return new JsonResponse($json);
    }

	/**
	 * Fonction pour ajouter un favoris à une liste
	 * @Route("/ajax/favoris/{id_video}/add/liste", name="ajax_liste_favoris_add", requirements={"id_video" = "\d+"})
	 */
	public function addListToBookmarkAction($id_video)
	{
		$user = $this->getUser();
		$em = $this->getDoctrine()->getManager();
        $request = $this->get('request');
		$id_liste = $request->request->get('id_liste');
		$liste = $em->getRepository('MongoboxBookmarkBundle:ListeFavoris')->find($id_liste);
		$video = $em->find('MongoboxJukeboxBundle:Videos', $id_video);

		$alreadyExist = $em->getRepository('MongoboxBookmarkBundle:UserFavoris')->findOneBy(array(
			'user' => $user,
			'video' => $video,
			'liste' => $liste
		));

        $date = new \DateTime;
        $message = 'La vidéo existe déjà dans la liste "'.$liste->getName().'"';
        $result = false;

        if ( is_null($alreadyExist) ) {
            $new_fav_list = new UserFavoris();
            $new_fav_list
                ->setUser($user)
                ->setListe($liste)
                ->setVideo($video)
                ->setDateFavoris($date)
            ;
            $em->persist($new_fav_list);
            $em->flush();

            $message = 'Vidéo ajoutée avec succès dans la liste "'.$liste->getName().'"';
            $result = true;
        }

		$html = '';
		if( $result )
		{
			$paramList = $request->request->get('liste');
			if( !is_null($paramList) )
				$html = $this->renderView('MongoboxBookmarkBundle:Listes:unFavorisDansListeDetails.html.twig', array('video' => $video, 'date' => $date, 'list' => $liste));
			else
				$html = $this->renderView('MongoboxBookmarkBundle:Favoris/Listes:uneListeFavoris.html.twig', array('liste' => $liste, 'ajax' => true, 'date' => $date ,'video' => $video));
		}

        return new JsonResponse(array(
            "message" => $message,
            "result" => $result,
            "html" => $html
        ));
    }

    /**
     * Fonction pour créer une nouvelle liste en ajax
     * @Route("/ajax/create/list", name="add_new_list")
     */
    public function createNewListAction()
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $request = $this->get('request');
            $user = $this->getUser();
            $currentRoute = $request->request->get('routeName');
            $listName = $request->request->get('listName');

            $newList = new ListeFavoris();
            $newList
                ->setUser($user)
                ->setName($listName)
                ->setDate_creation(new \DateTime)
            ;

            $em->persist($newList);
            $em->flush();

            $json = array(
                'success' => true,
                'listName' => $listName,
                'currentRoute' => false
            );

			$json['listNumber'] = $em->getRepository('MongoboxBookmarkBundle:UserFavoris')->getListsNumber($user);
			if( $currentRoute === 'user_voir_listes' )
			{
				$json['currentRoute'] = true;
				$json['limitation'] = self::_limitation_listes;
				$json['html'] = $this->renderView('MongoboxBookmarkBundle:Listes:uneListe.html.twig', array('liste' => $newList));
			}
		} catch( \Exception $e)
		{
			$json = array('success' => false);
		}

        return new JsonResponse($json);
    }

	/**
	 * Fonction pour supprimer une liste de favoris
	 * @Route("/ajax/list/remove/{id_list}", name="remove_list_action", requirements={"id" = "\d+"})
	 */
	public function removeListAction($id_list)
	{
		$em = $this->getDoctrine()->getManager();
		$user = $this->getUser();
		$json = array();
		try
		{
			$json['success'] = true;
			$json['message'] = "Veuillez séléctionner une liste pour afficher les vidéos";
			$listToRemove = $em->getRepository('MongoboxBookmarkBundle:ListeFavoris')->find($id_list);
			$em->getRepository('MongoboxBookmarkBundle:UserFavoris')->removeBookmarkFromList($listToRemove, $user);
			$em->remove($listToRemove);
			$em->flush();
			$json['listNumber'] = $em->getRepository('MongoboxBookmarkBundle:UserFavoris')->getListsNumber($user);

        } catch ( \Exception $e ) {
            $json['success'] = false;
        }

        return new JsonResponse($json);
    }

	/**
	 * Fonction pour récupérer les détails d'une liste
	 * @Route("/ajax/list/details/{id_list}", name="details_list_action", requirements={"id_list" = "\d+"})
	 */
	public function getListDetailsAction($id_list)
	{
		$user = $this->getUser();
		$manager = $this->getDoctrine()->getManager();
		$json = array();
		try
		{
			$list = $manager->getRepository('MongoboxBookmarkBundle:ListeFavoris')->find($id_list);
			$videos = $manager->getRepository('MongoboxBookmarkBundle:ListeFavoris')->getBookmarkFromList($list, $user);
			$json['success'] = true;
			$json['html'] = $this->renderView('MongoboxBookmarkBundle:Listes:listeDetails.html.twig', array('list' => $list, 'bookmarks' => $videos));
		} catch( \Exception $e )
		{
			$json['success'] = false;
			$json['error'] = 'Le chargement de la liste a échoué';
		}

        return new JsonResponse($json);
    }

	/**
	 * Fonction pour supprimer une vidéo d'une liste de favoris
	 * @Route("/ajax/list/{id_list}/remove/bookmark/{id_video}", name="remove_bookmark_list_action", requirements={"id_list" = "\d+", "id_video" = "\d+"})
	 */
	public function removeVideoFromListAction($id_list, $id_video)
	{
		$user = $this->getUser();
		$manager = $this->getDoctrine()->getManager();
		$uf = $manager->getRepository('MongoboxBookmarkBundle:UserFavoris')->findOneBy(array(
			"user" => $user,
			"liste" => $manager->find("MongoboxBookmarkBundle:ListeFavoris", $id_list),
			"video" => $manager->find("MongoboxJukeboxBundle:Videos", $id_video)
		));

        $json = array(
            "success" => false,
            "message" => "Une erreur est survenue pendant la suppression de la vidéo"
        );
        if ($uf) {
            $manager->remove($uf);
            $manager->flush();
            $json["success"] = true;
            $json["message"] = "La vidéo a bien été supprimée de la liste";
        }

        return new JsonResponse($json);
    }

	/**
	 * Fonction pour mettre à jour le titre d'une liste
	 * @Route("/ajax/list/{id_list}/update/title", name="update_title_list_action", requirements={"id_list" = "\d+"})
	 */
	public function ajaxUpdateListNameAction($id_list)
	{
		$em = $this->getDoctrine()->getManager();
		$list = $em->find("MongoboxBookmarkBundle:ListeFavoris", $id_list);

        $request = $this->get('request');

        $json = array(
            "success" => false,
            "message" => "Une erreur est survenue lors de la mise à jour de la liste"
        );
        if ($list) {
            $newName = $request->request->get('name');
            $list->setName($newName);
            $em->flush();
            $json["success"] = true;
            $json["message"] = "La liste a bien été mise à jour";
            $json["newName"] = $newName;
        }

        return new JsonResponse($json);
    }
}
