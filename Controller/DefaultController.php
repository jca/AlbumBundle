<?php

namespace Jcc\Bundle\AlbumBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;


/**
 * @todo b3 compatibility check (including /album/ rewrite)
 * @todo html caching
 */
class DefaultController extends Controller
{

    /**
     * @Route("/", name = "index")
     * @Template()
     */
    public function indexAction()
    {
        try {
            // Redirect admin and local users to secure area
            // TODO: Make the local network rule configurable (turned off by default)
            if (true === $this->get('security.context')->isGranted('ROLE_ADMIN') ||
                true === IpUtils::checkIp($this->getRequest()->getClientIp(), '192.168.0.0/16') ||
                $this->getRequest()->getClientIp() === '127.0.0.1') {
                return $this->redirect($this->generateUrl('admin_index'));
            }
        } catch (\Exception $e) {

        }
        return array();
    }

    /**
     * Resizes the original picture and redirect browser to itself     
     *
     * @Route("/pictures/{mode}/{width}/{height}/{hash}.jpg", name = "picture")
     */
    public function pictureAction($mode, $width, $height, $hash)
    {
        $logger = $this->get('logger');

        //Get the image
        $em = $this->getDoctrine()->getManager();
        $pictureRepo = $this->getDoctrine()->getRepository('JccAlbumBundle:Picture');
        $picture = $pictureRepo->findOneByHash($hash);

        if (!$picture) {
            throw $this->createNotFoundException();
        }
        //Create a link
        $container = $this->get('service_container');
        $root = $container->getParameter('album_root');
        $cache = $container->getParameter('cache_path');

        $source = $root . '/' . $picture->getFolder()->getPath() . '/' . $picture->getPath();
        $link = tempnam(sys_get_temp_dir(), 'slidesource');
        unlink($link);
        if (!file_exists($source)) {
            $em->remove($picture);
            $em->flush();

            throw $this->createNotFoundException('No such image at path '.$source);
        } else if(!symlink($source, $link)) {
            throw $this->createNotFoundException('Could not link ' . $link.' to ' . $source);
        }

        $cachepath = $cache . '/' . $mode . '/' . $width . '/' . $height . '/' . $hash . '.jpg';
        $url = $this->generateUrl('picture', compact('mode', 'width', 'height', 'hash'));

        if (file_exists($cachepath) && filemtime($cachepath) > filemtime($source)) {
            return $this->redirect($url);
        }

        //Resize
        $cachedir = dirname($cachepath);
        if (!is_dir($cachedir) && !mkdir($cachedir, 0777, true)) {
            throw $this->createNotFoundException('Picture cache directory is not writable!');
        }

        $new_width = $width;
        $new_height = $height;

        list($orig_width, $orig_height, $type, $attr) = getimagesize($source);

        $exif = @exif_read_data($source);
        $convert = '';
        if(!empty($exif['Orientation'])) {
            switch($exif['Orientation']) {
                case 8:
                    $convert = '-rotate 270';
                    $tmp = $orig_width;
                    $orig_width = $orig_height;
                    $orig_height = $tmp;
                    break;
                case 3:
                    $convert = '-rotate 180';
                    break;
                case 6:
                    $convert = '-rotate 90';
                    $tmp = $orig_width;
                    $orig_width = $orig_height;
                    $orig_height = $tmp;
                    break;
            }
        }

        # preserve aspect ratio, fitting image to specified box
        $quality = 90;
        if ($mode == "fit")
        {
                $new_height = $orig_height * $new_width / $orig_width;
                if ($new_height > $height)
                {
                        $new_width = $orig_width * $height / $orig_height;
                        $new_height = $height;
                }
        }
        # crop: resize to all image
        else if ($mode == "crop")
        {
                $new_height = $orig_height * $new_width / $orig_width;
                if ($new_height < $height)
                {
                        $new_width = $orig_width * $height / $orig_height;
                        $new_height = $height;
                }
        }

        $tmpfile = tempnam(sys_get_temp_dir(), 'slideresize');

        #step 1: Resize
        $cmd = sprintf('epeg "%s" -m %d -q %d "%s"', $link, max($new_height, $new_width), $quality, $tmpfile);
        exec($cmd, $output, $error);
        $logger->info('exec:' . $cmd . '; output:' . implode("\n", $output));

        if (!$error) {
            $logger->info('exec:' . $cmd . '; output:' . implode("\n", $output));

        } else {
            $logger->error('exec:' . $cmd . '; output:' . implode("\n", $output));

            #Fallback : use convert all the way (slower)
            $convert .= sprintf(' -resize %dx%d +repage', $new_width, $new_height);
            $tmpfile = $link;
        }

        if ($mode == "crop") {
            $convert .= sprintf(' -gravity center -crop %dx%d+0+0 +repage', $width, $height);
        }

        if ($convert) {
            $cmd = sprintf('convert "%s" -quality %d %s "%s"', $tmpfile, $quality, $convert, $cachepath);
            exec($cmd, $output2, $return);

            if ($return) {
                $logger->error("Executed: $cmd; Output: " . implode("\n", $output2));

                throw $this->createNotFoundException('Error while converting the input file!');
            } else {
                $logger->info("Executed: $cmd; Output: " . implode("\n", $output2));
            }

            unlink($tmpfile); //Where target is a tmp file
            if ($tmpfile != $link) {
                unlink($link);
            }
        } else {
            rename($tmpfile, $cachepath);
        }

        if (!file_exists($cachepath)) {
            throw $this->createNotFoundException('The cached file was not found');
        }

        //Redirect to self
        return $this->redirect($url);
    }

    /**
     * @Route("/albums/{hash}/{slug}.html", name="tag_view")
     * @Template()
     */
    public function viewAction($hash, $slug)
    {
        $tagRepo = $this->getDoctrine()->getRepository('JccAlbumBundle:Tag');
        $picRepo = $this->getDoctrine()->getRepository('JccAlbumBundle:Picture');
        $tag = $tagRepo->findOneByHash($hash);

        if (empty($tag)) {
            throw $this->createNotFoundException('Tag not found');
        }

        $pictures = $picRepo->findByTag($tag);

        return array(
            'tag' => $tag,
            'pictures' => $pictures
        );
    }

    /**
     * @Route("/download/{hash}.jpg", name="download")
     */
    public function downloadAction($hash)
    {
        $em = $this->getDoctrine()->getManager();
        $pictureRepo = $this->getDoctrine()->getRepository('JccAlbumBundle:Picture');
        $picture = $pictureRepo->findOneByHash($hash);

        $container = $this->get('service_container');
        $root = $container->getParameter('album_root');
        $source = $root . '/' . $picture->getFolder()->getPath() . '/' . $picture->getPath();

        $response = new BinaryFileResponse($source);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($picture->getPath()));

        return $response;
    }

}
