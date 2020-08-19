<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\Entities;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use RZ\Roadiz\Core\Models\AbstractDocument;
use RZ\Roadiz\Core\Models\AdvancedDocumentInterface;
use RZ\Roadiz\Core\Models\DocumentInterface;
use RZ\Roadiz\Core\Models\FolderInterface;
use RZ\Roadiz\Core\Models\HasThumbnailInterface;
use RZ\Roadiz\Utils\StringHandler;
use JMS\Serializer\Annotation as Serializer;

/**
 * Documents entity represent a file on server with datetime and naming.
 *
 * @ORM\Entity(repositoryClass="RZ\Roadiz\Core\Repositories\DocumentRepository")
 * @ORM\Table(name="documents", indexes={
 *     @ORM\Index(columns={"raw"}),
 *     @ORM\Index(columns={"private"}),
 *     @ORM\Index(columns={"raw", "private"}),
 *     @ORM\Index(columns={"mime_type"})
 * })
 */
class Document extends AbstractDocument implements AdvancedDocumentInterface, HasThumbnailInterface
{
    /**
     * @ORM\OneToOne(targetEntity="Document", inversedBy="downscaledDocument", cascade={"all"}, fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="raw_document", referencedColumnName="id", onDelete="CASCADE")
     * @Serializer\Groups({"document"})
     * @Serializer\Type("RZ\Roadiz\Core\Entities\Document")
     * @var DocumentInterface|null
     */
    protected $rawDocument = null;
    /**
     * @ORM\Column(type="boolean", name="raw", nullable=false, options={"default" = false})
     * @Serializer\Groups({"document"})
     * @Serializer\Type("bool")
     */
    protected $raw = false;
    /**
     * @ORM\Column(type="string", name="embedId", unique=false, nullable=true)
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("string")
     */
    protected $embedId = null;
    /**
     * @ORM\Column(type="string", name="embedPlatform", unique=false, nullable=true)
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("string")
     */
    protected $embedPlatform = null;
    /**
     * @ORM\OneToMany(targetEntity="RZ\Roadiz\Core\Entities\NodesSourcesDocuments", mappedBy="document")
     * @var ArrayCollection
     * @Serializer\Exclude
     */
    protected $nodesSourcesByFields = null;
    /**
     * @ORM\OneToMany(targetEntity="RZ\Roadiz\Core\Entities\TagTranslationDocuments", mappedBy="document")
     * @var ArrayCollection
     * @Serializer\Exclude
     */
    protected $tagTranslations = null;
    /**
     * @ORM\OneToMany(targetEntity="RZ\Roadiz\Core\Entities\AttributeDocuments", mappedBy="document")
     * @var ArrayCollection
     * @Serializer\Exclude
     */
    protected $attributeDocuments = null;
    /**
     * @ORM\ManyToMany(targetEntity="RZ\Roadiz\Core\Entities\CustomFormFieldAttribute", mappedBy="documents")
     * @var ArrayCollection
     * @Serializer\Exclude
     */
    protected $customFormFieldAttributes = null;
    /**
     * @ORM\ManyToMany(targetEntity="RZ\Roadiz\Core\Entities\Folder", mappedBy="documents")
     * @ORM\JoinTable(name="documents_folders")
     * @Serializer\Groups({"document"})
     * @Serializer\Type("ArrayCollection<RZ\Roadiz\Core\Entities\Folder>")
     */
    protected $folders;
    /**
     * @ORM\OneToMany(targetEntity="DocumentTranslation", mappedBy="document", orphanRemoval=true, fetch="EAGER")
     * @var ArrayCollection
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("ArrayCollection<RZ\Roadiz\Core\Entities\DocumentTranslation>")
     */
    protected $documentTranslations;
    /**
     * @ORM\Column(type="string", nullable=true)
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("string")
     */
    private $filename;
    /**
     * @ORM\Column(name="mime_type", type="string", nullable=true)
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("string")
     */
    private $mimeType;
    /**
     * @ORM\OneToOne(targetEntity="Document", mappedBy="rawDocument")
     * @Serializer\Exclude
     * @var DocumentInterface|null
     */
    private $downscaledDocument = null;
    /**
     * @ORM\Column(type="string")
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("string")
     */
    private $folder;
    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default" = false})
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("bool")
     */
    private $private = false;
    /**
     * @var integer
     * @ORM\Column(type="integer", nullable=false, options={"default" = 0})
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("int")
     */
    private $imageWidth = 0;
    /**
     * @var integer
     * @ORM\Column(type="integer", nullable=false, options={"default" = 0})
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("int")
     */
    private $imageHeight = 0;
    /**
     * @var string|null
     * @ORM\Column(type="string", name="average_color", length=7, unique=false, nullable=true)
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("string")
     */
    private $imageAverageColor;
    /**
     * @var int|null The filesize in bytes.
     * @ORM\Column(type="integer", nullable=true, unique=false)
     * @Serializer\Groups({"document", "nodes_sources", "tag", "attribute"})
     * @Serializer\Type("int")
     */
    private $filesize;

