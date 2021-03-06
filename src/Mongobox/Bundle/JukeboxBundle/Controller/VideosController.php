<?php

namespace Mongobox\Bundle\JukeboxBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

use Mongobox\Bundle\JukeboxBundle\Entity\Videos;
use Mongobox\Bundle\JukeboxBundle\Entity\VideoTag;
use Mongobox\Bundle\JukeboxBundle\Entity\VideoGroup;
use Mongobox\Bundle\JukeboxBundle\Entity\Playlist;

// Forms
use Mongobox\Bundle\JukeboxBundle\Form\Type\VideosType;
use Mongobox\Bundle\JukeboxBundle\Form\Type\VideoType;
use Mongobox\Bundle\JukeboxBundle\Form\Type\VideoSearchType;
use Mongobox\Bundle\JukeboxBundle\Form\Type\VideoInfoType;
use Mongobox\Bundle\JukeboxBundle\Form\Type\SearchVideosType;
use Mongobox\Bundle\JukeboxBundle\Form\Type\VideoTagsType;

// Google API
use Google_Client;
use Google_Service_YouTube;

/**
 * Videos controller.
 *
 * @Route("/videos")
 */
class VideosController extends Controller
{
    protected $_limitPagination = 30;

    /**
     * Block search videos
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function searchFormAction()
    {
        $formSearchVideos = $this->createForm(new SearchVideosType());

        return $this->render(
            'MongoboxJukeboxBundle:Videos/Blocs:searchVideos.html.twig',
            array('form' => $formSearchVideos->createView())
        );
    }

    /**
     * Search video entities.
     *
     * @Route("/search", name="search_videos")
     */
    public function searchAction(Request $request)
    {
        $searchQuery = $request->get('query');

        $response = $this->forward('MongoboxJukeboxBundle:Videos:index', array('page' => 1, 'query' => $searchQuery));

        return $response;
    }

    /**
     * Lists all Videos entities.
     *
     * @Route("/{page}", name="videos", requirements={"page" = "\d+"}, defaults={"page" = 1})
     * @Template()
     */
    public function indexAction(Request $request, $page, $query = null)
    {
        $em = $this->getDoctrine()->getManager();
        $group = $em->getRepository('MongoboxGroupBundle:Group')->find($request->getSession()->get('id_group'));
        $videosRepository = $em->getRepository('MongoboxJukeboxBundle:Videos');
        $videoGroupRepository = $em->getRepository('MongoboxJukeboxBundle:VideoGroup');

        $criteria = array();
        if (!empty($query)) {
            $criteria = array('title' => $query);
        }

        // filtre par defaut
        $filters = array('sortBy' => 'vg.lastBroadcast', 'orderBy' => 'desc');

        // $_GET parameters
        $sortBy = $request->query->get('sortBy');
        $orderBy = $request->query->get('orderBy');

        if (!empty($sortBy) && !empty($orderBy)) {
            $filters = array(
                'sortBy'  => $sortBy,
                'orderBy' => $orderBy
            );
        }

        $entities = $videosRepository->search(
            $group,
            $criteria,
            $page,
            $this->_limitPagination,
            $filters
        );

        $nbPages = 0;
        if (empty($criteria)) {
            $groupVideos = $videoGroupRepository->findBy(array('group' => $group->getId()));
            $nbPages = (int) (count($groupVideos) / $this->_limitPagination);
        }

        $displayFilters = $filters;
        ('DESC' === $displayFilters['orderBy']) ? $displayFilters['orderBy'] = 'ASC' :
            $displayFilters['orderBy'] = 'DESC';

        return array(
            'entities'   => $entities,
            'pagination' => array(
                'page'        => $page,
                'page_total'  => $nbPages,
                'page_gauche' => ($page - 1 > 0) ? $page - 1 : 1,
                'page_droite' => ($page + 1 < $nbPages) ? $page + 1 : $nbPages,
                'limite'      => $this->_limitPagination
            ),
            'filters'    => $filters,
            'query'      => $query
        );
    }

    /**
     * Add the video to the playlist entity.
     *
     * @Route("/add-video-playlist/{id}", name="videos_add_to_playlist_vg")
     * @ParamConverter("video", class="MongoboxJukeboxBundle:VideoGroup")
     */
    public function addToPlaylistVGAction(Request $request, VideoGroup $video)
    {
        $em = $this->getDoctrine()->getManager();
        $session = $request->getSession();
        $group = $em->getRepository('MongoboxGroupBundle:Group')->find($session->get('id_group'));

        $itemPlaylist = $em->getRepository('MongoboxJukeboxBundle:Playlist')->findOneBy(
            array(
                'group'       => $group,
                'video_group' => $video,
                'random'      => 0
            )
        );

        if (!empty($itemPlaylist)) {
            $this->get('session')->getFlashBag()->add('erreur', 'Cette vidéo a déjà été ajoutée à la playlist');
        } else {
            $playlist_add = new Playlist();
            $playlist_add->setVideoGroup($video);
            $playlist_add->setGroup($group);
            $playlist_add->setRandom(0);
            $playlist_add->setCurrent(0);
            $playlist_add->setDate(new \Datetime());

            $em->persist($playlist_add);
            $em->flush();

            $this->get('session')->getFlashBag()->add('success', 'Vidéo ajoutée à la playlist');
        }


        return $this->redirect($this->generateUrl('videos'));
    }

