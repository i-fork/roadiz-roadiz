<?php
/**
 * Copyright (c) 2018. Ambroise Maupate and Julien Blanchet
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
 * @file FilterQueryCriteriaEvent.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace RZ\Roadiz\Core\Events;

use Doctrine\ORM\Query;
use Symfony\Component\EventDispatcher\Event;

class FilterQueryCriteriaEvent extends Event
{
    /**
     * @var string
     */
    protected $property;
    /**
     * @var mixed
     */
    protected $value;
    /**
     * @var string
     */
    protected $entityClass;
    /**
     * @var Query
     */
    protected $query;

    /**
     * FilterQueryBuilderEvent constructor.
     *
     * @param Query $query
     * @param string $entityClass
     * @param string $property
     * @param mixed $value
     */
    public function __construct(Query $query, $entityClass, $property, $value)
    {
        $this->entityClass = $entityClass;
        $this->property = $property;
        $this->value = $value;
        $this->query = $query;
    }

    /**
     * @return Query
     */
    public function getQuery(): Query
    {
        return $this->query;
    }

    /**
     * @param Query $query
     *
     * @return FilterQueryCriteriaEvent
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }


    /**
     * @param string $entityClass
     * @return bool
     */
    public function supports($entityClass): bool
    {
        return $this->entityClass === $entityClass;
    }
}
