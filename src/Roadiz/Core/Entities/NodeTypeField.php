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
 * @file NodeTypeField.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Core\Entities;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use RZ\Roadiz\Core\AbstractEntities\AbstractField;

/**
 * NodeTypeField entities are used to create NodeTypes with
 * custom data structure.
 *
 * @ORM\Entity(repositoryClass="RZ\Roadiz\Core\Repositories\NodeTypeFieldRepository")
 * @ORM\Table(name="node_type_fields", indexes={
 *         @ORM\Index(columns={"visible"}),
 *         @ORM\Index(columns={"indexed"}),
 *         @ORM\Index(columns={"position"}),
 *         @ORM\Index(columns={"group_name"}),
 *         @ORM\Index(columns={"group_name_canonical"}),
 *         @ORM\Index(columns={"type"}),
 *         @ORM\Index(columns={"universal"})
 *     },
 *     uniqueConstraints={@ORM\UniqueConstraint(columns={"name", "node_type_id"})}
 * )
 * @ORM\HasLifecycleCallbacks
 */
class NodeTypeField extends AbstractField
{
    /**
     * If current field data should be the same over translations or not.
     *
     * @var bool
     * @ORM\Column(name="universal", type="boolean", nullable=false, options={"default" = false})
     * @Serializer\Groups({"node_type"})
     */
    private $universal = false;

    /**
     * @ORM\ManyToOne(targetEntity="NodeType", inversedBy="fields")
     * @ORM\JoinColumn(name="node_type_id", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $nodeType;

    /**
     * @return \RZ\Roadiz\Core\Entities\NodeType
     */
    public function getNodeType()
    {
        return $this->nodeType;
    }

    /**
     * @param \RZ\Roadiz\Core\Entities\NodeType $nodeType
     *
     * @return $this
     */
    public function setNodeType($nodeType)
    {
        $this->nodeType = $nodeType;

        return $this;
    }

    /**
     * @ORM\Column(name="min_length", type="integer", nullable=true)
     * @Serializer\Groups({"node_type"})
     */
    private $minLength = null;

    /**
     * @return int
     */
    public function getMinLength()
    {
        return $this->minLength;
    }

    /**
     * @param $minLength
     *
     * @return $this
     */
    public function setMinLength($minLength)
    {
        $this->minLength = $minLength;

        return $this;
    }

    /**
     * @ORM\Column(name="max_length", type="integer", nullable=true)
     * @Serializer\Groups({"node_type"})
     */
    private $maxLength = null;

    /**
     * @return int
     */
    public function getMaxLength()
    {
        return $this->maxLength;
    }

    /**
     * @param int $maxLength
     *
     * @return $this
     */
    public function setMaxLength($maxLength)
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default" = false})
     * @Serializer\Groups({"node_type"})
     */
    private $indexed = false;

    /**
     * @return boolean $isIndexed
     */
    public function isIndexed()
    {
        return $this->indexed;
    }

    /**
     * @param boolean $indexed
     *
     * @return $this
     */
    public function setIndexed($indexed)
    {
        $this->indexed = $indexed;

        return $this;
    }

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default" = true})
     * @Serializer\Groups({"node_type"})
     */
    private $visible = true;

    /**
     * @return boolean $isVisible
     */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
     * @param boolean $visible
     *
     * @return $this
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Tell if current field can be searched and indexed in a Search engine server.
     *
     * @return boolean
     */
    public function isSearchable()
    {
        return (boolean) in_array($this->getType(), static::$searchableTypes);
    }

    /**
     * @return string
     */
    public function getOneLineSummary()
    {
        return $this->getId() . " — " . $this->getLabel() . ' ['.$this->getName().']' .
        ' - ' . static::$typeToHuman[$this->getType()] .
        ($this->isIndexed() ? ' - indexed' : '') .
        (!$this->isVisible() ? ' - hidden' : '') . PHP_EOL;
    }

    /**
     * @see Same as isUniversal
     * @return mixed
     */
    public function getUniversal()
    {
        return $this->universal;
    }

    /**
     * @return bool
     */
    public function isUniversal()
    {
        return $this->universal;
    }

    /**
     * @param bool $universal
     * @return NodeTypeField
     */
    public function setUniversal($universal)
    {
        $this->universal = (bool) $universal;
        return $this;
    }
}
