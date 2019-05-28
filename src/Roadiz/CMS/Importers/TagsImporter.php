<?php
/**
 * Copyright © 2014, Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
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
 * @file TagsImporter.php
 * @author Maxime Constantinian
 */
namespace RZ\Roadiz\CMS\Importers;

use Doctrine\ORM\EntityManager;
use Pimple\Container;
use RZ\Roadiz\Core\ContainerAwareInterface;
use RZ\Roadiz\Core\ContainerAwareTrait;
use RZ\Roadiz\Core\Entities\Setting;
use RZ\Roadiz\Core\Entities\SettingGroup;
use RZ\Roadiz\Core\Entities\Tag;
use RZ\Roadiz\Core\Entities\TagTranslation;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;
use RZ\Roadiz\Core\Handlers\HandlerFactoryInterface;
use RZ\Roadiz\Core\Serializers\TagJsonSerializer;

/**
 * Class TagsImporter.
 *
 * @package RZ\Roadiz\CMS\Importers
 */
class TagsImporter implements ImporterInterface, EntityImporterInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * NodesImporter constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }


    /**
     * @inheritDoc
     */
    public function supports(string $entityClass): bool
    {
        return $entityClass === Tag::class;
    }

    /**
     * @inheritDoc
     */
    public function import(string $serializedData): bool
    {
        /** @var EntityManager $em */
        $em = $this->get('em');

        $serializer = new TagJsonSerializer();
        $tags = $serializer->deserialize($serializedData);
        foreach ($tags as $tag) {
            static::browseTree($tag, $em);
        }

        $em->flush();

        return true;
    }


    protected static $usedTranslations;

    /**
     * Import a Json file (.rzt) containing tag and tag translation.
     *
     * @param string $serializedData
     * @param EntityManager $em
     * @param HandlerFactoryInterface $handlerFactory
     * @return bool
     * @deprecated
     */
    public static function importJsonFile($serializedData, EntityManager $em, HandlerFactoryInterface $handlerFactory)
    {
        $serializer = new TagJsonSerializer();
        $tags = $serializer->deserialize($serializedData);
        foreach ($tags as $tag) {
            static::browseTree($tag, $em);
        }

        $em->flush();

        return true;
    }

    /**
     * @param Tag $tag
     * @param EntityManager $em
     * @return null|Tag
     */
    protected static function browseTree(Tag $tag, EntityManager $em)
    {
        /**
         * Test if tag already exists against its tagName
         *
         * @var Tag|null $existing
         */
        $existing = $em->getRepository(Tag::class)
                       ->findOneByTagName($tag->getTagName());
        if (null !== $existing) {
            throw new EntityAlreadyExistsException('"' . $tag . '" already exists.');
        }

        foreach ($tag->getChildren() as $child) {
            static::browseTree($child, $em);
        }
        /*
         * Persist current tag BEFORE
         * handling any relationship
         */
        $em->persist($tag);

        /** @var TagTranslation $tagTranslation */
        foreach ($tag->getTranslatedTags() as $tagTranslation) {
            /** @var Translation|null $trans */
            $trans = $em->getRepository(Translation::class)
                ->findOneByLocale($tagTranslation->getTranslation()->getLocale());

            if (null === $trans &&
                !empty(static::$usedTranslations[$tagTranslation->getTranslation()->getLocale()])) {
                $trans = static::$usedTranslations[$tagTranslation->getTranslation()->getLocale()];
            }

            if (null === $trans) {
                $trans = new Translation();
                $trans->setLocale($tagTranslation->getTranslation()->getLocale());
                $trans->setName(Translation::$availableLocales[$tagTranslation->getTranslation()->getLocale()]);
                $em->persist($trans);

                static::$usedTranslations[$tagTranslation->getTranslation()->getLocale()] = $trans;
            }
            $tagTranslation->setTranslation($trans);
            $em->persist($tagTranslation);
        }

        return $tag;
    }
}
