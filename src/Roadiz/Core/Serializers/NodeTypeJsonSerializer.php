<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\Serializers;

use RZ\Roadiz\Core\Entities\NodeType;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Json Serialization handler for NodeType.
 *
 * @deprecated Use Serializer service.
 */
class NodeTypeJsonSerializer extends AbstractJsonSerializer
{
    protected $ntfSerializer;

    public function __construct()
    {
        $this->ntfSerializer = new NodeTypeFieldJsonSerializer();
    }
    /**
     * Create a simple associative array with a NodeType.
     *
     * @param NodeType $nodeType
     * @deprecated Use Serializer service.
     * @return array
     */
    public function toArray($nodeType)
    {
        $data = [];

        $data['name'] = $nodeType->getName();
        $data['displayName'] = $nodeType->getDisplayName();
        $data['description'] = $nodeType->getDescription();
        $data['visible'] = $nodeType->isVisible();
        $data['newsletterType'] = $nodeType->isNewsletterType();
        $data['hidingNodes'] = $nodeType->isHidingNodes();
        $data['color'] = $nodeType->getColor();
        $data['defaultTtl'] = $nodeType->getDefaultTtl();
        $data['reachable'] = $nodeType->isReachable();
        $data['publishable'] = $nodeType->isPublishable();
        $data['fields'] = [];

        foreach ($nodeType->getFields() as $nodeTypeField) {
            $nodeTypeFieldData = $this->ntfSerializer->toArray($nodeTypeField);
            $data['fields'][] = $nodeTypeFieldData;
        }

        return $data;
    }

    /**
     * Deserializes a Json into readable datas.
     *
     * @param string $string
     * @deprecated Use Serializer service.
     * @return \RZ\Roadiz\Core\Entities\NodeType
     */
    public function deserialize($string)
    {
        $encoder = new JsonEncoder();
        $nameConverter = new CamelCaseToSnakeCaseNameConverter([
            'name',
            'displayName',
            'description',
            'visible',
            'newsletterType',
            'defaultTtl',
            'color',
            'hidingNodes',
            'reachable',
            'publishable',
        ]);
        $normalizer = new GetSetMethodNormalizer(null, $nameConverter);
        $serializer = new Serializer([$normalizer], [$encoder]);
        /** @var NodeType $nodeType */
        $nodeType = $serializer->deserialize($string, NodeType::class, 'json');

        /*
         * Importing Fields.
         *
         * We need to extract fields from node-type and to re-encode them
         * to pass to NodeTypeFieldJsonSerializer.
         */
        $tempArray = json_decode($string, true);

        foreach ($tempArray['fields'] as $fieldAssoc) {
            $ntField = $this->ntfSerializer->deserialize(json_encode($fieldAssoc));
            $nodeType->addField($ntField);
        }

        return $nodeType;
    }
}
