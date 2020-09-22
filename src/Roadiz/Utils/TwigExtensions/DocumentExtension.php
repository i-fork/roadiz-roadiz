<?php
declare(strict_types=1);

namespace RZ\Roadiz\Utils\TwigExtensions;

use Pimple\Container;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Core\Exceptions\InvalidEmbedId;
use RZ\Roadiz\Core\Models\DocumentInterface;
use RZ\Roadiz\Document\Renderer\RendererInterface;
use RZ\Roadiz\Utils\MediaFinders\EmbedFinderFactory;
use RZ\Roadiz\Utils\MediaFinders\EmbedFinderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\OptionsResolver\Exception\InvalidArgumentException;
use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Extension that allow render document images.
 */
class DocumentExtension extends AbstractExtension
{
    /**
     * @var Container
     */
    private $container;
    /**
     * @var bool
     */
    private $throwExceptions;

    /**
     * DocumentExtension constructor.
     * @param Container $container
     * @param bool $throwExceptions Trigger exception if using filter on NULL values (default: false)
     */
    public function __construct(Container $container, $throwExceptions = false)
    {
        $this->container = $container;
        $this->throwExceptions = $throwExceptions;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return [
            new TwigFilter('display', [$this, 'display'], ['is_safe' => ['html']]),
            new TwigFilter('imageRatio', [$this, 'getImageRatio']),
            new TwigFilter('imageSize', [$this, 'getImageSize']),
            new TwigFilter('imageOrientation', [$this, 'getImageOrientation']),
            new TwigFilter('path', [$this, 'getPath']),
            new TwigFilter('exists', [$this, 'exists']),
            new TwigFilter('embedFinder', [$this, 'getEmbedFinder']),
        ];
    }

    /**
     * @param Document|null $document
     * @return null|EmbedFinderInterface
     * @throws RuntimeError
     */
    public function getEmbedFinder(Document $document = null): ?EmbedFinderInterface
    {
        if (null === $document) {
            if ($this->throwExceptions) {
                throw new RuntimeError('Document can’t be null to get its EmbedFinder.');
            } else {
                return null;
            }
        }

        try {
            /** @var EmbedFinderFactory $embedFinderFactory */
            $embedFinderFactory = $this->container[EmbedFinderFactory::class];
            if (null !== $document->getEmbedPlatform() &&
                $embedFinderFactory->supports($document->getEmbedPlatform())) {
                return $embedFinderFactory->createForPlatform(
                    $document->getEmbedPlatform(),
                    $document->getEmbedId()
                );
            }
        } catch (InvalidEmbedId $embedException) {
            if ($this->throwExceptions) {
                throw new RuntimeError($embedException->getMessage());
            } else {
                return null;
            }
        }

        return null;
    }

    /**
     * @param DocumentInterface|null $document
     * @param array $options
     *
     * @return string
     * @throws RuntimeError
     */
    public function display(DocumentInterface $document = null, array $options = [])
    {
        if (null === $document) {
            if ($this->throwExceptions) {
                throw new RuntimeError('Document can’t be null to be displayed.');
            } else {
                return "";
            }
        }
        try {
            /** @var RendererInterface $renderer */
            $renderer = $this->container[RendererInterface::class];
            return $renderer->render($document, $options);
        } catch (InvalidEmbedId $embedException) {
            if ($this->throwExceptions) {
                throw new RuntimeError($embedException->getMessage());
            } else {
                return '<p>'.$embedException->getMessage().'</p>';
            }
        } catch (InvalidArgumentException $e) {
            throw new RuntimeError($e->getMessage(), -1, null, $e);
        }
    }

    /**
     * Get image orientation.
     *
     * - Return null if document is not an Image
     * - Return `'landscape'` if width is higher or equal to height
     * - Return `'portrait'` if height is strictly lower to width
     *
     * @param Document $document
     * @return null|string
     * @throws RuntimeError
     */
    public function getImageOrientation(Document $document = null)
    {
        if (null === $document) {
            if ($this->throwExceptions) {
                throw new RuntimeError('Document can’t be null to get its orientation.');
            } else {
                return null;
            }
        }
        if (null !== $document && $document->isImage()) {
            $size = $this->getImageSize($document);
            return $size['width'] >= $size['height'] ? 'landscape' : 'portrait';
        }

        return null;
    }

    /**
     * @param Document $document
     * @return array
     * @throws RuntimeError
     */
    public function getImageSize(Document $document = null)
    {
        if (null === $document) {
            if ($this->throwExceptions) {
                throw new RuntimeError('Document can’t be null to get its size.');
            } else {
                return [
                    'width' => 0,
                    'height' => 0,
                ];
            }
        }
        if (null !== $document && $document->isImage()) {
            return [
                'width' => $document->getImageWidth(),
                'height' => $document->getImageHeight(),
            ];
        }

        return [
            'width' => 0,
            'height' => 0,
        ];
    }

    /**
     * @param Document|null $document
     * @return float
     * @throws RuntimeError
     */
    public function getImageRatio(Document $document = null)
    {
        if (null === $document) {
            if ($this->throwExceptions) {
                throw new RuntimeError('Document can’t be null to get its ratio.');
            } else {
                return 0.0;
            }
        }

        if (null !== $document &&
            $document->isImage() &&
            null !== $ratio = $document->getImageRatio()) {
            return $ratio;
        }

        return 0.0;
    }

    /**
     * @param Document|null $document
     * @return null|string
     */
    public function getPath(Document $document = null)
    {
        if (null !== $document) {
            return $this->container['assetPackages']->getDocumentFilePath($document);
        }

        return null;
    }

    /**
     * @param Document|null $document
     * @return bool
     */
    public function exists(Document $document = null)
    {
        if (null !== $document) {
            $fs = new Filesystem();
            return $fs->exists($this->container['assetPackages']->getDocumentFilePath($document));
        }

        return false;
    }
}
