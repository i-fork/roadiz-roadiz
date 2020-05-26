<?php
declare(strict_types=1);

namespace Themes\Rozier\Events;

use Doctrine\ORM\EntityManager;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\ImageManager;
use Psr\Log\LoggerInterface;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Core\Events\DocumentImageUploadedEvent;
use RZ\Roadiz\Core\Events\FilterDocumentEvent;
use RZ\Roadiz\Core\Models\DocumentInterface;
use RZ\Roadiz\Utils\Asset\Packages;
use RZ\Roadiz\Utils\Document\AverageColorResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ImageColorDocumentSubscriber implements EventSubscriberInterface
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
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @param EntityManager $entityManager
     * @param Packages $packages
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityManager $entityManager,
        Packages $packages,
        LoggerInterface $logger = null
    ) {
        $this->packages = $packages;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            DocumentImageUploadedEvent::class => ['onImageUploaded', 0],
        ];
    }

    /**
     * @param DocumentInterface $document
     *
     * @return bool
     */
    protected function supports(DocumentInterface $document)
    {
        if ($document->isProcessable() && $document instanceof Document) {
            return true;
        }

        return false;
    }

    /**
     * @param FilterDocumentEvent $event
     */
    public function onImageUploaded(FilterDocumentEvent $event)
    {
        $document = $event->getDocument();
        if ($this->supports($document)) {
            $documentPath = $this->packages->getDocumentFilePath($document);
            try {
                $manager = new ImageManager();
                $mediumColor = (new AverageColorResolver())->getAverageColor($manager->make($documentPath));
                $document->setImageAverageColor($mediumColor);
            } catch (NotReadableException $exception) {
                /*
                 * Do nothing
                 */
                $this->logger->warning('Document file is not a readable image.', [
                    'id' => $document->getId(),
                    'path' => $documentPath,
                ]);
            }
        }
    }
}