    /**
     * @var Collection<Document>
     * @ORM\OneToMany(targetEntity="RZ\Roadiz\Core\Entities\Document", mappedBy="original")
     * @Serializer\Groups({"document_thumbnails"})
     * @Serializer\MaxDepth(2)
     * @Serializer\Type("ArrayCollection<RZ\Roadiz\Core\Entities\Document>")
     */
    private $thumbnails;

    /**
     * @var HasThumbnailInterface|Document|null
     * @ORM\ManyToOne(targetEntity="RZ\Roadiz\Core\Entities\Document", inversedBy="thumbnails")
     * @ORM\JoinColumn(name="original", nullable=true, onDelete="SET NULL")
     * @Serializer\Groups({"document_original"})
     * @Serializer\MaxDepth(2)
     * @Serializer\Type("RZ\Roadiz\Core\Entities\Document")
     */
    private $original = null;

    /**
     * Document constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->folders = new ArrayCollection();
        $this->documentTranslations = new ArrayCollection();
        $this->nodesSourcesByFields = new ArrayCollection();
        $this->tagTranslations = new ArrayCollection();
        $this->attributeDocuments = new ArrayCollection();
        $this->customFormFieldAttributes = new ArrayCollection();
        $this->thumbnails = new ArrayCollection();
        $this->imageWidth = 0;
        $this->imageHeight = 0;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     *
     * @return $this
     */
    public function setFilename($filename)
    {
        $this->filename = StringHandler::cleanForFilename($filename ?? '');

        return $this;
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * @param string $mimeType
     *
     * @return $this
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * @return string
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * Set folder name.
     *
     * @param string $folder
     * @return $this
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmbedId()
    {
        return $this->embedId;
    }

    /**
     * @param string $embedId
     * @return $this
     */
    public function setEmbedId($embedId)
    {
        $this->embedId = $embedId;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmbedPlatform()
    {
        return $this->embedPlatform;
    }

    /**
     * @param string $embedPlatform
     * @return $this
     */
    public function setEmbedPlatform($embedPlatform)
    {
        $this->embedPlatform = $embedPlatform;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isPrivate()
    {
        return $this->private;
    }

    /**
     * @param boolean $private
     * @return $this
     */
    public function setPrivate($private)
    {
        $this->private = (boolean) $private;
        if (null !== $raw = $this->getRawDocument()) {
            $raw->setPrivate($private);
        }

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getNodesSourcesByFields()
    {
        return $this->nodesSourcesByFields;
    }

    /**
     * @return ArrayCollection
     */
    public function getTagTranslations()
    {
        return $this->tagTranslations;
    }

    /**
     * @return ArrayCollection
     */
    public function getAttributeDocuments(): Collection
    {
        return $this->attributeDocuments;
    }

    /**
     * @param FolderInterface $folder
     * @return $this
     */
    public function addFolder(FolderInterface $folder)
    {
        if (!$this->getFolders()->contains($folder)) {
            $this->folders->add($folder);
        }

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getFolders()
    {
        return $this->folders;
    }

    /**
     * @param FolderInterface $folder
     * @return $this
     */
    public function removeFolder(FolderInterface $folder)
    {
        if ($this->getFolders()->contains($folder)) {
            $this->folders->remove($folder);
        }

        return $this;
    }

    /**
     * @param Translation $translation
     * @return Collection
     */
    public function getDocumentTranslationsByTranslation(Translation $translation)
    {
        $criteria = Criteria::create();
        $criteria->where(Criteria::expr()->eq('translation', $translation));

        return $this->documentTranslations->matching($criteria);
    }

    /**
     * @param DocumentTranslation $documentTranslation
     * @return $this
     */
    public function addDocumentTranslation(DocumentTranslation $documentTranslation)
    {
        if (!$this->getDocumentTranslations()->contains($documentTranslation)) {
            $this->documentTranslations->add($documentTranslation);
        }

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getDocumentTranslations()
    {
        return $this->documentTranslations;
    }

    /**
     * @return bool
     */
    public function hasTranslations()
    {
        return (boolean) $this->getDocumentTranslations()->count();
    }

    /**
     * Gets the value of rawDocument.
     *
     * @return DocumentInterface|null
     */
    public function getRawDocument()
    {
        return $this->rawDocument;
    }

    /**
     * Sets the value of rawDocument.
     *
     * @param DocumentInterface|null $rawDocument the raw document
     *
     * @return self
     */
    public function setRawDocument(DocumentInterface $rawDocument = null)
    {
        $this->rawDocument = $rawDocument;

        return $this;
    }

    /**
     * Is document a raw one.
     *
     * @return boolean
     */
    public function isRaw()
    {
        return $this->raw;
    }

    /**
     * Sets the value of raw.
     *
     * @param boolean $raw the raw
     *
     * @return self
     */
    public function setRaw($raw)
    {
        $this->raw = (boolean) $raw;

        return $this;
    }

    /**
     * Gets the downscaledDocument.
     *
     * @return DocumentInterface|null
     */
    public function getDownscaledDocument()
    {
        return $this->downscaledDocument;
    }

    /**
     * @return int
     */
    public function getImageWidth(): int
    {
        return $this->imageWidth;
    }

    /**
     * @param int $imageWidth
     *
     * @return Document
     */
    public function setImageWidth(int $imageWidth)
    {
        $this->imageWidth = $imageWidth;

        return $this;
    }

    /**
     * @return int
     */
    public function getImageHeight(): int
    {
        return $this->imageHeight;
    }

    /**
     * @param int $imageHeight
     *
     * @return Document
     */
    public function setImageHeight(int $imageHeight)
    {
        $this->imageHeight = $imageHeight;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getImageRatio(): ?float
    {
        if ($this->getImageWidth() > 0 && $this->getImageHeight() > 0) {
            return $this->getImageWidth() / $this->getImageHeight();
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getImageAverageColor(): ?string
    {
        return $this->imageAverageColor;
    }

    /**
     * @param string|null $imageAverageColor
     *
     * @return Document
     */
    public function setImageAverageColor(?string $imageAverageColor)
    {
        $this->imageAverageColor = $imageAverageColor;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getFilesize(): ?int
    {
        return $this->filesize;
    }

    /**
     * @param int|null $filesize
     * @return Document
     */
    public function setFilesize(?int $filesize)
    {
        $this->filesize = $filesize;
        return $this;
    }

    public function getAlternativeText(): string
    {
        $documentTranslation = $this->getDocumentTranslations()->first();
        return $documentTranslation && !empty($documentTranslation->getName()) ?
            $documentTranslation->getName() :
            parent::getAlternativeText();
    }

    /**
     * Clone current document.
     */
    public function __clone()
    {
        if ($this->id) {
            $this->id = null;
            $this->rawDocument = null;
        }
    }

    /**
     * @return Collection
     */
    public function getThumbnails(): Collection
    {
        return $this->thumbnails;
    }

    /**
     * @param Collection $thumbnails
     *
     * @return Document
     */
    public function setThumbnails(Collection $thumbnails): Document
    {
        if ($this->thumbnails->count()) {
            /** @var HasThumbnailInterface $thumbnail */
            foreach ($this->thumbnails as $thumbnail) {
                $thumbnail->setOriginal(null);
            }
        }
        $this->thumbnails = $thumbnails->filter(function (HasThumbnailInterface $thumbnail) {
            return $thumbnail !== $this;
        });
        /** @var HasThumbnailInterface $thumbnail */
        foreach ($this->thumbnails as $thumbnail) {
            $thumbnail->setOriginal($this);
        }

        return $this;
    }

    /**
     * @return HasThumbnailInterface|null
     */
    public function getOriginal(): ?HasThumbnailInterface
    {
        return $this->original;
    }

    /**
     * @param HasThumbnailInterface|null $original
     *
     * @return Document
     */
    public function setOriginal(?HasThumbnailInterface $original): Document
    {
        if ($original !== $this) {
            $this->original = $original;
        }

        return $this;
    }

    /**
     * @return bool
     * @Serializer\Groups({"document"})
     * @Serializer\SerializedName("isThumbnail")
     * @Serializer\VirtualProperty()
     */
    public function isThumbnail(): bool
    {
        return $this->getOriginal() !== null;
    }

    /**
     * @return bool
     * @Serializer\Groups({"document"})
     * @Serializer\SerializedName("hasThumbnail")
     * @Serializer\VirtualProperty()
     */
    public function hasThumbnails(): bool
    {
        return $this->getThumbnails()->count() > 0;
    }

    /**
     * @return bool
     */
    public function needsThumbnail(): bool
    {
        return !$this->isProcessable();
    }
}
