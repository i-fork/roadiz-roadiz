<?php
declare(strict_types=1);

namespace RZ\Roadiz\OpenId\Authentication\Provider;

use RZ\Roadiz\OpenId\Authentication\JwtAccountToken;

final class ChainJwtRoleStrategy implements JwtRoleStrategy
{
    /**
     * @var array<JwtRoleStrategy>
     */
    private $strategies = [];

    /**
     * ChainJwtRoleStrategy constructor.
     *
     * @param array $strategies
     */
    public function __construct(array $strategies)
    {
        $this->strategies = $strategies;
        foreach ($this->strategies as $strategy) {
            if (!($strategy instanceof JwtRoleStrategy)) {
                throw new \InvalidArgumentException('Strategy must implement ' . JwtRoleStrategy::class);
            }
        }
    }

    public function supports(JwtAccountToken $token): bool
    {
        /** @var JwtRoleStrategy $strategy */
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($token)) {
                return true;
            }
        }
        return false;
    }

    public function getRoles(JwtAccountToken $token): ?array
    {
        /** @var JwtRoleStrategy $strategy */
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($token)) {
                return $strategy->getRoles($token);
            }
        }
        return null;
    }
}
