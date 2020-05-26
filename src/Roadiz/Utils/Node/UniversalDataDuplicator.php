<?php
declare(strict_types=1);

namespace RZ\Roadiz\Utils\Node;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Entities\NodesSources;
use RZ\Roadiz\Core\Entities\NodesSourcesDocuments;
use RZ\Roadiz\Core\Entities\NodeTypeField;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Repositories\NodesSourcesRepository;
use RZ\Roadiz\Core\Repositories\NodeTypeFieldRepository;
use RZ\Roadiz\Core\Repositories\TranslationRepository;

class UniversalDataDuplicator
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * UniversalDataDuplicator constructor.
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Duplicate node-source universal to any other language source for the same node.
     *
     * **Be careful, this method does not flush.**
     *
     * @param NodesSources $source
     * @return bool
     */
    public function duplicateUniversalContents(NodesSources $source)
    {
        /*
         * Only if source is default translation.
         * Non-default translation source should not contain universal fields.
         */
        if ($source->getTranslation()->isDefaultTranslation() || !$this->hasDefaultTranslation($source)) {
            /** @var NodeTypeFieldRepository<NodeTypeField> $nodeTypeFieldRepository */
            $nodeTypeFieldRepository = $this->em->getRepository(NodeTypeField::class);
            $universalFields = $nodeTypeFieldRepository->findAllUniversal($source->getNode()->getNodeType());

            if (count($universalFields) > 0) {
                /** @var NodesSourcesRepository<NodesSources> $repository */
                $repository = $this->em->getRepository(NodesSources::class);
                $repository->setDisplayingAllNodesStatuses(true)
                    ->setDisplayingNotPublishedNodes(true)
                ;
                $otherSources = $repository->findBy([
                    'node' => $source->getNode(),
                    'id' => ['!=', $source->getId()],
                ]);

                /** @var NodeTypeField $universalField */
                foreach ($universalFields as $universalField) {
                    /** @var NodesSources $otherSource */
                    foreach ($otherSources as $otherSource) {
                        if (!$universalField->isVirtual()) {
                            $this->duplicateNonVirtualField($source, $otherSource, $universalField);
                        } else {
                            switch ($universalField->getType()) {
                                case NodeTypeField::DOCUMENTS_T:
                                    $this->duplicateDocumentsField($source, $otherSource, $universalField);
                                    break;
                                case NodeTypeField::MANY_TO_ONE_T:
                                case NodeTypeField::MANY_TO_MANY_T:
                                    $this->duplicateNonVirtualField($source, $otherSource, $universalField);
                                    break;
                            }
                        }
                    }
                }
                return true;
            }
        }

        return false;
    }

    /**
     * @param NodesSources $source
     * @return bool
     */
    private function hasDefaultTranslation(NodesSources $source)
    {
        /** @var TranslationRepository<Translation> $translationRepository */
        $translationRepository = $this->em->getRepository(Translation::class);
        /** @var Translation $defaultTranslation */
        $defaultTranslation = $translationRepository->findDefault();

        /** @var NodesSourcesRepository<NodesSources> $repository */
        $repository = $this->em->getRepository(NodesSources::class);
        $sourceCount = $repository->setDisplayingAllNodesStatuses(true)
            ->setDisplayingNotPublishedNodes(true)
            ->countBy([
                'node' => $source->getNode(),
                'translation' => $defaultTranslation,
            ]);

        return $sourceCount === 1;
    }

    /**
     * @param NodesSources $universalSource
     * @param NodesSources $destSource
     * @param NodeTypeField $field
     */
    protected function duplicateNonVirtualField(NodesSources $universalSource, NodesSources $destSource, NodeTypeField $field)
    {
        $getter = $field->getGetterName();
        $setter = $field->getSetterName();

        $destSource->$setter($universalSource->$getter());
    }

    /**
     * @param NodesSources  $universalSource
     * @param NodesSources  $destSource
     * @param NodeTypeField $field
     *
     * @throws \Doctrine\ORM\ORMException
     */
    protected function duplicateDocumentsField(NodesSources $universalSource, NodesSources $destSource, NodeTypeField $field)
    {
        $newDocuments = $this->em
            ->getRepository(NodesSourcesDocuments::class)
            ->findBy(['nodeSource' => $universalSource, 'field' => $field]);

        $formerDocuments = $this->em
            ->getRepository(NodesSourcesDocuments::class)
            ->findBy(['nodeSource' => $destSource, 'field' => $field]);

        /* Delete former documents */
        if (count($formerDocuments) > 0) {
            foreach ($formerDocuments as $formerDocument) {
                $this->em->remove($formerDocument);
            }
        }
        /* Add new documents */
        if (count($newDocuments) > 0) {
            /** @var NodesSourcesDocuments $newDocument */
            $position = 1;
            foreach ($newDocuments as $newDocument) {
                $nsDoc = new NodesSourcesDocuments($destSource, $newDocument->getDocument(), $field);
                $nsDoc->setPosition($position);
                $position++;

                $this->em->persist($nsDoc);
            }
        }
    }
}
