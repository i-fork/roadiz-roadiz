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
 * @file SvgDocumentSubscriber.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace Themes\Rozier\Events;

use enshrined\svgSanitize\Sanitizer;
use Psr\Log\LoggerInterface;
use RZ\Roadiz\Core\Events\DocumentSvgUploadedEvent;
use RZ\Roadiz\Core\Events\FilterDocumentEvent;
use RZ\Roadiz\Utils\Asset\Packages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SvgDocumentSubscriber implements EventSubscriberInterface
{
    /**
     * @var Packages
     */
    private $packages;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Packages $packages
     * @param LoggerInterface $logger
     */
    public function __construct(
        Packages $packages,
        LoggerInterface $logger = null
    ) {
        $this->packages = $packages;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            DocumentSvgUploadedEvent::class => 'onSvgUploaded',
        ];
    }

    /**
     * @param FilterDocumentEvent $event
     */
    public function onSvgUploaded(FilterDocumentEvent $event)
    {
        $document = $event->getDocument();
        $documentPath = $this->packages->getDocumentFilePath($document);

        // Create a new sanitizer instance
        $sanitizer = new Sanitizer();
        $sanitizer->minify(true);

        // Load the dirty svg
        $dirtySVG = file_get_contents($documentPath);

        file_put_contents($documentPath, $sanitizer->sanitize($dirtySVG));

        $this->logger->info('Svg document sanitized.');
    }
}
