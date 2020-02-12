<?php
declare(strict_types=1);
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
 * @file DocumentsFieldGenerator.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace RZ\Roadiz\Utils\Doctrine\Generators;

use RZ\Roadiz\Core\Entities\Document;

/**
 * Class DocumentsFieldGenerator
 *
 * @package RZ\Roadiz\Utils\Doctrine\Generators
 */
class DocumentsFieldGenerator extends AbstractFieldGenerator
{
    /**
     * @inheritDoc
     */
    public function getFieldGetter(): string
    {
        return '
    /**
     * @return array Documents array
     * @Serializer\VirtualProperty
     * @Serializer\Groups({"nodes_sources", "nodes_sources_documents", "nodes_sources_'.($this->field->getGroupNameCanonical() ?: 'default').'"})
     * @Serializer\SerializedName("'.$this->field->getVarName().'")
     */
    public function '.$this->field->getGetterName().'()
    {
        if (null === $this->' . $this->field->getVarName() . ') {
            if (null !== $this->objectManager) {
                $this->' . $this->field->getVarName() . ' = $this->objectManager
                    ->getRepository(Document::class)
                    ->findByNodeSourceAndField(
                        $this,
                        $this->getNode()->getNodeType()->getFieldByName("'.$this->field->getName().'")
                    );
            } else {
                $this->' . $this->field->getVarName() . ' = [];
            }
        }
        return $this->' . $this->field->getVarName() . ';
    }'.PHP_EOL;
    }

    /**
     * Generate PHP setter method block.
     *
     * @return string
     */
    protected function getFieldSetter(): string
    {
        return '
    /**
     * @param Document $document
     *
     * @return $this
     */
    public function add'.ucfirst($this->field->getVarName()).'(Document $document)
    {
        $field = $this->getNode()->getNodeType()->getFieldByName("'.$this->field->getName().'");
        if (null !== $field) {
            $nodeSourceDocument = new \RZ\Roadiz\Core\Entities\NodesSourcesDocuments(
                $this,
                $document,
                $field
            );
            $this->objectManager->persist($nodeSourceDocument);
            $this->addDocumentsByFields($nodeSourceDocument);
            $this->' . $this->field->getVarName() . ' = null;
        }
        return $this;
    }'.PHP_EOL;
    }
}
