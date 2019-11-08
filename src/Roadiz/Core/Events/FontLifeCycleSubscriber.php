<?php
/**
 * Copyright (c) 2017. Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file FontLifeCycleSubscriber.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace RZ\Roadiz\Core\Events;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Monolog\Logger;
use Pimple\Container;
use RZ\Roadiz\Core\Entities\Font;
use RZ\Roadiz\Utils\Asset\Packages;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Handle file management on Fonts lifecycle events.
 *
 * @package Roadiz\Core\Events
 */
class FontLifeCycleSubscriber implements EventSubscriber
{
    /**
     * @var Container
     */
    private $container;

    /**
     * FontLifeCycleSubscriber constructor.
     *
     * We need to pass whole container not to trigger asset packages
     * initialization and not to creation a dependency infinite loop.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::preRemove,
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        // perhaps you only want to act on some "Font" entity
        if ($entity instanceof Font) {
            $this->setFontFilesNames($entity);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        // perhaps you only want to act on some "Font" entity
        if ($entity instanceof Font) {
            $this->setFontFilesNames($entity);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        // perhaps you only want to act on some "Font" entity
        if ($entity instanceof Font) {
            $this->upload($entity);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        // perhaps you only want to act on some "Font" entity
        if ($entity instanceof Font) {
            $this->upload($entity);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        /** @var Packages $packages */
        $packages = $this->container->offsetGet('assetPackages');
        /** @var Logger $logger */
        $logger = $this->container->offsetGet('logger.doctrine');

        $entity = $args->getObject();
        // perhaps you only want to act on some "Product" entity
        if ($entity instanceof Font) {
            $fileSystem = new Filesystem();
            try {
                if (null !== $entity->getSVGFilename()) {
                    $svgFilePath = $packages->getFontsPath($entity->getSVGRelativeUrl());
                    $logger->info('Font file deleted', ['file' => $svgFilePath]);
                    $fileSystem->remove($svgFilePath);
                }
                if (null !== $entity->getOTFFilename()) {
                    $otfFilePath = $packages->getFontsPath($entity->getOTFRelativeUrl());
                    $logger->info('Font file deleted', ['file' => $otfFilePath]);
                    $fileSystem->remove($otfFilePath);
                }
                if (null !== $entity->getEOTFilename()) {
                    $eotFilePath = $packages->getFontsPath($entity->getEOTRelativeUrl());
                    $logger->info('Font file deleted', ['file' => $eotFilePath]);
                    $fileSystem->remove($eotFilePath);
                }
                if (null !== $entity->getWOFFFilename()) {
                    $woffFilePath = $packages->getFontsPath($entity->getWOFFRelativeUrl());
                    $logger->info('Font file deleted', ['file' => $woffFilePath]);
                    $fileSystem->remove($woffFilePath);
                }
                if (null !== $entity->getWOFF2Filename()) {
                    $woff2FilePath = $packages->getFontsPath($entity->getWOFF2RelativeUrl());
                    $logger->info('Font file deleted', ['file' => $woff2FilePath]);
                    $fileSystem->remove($woff2FilePath);
                }

                /*
                 * Removing font folder if empty.
                 */
                $fontFolderPath = $packages->getFontsPath($entity->getFolder());
                if ($fileSystem->exists($fontFolderPath)) {
                    $isDirEmpty = !(new \FilesystemIterator($fontFolderPath))->valid();
                    if ($isDirEmpty) {
                        $logger->info('Font folder is empty, deleting…', ['folder' => $fontFolderPath]);
                        $fileSystem->remove($fontFolderPath);
                    }
                }
            } catch (IOException $e) {
                //do nothing
            }
        }
    }

    /**
     * @param Font $font
     */
    public function setFontFilesNames(Font $font)
    {
        if ($font->getHash() == "") {
            $font->generateHashWithSecret('default_roadiz_secret');
        }

        if (null !== $font->getSvgFile()) {
            $font->setSVGFilename($font->getSvgFile()->getClientOriginalName());
        }
        if (null !== $font->getOtfFile()) {
            $font->setOTFFilename($font->getOtfFile()->getClientOriginalName());
        }
        if (null !== $font->getEotFile()) {
            $font->setEOTFilename($font->getEotFile()->getClientOriginalName());
        }
        if (null !== $font->getWoffFile()) {
            $font->setWOFFFilename($font->getWoffFile()->getClientOriginalName());
        }
        if (null !== $font->getWoff2File()) {
            $font->setWOFF2Filename($font->getWoff2File()->getClientOriginalName());
        }
    }

    /**
     * @param Font $font
     */
    public function upload(Font $font)
    {
        /** @var Packages $packages */
        $packages = $this->container->offsetGet('assetPackages');
        $fontFolderPath = $packages->getFontsPath($font->getFolder());

        if (null !== $font->getSvgFile()) {
            $font->getSvgFile()->move($fontFolderPath, $font->getSVGFilename());
            $font->setSvgFile(null);
        }
        if (null !== $font->getOtfFile()) {
            $font->getOtfFile()->move($fontFolderPath, $font->getOTFFilename());
            $font->setOtfFile(null);
        }
        if (null !== $font->getEotFile()) {
            $font->getEotFile()->move($fontFolderPath, $font->getEOTFilename());
            $font->setEotFile(null);
        }
        if (null !== $font->getWoffFile()) {
            $font->getWoffFile()->move($fontFolderPath, $font->getWOFFFilename());
            $font->setWoffFile(null);
        }
        if (null !== $font->getWoff2File()) {
            $font->getWoff2File()->move($fontFolderPath, $font->getWOFF2Filename());
            $font->setWoff2File(null);
        }
    }
}
