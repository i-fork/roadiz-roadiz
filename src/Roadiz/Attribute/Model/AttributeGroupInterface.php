<?php
declare(strict_types=1);

namespace RZ\Roadiz\Attribute\Model;

use Doctrine\Common\Collections\Collection;
use RZ\Roadiz\Core\Entities\Translation;

interface AttributeGroupInterface
{
    public function getName(): ?string;
    public function getTranslatedName(Translation $translation): ?string;
    public function setName(string $name);

    public function getCanonicalName(): ?string;
    public function setCanonicalName(string $canonicalName);

    public function getAttributes(): Collection;
    public function setAttributes(Collection $attributes);

    public function getAttributeGroupTranslations(): Collection;
    public function setAttributeGroupTranslations(Collection $attributeGroupTranslations);
}
