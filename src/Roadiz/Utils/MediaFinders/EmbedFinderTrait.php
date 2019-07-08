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
 * @file EmbedFinderTrait.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace RZ\Roadiz\Utils\MediaFinders;

use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Exception\ClientException;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Core\Entities\DocumentTranslation;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Exceptions\APINeedsAuthentificationException;
use RZ\Roadiz\Core\Models\DocumentInterface;

trait EmbedFinderTrait
{
    /**
     * @inheritDoc
     */
    protected function documentExists(ObjectManager $objectManager, $embedId, $embedPlatform)
    {
        $existingDocument = $objectManager->getRepository(Document::class)
            ->findOneBy([
                'embedId' => $embedId,
                'embedPlatform' => $embedPlatform,
            ]);

        return null !== $existingDocument;
    }

    /**
     * @inheritDoc
     */
    protected function injectMetaInDocument(ObjectManager $objectManager, DocumentInterface $document)
    {
        $translations = $objectManager->getRepository(Translation::class)->findAll();

        try {
            /** @var Translation $translation */
            foreach ($translations as $translation) {
                $documentTr = new DocumentTranslation();
                $documentTr->setDocument($document);
                $documentTr->setTranslation($translation);
                $documentTr->setName($this->getMediaTitle());
                $documentTr->setDescription($this->getMediaDescription());
                $documentTr->setCopyright($this->getMediaCopyright());
                $objectManager->persist($documentTr);
            }
        } catch (APINeedsAuthentificationException $exception) {
            // do no prevent from creating document if credentials are not provided.
        } catch (ClientException $exception) {
            // do no prevent from creating document if platform has errors, such as
            // too much API usage.
        }

        return $document;
    }
}