    /**
     * Add the video to the playlist entity.
     *
     * @Route("/{id}/add_to_playlist", name="videos_add_to_playlist")
     * @ParamConverter("video", class="MongoboxJukeboxBundle:Videos")
     */
    public function addToPlaylistAction(Request $request, Videos $video)
    {
        $em = $this->getDoctrine()->getManager();
        $session = $request->getSession();
        $is_added = false;
        $group = $em->getRepository('MongoboxGroupBundle:Group')->find($session->get('id_group'));
        $videoGroup = $em->getRepository('MongoboxJukeboxBundle:VideoGroup')->findOneBy(
            array('group' => $group, 'video' => $video)
        );
        if (is_object($videoGroup)) {
            $playlist_add = new Playlist();
            $playlist_add->setVideoGroup($videoGroup);
            $playlist_add->setGroup($group);
            $playlist_add->setRandom(0);
            $playlist_add->setCurrent(0);
            $playlist_add->setDate(new \Datetime());

            $em->persist($playlist_add);
            $em->flush();
            $is_added = true;
        }

        $retour = array(
            'success' => $is_added,
            'message' => ($is_added) ? "Vidéo ajoutée à la playlist" : "Echec lors de l'ajout",
        );

        return new Response(json_encode($retour));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm();
    }

    /**
     * Action to search tags for autocomplete field
     *
     * @Route("/video-tags-ajax-autocomplete", name="video_tags_ajax_autocomplete")
     * @Template()
     */
    public function videoAjaxAutocompleteTagsAction(Request $request)
    {
        // récupération du mots clés en ajax selon la présélection du mot
        $value = $request->get('term');
        $em = $this->getDoctrine()->getManager();
        $videoTagsRepository = $em->getRepository('MongoboxJukeboxBundle:VideoTag');
        $motscles = $videoTagsRepository->getTags($value);

        return new Response(json_encode($motscles));
    }

    /**
     * Action to search tags for autocomplete field
     *
     * @Route("/video-ajax-get-tag/{id_tag}", name="video_tags_get_tag")
     * @Template()
     */
    public function getTagAction($id_tag)
    {
        $em = $this->getDoctrine()->getManager();
        $tag = $em->getRepository('MongoboxTumblrBundle:TumblrTag')->find($id_tag);

        return new Response($tag->getName());
    }

    /**
     * Action to load tag or create it if not exist
     *
     * @Route("/video-tags-load-item", name="video_tags_load_item")
     * @Template()
     */
    public function ajaxLoadTagAction(Request $request)
    {
        // récupération du mots clés en ajax selon la présélection du mot
        $value = $request->get('tag');


        $em = $this->getDoctrine()->getManager();
        $videoTagsRepository = $em->getRepository('MongoboxJukeboxBundle:VideoTag');

        // Check if tag Already exist
        $resultTag = $videoTagsRepository->loadOneTagByName($value);
        if (false === $resultTag) {

            // Create a new tag
            $newEntityTag = new VideoTag();
            $newEntityTag
                ->setName($value)
                ->setSystemName($value);
            $em->persist($newEntityTag);
            $em->flush();

            // Parsing result
            $resultTag = array(
                'id'   => $newEntityTag->getId(),
                'name' => $newEntityTag->getName()
            );
        }

        return new Response(json_encode($resultTag));
    }

