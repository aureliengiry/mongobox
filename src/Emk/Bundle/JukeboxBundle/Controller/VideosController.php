<?php

namespace Emk\Bundle\JukeboxBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Emk\Bundle\JukeboxBundle\Entity\Videos;
use Emk\Bundle\JukeboxBundle\Entity\Playlist;

// Forms
use Emk\Bundle\JukeboxBundle\Form\VideosType;
use Emk\Bundle\JukeboxBundle\Form\SearchVideosType;

/**
 * Videos controller.
 *
 * @Route("/videos")
 */
class VideosController extends Controller
{
    protected $_limitPagination = 50;

    /**
     * Lists all Videos entities.
     *
     * @Route("/{page}", name="videos",requirements={"page" = "\d+"}, defaults={"page" = 1})
     * @Template()
     */
    public function indexAction(Request $request, $page)
    {
        $em = $this->getDoctrine()->getManager();
        $videosRepository = $em->getRepository('EmkJukeboxBundle:Videos');

        $formSearchVideos = $this->createForm(new SearchVideosType());

        $criteria = array();
         if (  'POST' === $request->getMethod() ) {
             $formSearchVideos->bindRequest($request);
             $criteria = array('title' => $formSearchVideos->get('search')->getData());
         }

     	// filtre par defaut
        $filters = array('sortBy' => 'date', 'orderBy' => 'asc');
        
        // $_GET parameters
        $sortBy = $request->query->get('sortBy');
        $orderBy = $request->query->get('orderBy');
        
        if( !empty($sortBy) && !empty($orderBy) ){
        	$filters = array(
        		'sortBy' => $sortBy,
        		'orderBy' => $orderBy		
        	);
        }
        
        $entities = $videosRepository->search(
                $criteria,
                $page,
                $this->_limitPagination,
        		$filters
        );

        $nbPages = (int) (count($videosRepository->findAll())  / $this->_limitPagination);

        $displayFilters = $filters;
        ( 'DESC' === $displayFilters['orderBy'] ) ? $displayFilters['orderBy'] = 'ASC' : $displayFilters['orderBy'] = 'DESC';
        
        return array(
            'searchVideosForm' => $formSearchVideos->createView(),
            'entities' => $entities,
            'pagination' => array(
                'page' => $page,
                'page_total' => $nbPages,
                'page_gauche' => ( $page-1 > 0 ) ? $page-1 : 1,
                'page_droite' => ( $page+1 < $nbPages ) ? $page+1 : $nbPages,
                'limite' =>  $this->_limitPagination
            ),
        	'filters' => $filters
        );
    }

    /**
     * Finds and displays a Videos entity.
     *
     * @Route("/{id}/show", name="videos_show")
     * @Template()
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('EmkJukeboxBundle:Videos')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Videos entity.');
        }

        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity'      => $entity,
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Displays a form to create a new Videos entity.
     *
     * @Route("/new", name="videos_new")
     * @Template()
     */
    public function newAction()
    {
        $entity = new Videos();
        $form   = $this->createForm(new VideosType(), $entity);

        return array(
            'entity' => $entity,
            'form'   => $form->createView(),
        );
    }

    /**
     * Creates a new Videos entity.
     *
     * @Route("/create", name="videos_create")
     * @Method("POST")
     * @Template("EmkJukeboxBundle:Videos:new.html.twig")
     */
    public function createAction(Request $request)
    {
        $entity  = new Videos();
        $form = $this->createForm(new VideosType(), $entity);
        $form->bind($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('videos_show', array('id' => $entity->getId())));
        }

        return array(
            'entity' => $entity,
            'form'   => $form->createView(),
        );
    }

    /**
     * Displays a form to edit an existing Videos entity.
     *
     * @Route("/{id}/edit", name="videos_edit")
     * @Template()
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('EmkJukeboxBundle:Videos')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Videos entity.');
        }

        $editForm = $this->createForm(new VideosType(), $entity);
        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Edits an existing Videos entity.
     *
     * @Route("/{id}/update", name="videos_update")
     * @Method("POST")
     * @Template("EmkJukeboxBundle:Videos:edit.html.twig")
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('EmkJukeboxBundle:Videos')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Videos entity.');
        }

        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createForm(new VideosType(), $entity);
        $editForm->bind($request);

        if ($editForm->isValid()) {
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('videos_edit', array('id' => $id)));
        }

        return array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Deletes a Videos entity.
     *
     * @Route("/{id}/delete", name="videos_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request, $id)
    {
        $form = $this->createDeleteForm($id);
        $form->bind($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository('EmkJukeboxBundle:Videos')->find($id);

            if (!$entity) {
                throw $this->createNotFoundException('Unable to find Videos entity.');
            }

            $em->remove($entity);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('videos'));
    }

    /**
     * Add the video to the playlist entity.
     *
     * @Route("/{id}/add_to_playlist", name="videos_add_to_playlist")
     * @ParamConverter("video", class="EmkJukeboxBundle:Videos")
     */
    public function addToPlaylistAction(Videos $video)
    {
        $em = $this->getDoctrine()->getManager();

        $playlist_add = new Playlist();
        $playlist_add->setVideo($video);
        $playlist_add->setRandom(0);
        $playlist_add->setDate(new \Datetime());
        $em->persist($playlist_add);
        $em->flush();

        return $this->redirect($this->generateUrl('videos'));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm()
        ;
    }
}
