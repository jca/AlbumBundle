<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Jcc\Bundle\AlbumBundle\Service;

use DirectoryIterator;
use DateTime;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Jcc\Bundle\AlbumBundle\Entity;

/**
 * Description of Folder
 *
 * @author jca
 */
class Folder
{
    /**
     * @var \Doctrine\ORM\EntityManager;
     */
    private $em;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $folderRepo;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $pictureRepo;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @param \Doctrine\ORM\EntityManager $em
     * @param array $parameters
     */
    public function __construct(EntityManager $em, array $parameters)
    {
        $this->em = $em;
        $this->parameters = $parameters;

        $this->folderRepo = $this->em->getRepository('JccAlbumBundle:Folder');
        $this->pictureRepo = $this->em->getRepository('JccAlbumBundle:Picture');
    }

    public function findOneFolderByPath($path)
    {
        //Find a folder entity in db
        $folder = $this->folderRepo->findOneByPath($path);

        if (!$folder) {
            $class = $this->folderRepo->getClassName();
            $folder = new $class;
            $folder->setName(basename($path));
            $folder->setPath($path);

            $this->em->persist($folder);
        }

        return $folder;
    }

    /**
     * @param \Jcc\Bundle\AlbumBundle\Entity\Folder $folder
     *
     * @return array
     */
    public function crawlFolderPictures(Entity\Folder $folder)
    {
        //Get all it's images and create a hashmap for matching (img > img path)
        $root = $this->parameters['album_root'];

        $all = $pictures = $saved = array();

        foreach ($folder->getPictures() as $picture) {
            $saved[$picture->getHash()] = $picture;
        }

        foreach (new DirectoryIterator($root. '/' . $folder->getPath()) as $file) {
            $all[] = array(
                'filename' => $file->getFilename(),
                'pathname' => $file->getPathname()
            );
        }

        usort($all, function($a, $b) {
            return strcmp($a['filename'], $b['filename']);
        });

        foreach($all as $file) {
            $extension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            if ('jpg' != $extension && 'jpeg' != $extension) {
                continue;
            }
            if (strpos($file['filename'], '.') === 0) {
                continue;
            }

            $hash = substr(md5($file['pathname']), 0, 12);
            if (isset($saved[$hash])) {
                $picture = $saved[$hash];
                unset($saved[$hash]);
            } else {
                $picture = new Entity\Picture;
                $picture->setPath($file['filename']);
                $picture->setHash($hash);
                $picture->setFolder($folder);

                $date = null;
                $exif = @exif_read_data($file['pathname']);
                if (!empty($exif['FileDateTime'])) {
                    $date = new DateTime("@" . $exif['FileDateTime']);
                } else {
                    $output = array();
                    exec(sprintf('exiftool %s', escapeshellarg($file['pathname'])), $output, $error);
                    foreach ($output ?: array() as $line) {
                        if (strpos($line, 'Date/Time Original') === false) {
                            continue;
                        }

                        list($key, $value) = preg_split('/\s+:\s+/', $line);
                        if ($key == 'Date/Time Original') {
                            $date = \DateTime::createFromFormat('Y:m:d h:i:s', $value);
                            break;
                        }
                    }
                }
		if ($date) 
                	$picture->setOriginalDate($date);

                $this->em->persist($picture);
            }

            $pictures[] = $picture;
        }

        //Remove inexistant pictures
        foreach ($saved as $picture) {
            $this->em->remove($picture);
        }

        $this->em->flush();

        //Sort pictures by date shot (EXIF)
        usort($pictures, function($a, $b) {
            if (!$a->getOriginalDate() || !$b->getOriginalDate()) {
                return strcmp($a->getPath(), $b->getPath());
            }
            return $a->getOriginalDate() > $b->getOriginalDate();
        });

        return $pictures;
    }
}