    /**
     * @Route( "/post_video", name="post_video")
     */
    public function postVideoAction(Request $request)
    {
        $youtubeService = $this->get('mongobox_jukebox.api_youtube');

        $em = $this->getDoctrine()->getManager();
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $session = $request->getSession();
        $group = $em->getRepository('MongoboxGroupBundle:Group')->find($session->get('id_group'));

        $video = new Videos();
        $form_video = $this->createForm(new VideoType(), $video);
        $form_search = $this->createForm(new VideoSearchType(), $video);

        if ('POST' === $request->getMethod()) {
            $form_video->submit($request);
            if ($form_video->isValid()) {
                $video->setLien(Videos::parseUrlDetail($video->getLien()));

                // Check if video already exist
                $video_new = $em->getRepository('MongoboxJukeboxBundle:Videos')->findOneby(
                    array('lien' => $video->getLien())
                );
                if (!is_object($video_new)) {
                    $dataYt = $youtubeService->getYoutubeApi()->videos->listVideos(
                        "id,snippet,status,contentDetails",
                        array('id' => $video->getLien())
                    );

                    foreach ($dataYt->getItems() as $youtubeVideo) {
                        $snippet = $youtubeVideo->getSnippet();

                        // Duration
                        $duration = $youtubeVideo->getContentDetails()->getDuration();
                        $interval = new \DateInterval($duration);
                        $duration = $this->toSeconds($interval);

                        // Thumbnails
                        $thumbnails = $snippet->getThumbnails();

                        $video
                            ->setDate(new \Datetime())
                            ->setTitle($snippet->getTitle())
                            ->setDuration($duration)
                            ->setThumbnail($thumbnails->getDefault()->getUrl())
                            ->setThumbnailHq($thumbnails->getHigh()->getUrl());

                        $artist = $request->request->get('artist');
                        $songName = $request->request->get('songName');
                        if (empty($artist) && empty($songName)) {
                            $infos = $video->guessVideoInfos();
                            $artist = $infos['artist'];
                            $songName = $infos['songName'];
                        }

                        $video
                            ->setArtist($artist)
                            ->setSongName($songName);

                        $em->persist($video);


                        $video_new = $video;

                        $this->get('session')->getFlashBag()->add(
                            'success',
                            'Vidéo "' . $snippet->getTitle() . '" postée avec succès'
                        );
                    }
                }

                // Check if video already exist in this group
                $video_group = $em->getRepository('MongoboxJukeboxBundle:VideoGroup')->findOneby(
                    array('video' => $video_new, 'group' => $group)
                );
                if (!is_object($video_group)) {
                    $video_group = new VideoGroup();
                    $video_group->setVideo($video_new)
                        ->setGroup($group)
                        ->setUser($user)
                        ->setDiffusion(0)
                        ->setVolume(50)
                        ->setVotes(0);
                    $em->persist($video_group);
                }

                // Add video into playlist
                $playlist_add = new Playlist();
                $playlist_add->setVideoGroup($video_group)
                    ->setGroup($group)
                    ->setDate(new \Datetime())
                    ->setRandom(0)
                    ->setCurrent(0);
                $em->persist($playlist_add);

                $em->flush();

                $form_video_info = $this->createForm(new VideoInfoType(), $video_new);

                // Get video tags
                $list_tags = $em->getRepository('MongoboxJukeboxBundle:VideoTag')->getVideoTags($video_new);

                $content = $this->render(
                    'MongoboxJukeboxBundle:Partial:edit-modal.html.twig',
                    array(
                        'form_video_info' => $form_video_info->createView(),
                        'video'           => $video_new,
                        'list_tags'       => $list_tags
                    )
                )->getContent();
                $title = 'Informations de la vidéo : ' . $video_new->getName();

                $return = array(
                    'content' => $content,
                    'title'   => $title
                );

                return new Response(json_encode($return));
            }
        }

        $content = $this->render(
            "MongoboxCoreBundle:Wall/Blocs:postVideo.html.twig",
            array(
                'form_video'  => $form_video->createView(),
                'form_search' => $form_search->createView()
            )
        )->getContent();
        $title = 'Ajout d\'une vidéo';

        $return = array(
            'content' => $content,
            'title'   => $title
        );

        return new Response(json_encode($return));
    }

    /**
     * Action to edit a video from a modal
     *
     * @Route("/edit_modal/{id_video}", name="video_edit_modal")
     * @ParamConverter("video", class="MongoboxJukeboxBundle:Videos", options={"id" = "id_video"})
     */
    public function editVideoModalAction(Request $request, Videos $video)
    {
        $em = $this->getDoctrine()->getManager();

        $editForm = $this->createForm(new VideoInfoType(), $video);
        // Process the form on POST
        if ($request->isMethod('POST')) {
            $editForm->submit($request);
            if ($editForm->isValid()) {
                //On supprime les anciens tags de la vidéo
                $em->getRepository('MongoboxJukeboxBundle:Videos')->wipeTags($video);

                //On rajoute les tags
                $tags = $editForm->get('tags')->getData();
                if (is_array($tags)) {
                    foreach ($tags as $tag_id) {
                        $entityTag = $em->getRepository('MongoboxJukeboxBundle:VideoTag')->find($tag_id);
                        $entityTag->getVideos()->add($video);
                    }
                }
                $em->flush();

                $content = 'Modification enregistrée avec succès';
                $title = '';

                $return = array(
                    'content' => $content,
                    'title'   => $title
                );

                return new Response(json_encode($return));
            };
        };

        $list_tags = $em->getRepository('MongoboxJukeboxBundle:VideoTag')->getVideoTags($video);

        $content = $this->render(
            'MongoboxJukeboxBundle:Partial:edit-modal.html.twig',
            array(
                'form_video_info' => $editForm->createView(),
                'video'           => $video,
                'list_tags'       => $list_tags
            )
        )->getContent();
        $title = 'Edition de la vidéo : ' . $video->getName();

        $return = array(
            'content' => $content,
            'title'   => $title
        );

        return new Response(json_encode($return));
    }

