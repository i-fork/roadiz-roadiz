<?php
/**
 * Copyright © 2016, Ambroise Maupate and Julien Blanchet
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
 * @file TranslationSubscriber.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\Events;

use Doctrine\Common\Cache\CacheProvider;
use RZ\Roadiz\Core\Events\Translation\TranslationCreatedEvent;
use RZ\Roadiz\Core\Events\Translation\TranslationDeletedEvent;
use RZ\Roadiz\Core\Events\Translation\TranslationUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to Translation event to clear result cache.
 */
class TranslationSubscriber implements EventSubscriberInterface
{
    protected $cacheProvider = null;

    public function __construct(CacheProvider $cacheProvider)
    {
        $this->cacheProvider = $cacheProvider;
    }

    public static function getSubscribedEvents()
    {
        return [
            TranslationCreatedEvent::class => 'purgeCache',
            TranslationUpdatedEvent::class => 'purgeCache',
            TranslationDeletedEvent::class => 'purgeCache',
        ];
    }

    /**
     * Empty nodeSources Url cache
     */
    public function purgeCache()
    {
        $this->cacheProvider->deleteAll();
    }
}
