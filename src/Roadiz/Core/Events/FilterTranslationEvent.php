<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\Events;

use RZ\Roadiz\Core\Entities\Translation;
use Symfony\Component\EventDispatcher\Event;

/**
 * @deprecated
 */
class FilterTranslationEvent extends Event
{
    protected $translation;

    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
    }

    public function getTranslation()
    {
        return $this->translation;
    }
}
