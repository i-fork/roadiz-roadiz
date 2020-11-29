<?php
declare(strict_types=1);

namespace RZ\Roadiz\OpenId;

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use RZ\Roadiz\Core\Bags\Settings;
use RZ\Roadiz\JWT\JwtConfigurationFactory;
use RZ\Roadiz\JWT\Validation\Constraint\HostedDomain;
use RZ\Roadiz\JWT\Validation\Constraint\UserInfoEndpoint;

final class OpenIdJwtConfigurationFactory implements JwtConfigurationFactory
{
    /**
     * @var Discovery|null
     */
    protected $discovery;
    /**
     * @var Settings
     */
    protected $settingsBag;
    /**
     * @var bool
     */
    private $verifyUserInfo;

    /**
     * @param Discovery|null $discovery
     * @param Settings $settingsBag
     * @param bool $verifyUserInfo
     */
    public function __construct(?Discovery $discovery, Settings $settingsBag, bool $verifyUserInfo = false)
    {
        $this->discovery = $discovery;
        $this->settingsBag = $settingsBag;
        $this->verifyUserInfo = $verifyUserInfo;
    }

    /**
     * @return Constraint[]
     */
    protected function getValidationConstraints(): array
    {
        $hostedDomain = $this->settingsBag->get('openid_hd', false);
        $validators = [
            new ValidAt(SystemClock::fromSystemTimezone()),
        ];

        if (false !== $this->settingsBag->get('oauth_client_id', false)) {
            $validators[] = new PermittedFor(trim((string) $this->settingsBag->get('oauth_client_id')));
        }

        if (false !== $hostedDomain && !empty(trim((string) $hostedDomain))) {
            $validators[] = new HostedDomain(trim((string) $hostedDomain));
        }

        if (null !== $this->discovery) {
            $validators[] = new IssuedBy($this->discovery->get('issuer'));
            if ($this->verifyUserInfo && !empty($this->discovery->get('userinfo_endpoint'))) {
                $validators[] = new UserInfoEndpoint(trim((string) $this->discovery->get('userinfo_endpoint')));
            }
        }

        return $validators;
    }

    public function create(): Configuration
    {
        $configuration = Configuration::forUnsecuredSigner();
        /*
         * Verify JWT signature if asymmetric crypto is used and if PHP gmp extension is loaded.
         */
        if (null !== $this->discovery &&
            $this->discovery->canVerifySignature() &&
            null !== $pems = $this->discovery->getPems()) {
            if (in_array(
                'RS256',
                $this->discovery->get('id_token_signing_alg_values_supported', [])
            ) && isset($pems[0])) {
                $configuration = Configuration::forAsymmetricSigner(
                    new Sha256(),
                    InMemory::plainText($pems[0]),
                    InMemory::plainText($pems[0])
                );
            }
        }

        $configuration->setValidationConstraints(...$this->getValidationConstraints());
        return $configuration;
    }
}