    /**
     * Action to search tags for autocomplete field
     *
     * @Route("/tags-ajax-autocomplete", name="video_tags_ajax_autocomplete")
     */
    public function ajaxAutocompleteTagsAction(Request $request)
    {
        // récupération du mots clés en ajax selon la présélection du mot
        $value = $request->get('term');
        $em = $this->getDoctrine()->getManager();
        $videoTagsRepository = $em->getRepository('MongoboxJukeboxBundle:VideoTag');
        $motscles = $videoTagsRepository->getTags($value);

        return new Response(json_encode($motscles));
    }

    /**
     * Action to search video from mongobox or youtube
     *
     * @Route("/ajax/search/keyword", name="ajax_search_keyword")
     */
    public function ajaxSearchKeywordAction(Request $request)
    {
        $youtubeService = $this->get('mongobox_jukebox.api_youtube');

        $em = $this->getDoctrine()->getManager();
        $session = $request->getSession();
        $group = $em->getRepository('MongoboxGroupBundle:Group')->find($session->get('id_group'));
        $video = new Videos();
        $form_search = $this->createForm(new VideoSearchType(), $video);
        $youtube_video = array();
        $mongobox_video = array();

        if ('POST' === $request->getMethod()) {
            $form_search->submit($request);
            $keyword = $form_search->get('search')->getData();

            $response = $youtubeService->getYoutubeApi()->search->listSearch(
                "id,snippet",
                array('q' => $keyword, 'maxResults' => 10)
            );
            foreach ($response->getItems() as $video) {
                $snippet = $video->getSnippet();
                $url = "https://www.youtube.com/watch?v={$video->getId()->getVideoId()}";
                $youtube_video[] = array('title' => $snippet->getTitle(), 'url' => $url);
            }

            //Récupération des infos Mongobox
            $search = array('title' => $keyword);
            $mongobox_videos = $em->getRepository('MongoboxJukeboxBundle:Videos')->search($group, $search, 1, 10);
            foreach ($mongobox_videos as $mv) {
                $mongobox_video[] = array('title' => $mv->getVideo()->getName(), 'url' => $mv->getVideo()->getLien());
            }
        }

        return new Response(
            json_encode(
                array(
                    'youtube'  => $this->render(
                        'MongoboxJukeboxBundle:Partial:search-listing.html.twig',
                        array(
                            'video_listing' => $youtube_video,
                            'title'         => 'Youtube'
                        )
                    )->getContent(),
                    'mongobox' => $this->render(
                        'MongoboxJukeboxBundle:Partial:search-listing.html.twig',
                        array(
                            'video_listing' => $mongobox_video,
                            'title'         => 'Mongobox'
                        )
                    )->getContent()
                )
            )
        );
    }

    /**
     * Convert Date Interval into total seconds
     *
     * @param \DateInterval $delta
     *
     * @return int
     */
    private function toSeconds(\DateInterval $delta)
    {
        $seconds = ($delta->s)
            + ($delta->i * 60)
            + ($delta->h * 60 * 60)
            + ($delta->d * 60 * 60 * 24)
            + ($delta->m * 60 * 60 * 24 * 30)
            + ($delta->y * 60 * 60 * 24 * 365);

        return (int) $seconds;
    }

    /**
     * @Route( "/get_info_video", name="get_info_video")
     */
    public function getInfoVideoAction(Request $request)
    {
        $youtubeService = $this->get('mongobox_jukebox.api_youtube');
        $em = $this->getDoctrine()->getManager();

        $lien = Videos::parseUrlDetail($request->request->get('lien'));

        $video_new = $em->getRepository('MongoboxJukeboxBundle:Videos')->findOneby(array('lien' => $lien));
        //Si la vidéo existe déjà, on dit au JS que tu zappe tout, on la rajoute à la playlist
        if (is_object($video_new)) {
            $response = array('video' => $video_new->getId(), 'type' => 'old');
        } //Sinon, on va chercher les infos YT
        else {
            $dataYt = $youtubeService->getYoutubeApi()->videos->listVideos(
                "id,snippet,status,contentDetails",
                array('id' => $lien)
            );

            foreach ($dataYt->getItems() as $youtubeVideo) {
                $snippet = $youtubeVideo->getSnippet();

                $video = new Videos();
                $video
                    ->setLien($lien)
                    ->setTitle($snippet->getTitle());

                //On fait un bête split pour chopper artist et songName pour le moment
                $response = $video->guessVideoInfos();
            }

            $response['type'] = 'new';
        }

        return new Response(json_encode($response));
    }
}
