<?php
declare(strict_types=1);

use RZ\Roadiz\Core\Entities\AttributeValueTranslation;
use RZ\Roadiz\Tests\SerializedEntityTestTrait;

final class AttributeValueTranslationTest extends \PHPUnit\Framework\TestCase
{
    use SerializedEntityTestTrait;

    /*
     * Test empty object serialization
     */
    public function testSerialize()
    {
        $a = new AttributeValueTranslation();
        $this->assertJson($this->getSerializer()->serialize($a, 'json'));
    }
}
