<?php

namespace Jcc\Bundle\AlbumBundle\Controller;

use DirectoryIterator;
use DateTime;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Jcc\Bundle\AlbumBundle\Entity\Album;
use Jcc\Bundle\AlbumBundle\Entity\Picture;
use Jcc\Bundle\AlbumBundle\Entity;
use Jcc\Bundle\AlbumBundle\Form;
/**
 * @todo ACL
 * @todo folder browsing (list of folders and navigation + diaporama)
 *    - make images album / folder independent
 *    - create a folder entity with last update date
 *    - folder_picture relation
 * @todo allow from a folder to create an album (name and derived slug _ picture relations)
 *
 * @Route("/manager")
 */
class AdminController extends Controller
{
    /**
     * Based on a root path, show subfolders as actual folders (image containers)
     *
     * @Route("/", name = "admin_index")
     * @Template()
     */
    public function indexAction()
    {
        return array('title' => 'Picture manager');
    }

    /**
     * @Route("/folder/{path}", name = "admin_folder", requirements={"path" = ".*"})
     * @Template()
     */
    public function folderAction($path)
    {
        /**
         * @var $folderService \Jcc\Bundle\AlbumBundle\Service\Folder
         */
        $folderService = $this->get('jcc_album.folder_service');

        $folder = $folderService->findOneFolderByPath($path);
        if (!$folder) {
           throw $this->createNotFoundException();
        }

        $pictures = $folderService->crawlFolderPictures($folder);

        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository('JccAlbumBundle:Tag');

        $tags = $repository->findByGlobal(1);

        return array(
            'folder' => $folder,
            'pictures' => $pictures,
            'tags' => $tags
        );
    }

    /**
     * @Template()
     */
    public function sidebarAction($path = null)
    {
        $container = $this->get('service_container');
        $root = $container->getParameter('album_root');

        $absFolders = $folders = array();

        $folders[] = array(
            'path' => $p = '',
            'name' => 'All pictures',
            'depth' => 0,
        );

        if ($path && (false === strpos($path, '..')) && is_dir("$root/$path")) {
            foreach (explode('/', $path) as $i => $level) {
                $folders[] = array(
                    'path' => $p .= $level,
                    'name' => $level,
                    'depth' => $i + 1
                );
                $p .= '/';
            }
        } else {
            $path = '';
        }

        if (is_dir($root . '/' . $path)) {
            exec(sprintf("find '%s' -mindepth '1' -maxdepth '1' -type d -name '*'", escapeshellarg($root . '/' . $path)),
                 $absFolders);
        }

        sort($absFolders);

        foreach ($absFolders as $folder) {
            if (strpos(basename($folder), '.') === 0) {
                continue;
            }
            $folders[] = array(
                'path' => $short = ltrim(str_replace($root, '', $folder), '/'),
                'name' => basename($folder),
                'depth' => count(explode('/', $short))
            );
        }

        return array('folders' => $folders);
    }

    /**
     * @Route("/tags", name = "admin_tags")
     * @Template()
     *
     * @return array
     */
    public function tagListAction()
    {
        $em = $this->getDoctrine()->getManager();
        $tagRepository = $this->getDoctrine()->getRepository('JccAlbumBundle:Tag');

        return array(
            'tags' => $tagRepository->findAll()
        );
    }

    /**
     * @Route("/tag/{id}", name = "admin_tag_edit", defaults = {"id": ""})
     * @Template()
     *
     * @return array
     */
    public function tagEditAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $tagRepository = $this->getDoctrine()->getRepository('JccAlbumBundle:Tag');

        if ($id > 0) {
            $picRepo = $this->getDoctrine()->getRepository('JccAlbumBundle:Picture');
            $tag = $tagRepository->find($id);
            if (empty($tag)) {
                throw $this->createNotFoundException('Tag not found');
            }

            $pictures = $picRepo->findByTag($tag, array('originalDate', 'ASC'));

        } else {
            $tag = new Entity\Tag();
            $pictures = array();
        }

        $form = $this->createForm(new Form\TagType(), $tag);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $em->persist($tag);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_tag_edit', array('id' => $tag->getId())));
        }

        $tags = $tagRepository->findByGlobal(true);
        if (!$tag->getGlobal()) {
            $tags[] = $tag;
        }

        return array(
            'tag' => $tag,
            'pictures' => $pictures,
            'tags' => $tags,
            'form' => $form->createView()
        );
    }

    /**
     * Add a tag to a picture
     *
     * @Route("/tag/{tag}/picture/{pic}/state/{state}", name = "admin_tag_picture")
     */
    public function tagPictureAction($tag, $pic, $state)
    {
        $em = $this->getDoctrine()->getManager();
        $tagRepository = $this->getDoctrine()->getRepository('JccAlbumBundle:Tag');
        $pictureRepository = $this->getDoctrine()->getRepository('JccAlbumBundle:Picture');
        $tagObject = $tagRepository->findOneBy(array('hash' => $tag));
        $picture = $pictureRepository->findOneBy(array('hash' => $pic));

        if ($tagObject && $picture) {
            if ($state && !in_array($tag, $picture->getTagHashes())) {
                $tagObject->getPictures()->add($picture);
            } else if(!$state && in_array($tag, $picture->getTagHashes())) {
                $tagObject->getPictures()->removeElement($picture);
            }

            $em->flush();

            return new Response($state);
        }

        throw $this->createNotFoundException("Could not find tag [$tag] or pictire [$pic]");
    }

    /**
     * @Route("/pic/{id}/remove", name = "admin_picture_remove")
     */
    public function removePictureAction($id)
    {
        return new \Symfony\Component\HttpFoundation\Response('OK');
    }

}
