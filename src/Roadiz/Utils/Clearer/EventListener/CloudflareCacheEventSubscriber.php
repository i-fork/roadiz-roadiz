<?php
declare(strict_types=1);

namespace RZ\Roadiz\Utils\Clearer\EventListener;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Pimple\Container;
use RZ\Roadiz\Core\Events\Cache\CachePurgeRequestEvent;
use RZ\Roadiz\Core\Events\NodesSources\NodesSourcesUpdatedEvent;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CloudflareCacheEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * ReverseProxyCacheEventSubscriber constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            CachePurgeRequestEvent::class => ['onBanRequest', 3],
            NodesSourcesUpdatedEvent::class => ['onPurgeRequest', 3],
        ];
    }

    /**
     * @return bool
     */
    protected function supportConfig()
    {
        return isset($this->container['config']['reverseProxyCache']['cloudflare']) &&
            isset($this->container['config']['reverseProxyCache']['cloudflare']['zone']) &&
            (isset($this->container['config']['reverseProxyCache']['cloudflare']['bearer']) ||
            (isset($this->container['config']['reverseProxyCache']['cloudflare']['email']) &&
            isset($this->container['config']['reverseProxyCache']['cloudflare']['key'])));
    }

    /**
     * @param CachePurgeRequestEvent $event
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return void
     */
    public function onBanRequest(CachePurgeRequestEvent $event)
    {
        if (!$this->supportConfig()) {
            return;
        }
        try {
            $request = $this->createBanRequest();
            (new Client())->send($request, [
                'debug' => $event->getKernel()->isDebug(),
                'timeout' => $this->getConf()['timeout']
            ]);
            $event->addMessage(
                'Cloudflare cache cleared.',
                static::class,
                'Cloudflare proxy cache'
            );
        } catch (ClientException $e) {
            if (null !== $e->getResponse()) {
                $data = json_decode($e->getResponse()->getBody()->getContents(), true);
                $event->addError(
                    $data['errors'][0]['message'] ?? $e->getMessage(),
                    static::class,
                    'Cloudflare proxy cache'
                );
            } else {
                $event->addError(
                    $e->getMessage(),
                    static::class,
                    'Cloudflare proxy cache'
                );
            }
        } catch (ConnectException $e) {
            $event->addError(
                $e->getMessage(),
                static::class,
                'Cloudflare proxy cache'
            );
        }
    }

    /**
     * @param NodesSourcesUpdatedEvent $event
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onPurgeRequest(NodesSourcesUpdatedEvent $event)
    {
        if (!$this->supportConfig()) {
            return;
        }

        try {
            /** @var UrlGeneratorInterface $urlGenerator */
            $urlGenerator = $this->container['router'];
            $nodeSource = $event->getNodeSource();
            while (!$nodeSource->getNode()->getNodeType()->isReachable()) {
                $nodeSource = $nodeSource->getParent();
                if (null === $nodeSource) {
                    return;
                }
            }

            $purgeRequest = $this->createPurgeRequest([$urlGenerator->generate(
                RouteObjectInterface::OBJECT_BASED_ROUTE_NAME,
                [
                    RouteObjectInterface::ROUTE_OBJECT => $nodeSource,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            )]);
            (new Client())->send($purgeRequest, [
                'debug' => false,
                'timeout' => $this->getConf()['timeout']
            ]);
        } catch (ClientException $e) {
            // do nothing
        }
    }

    /**
     * @return array|null
     */
    protected function getConf(): ?array
    {
        return $this->container['config']['reverseProxyCache']['cloudflare'] ?? null;
    }

    /**
     * @param array $body
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function createRequest(array $body): \GuzzleHttp\Psr7\Request
    {
        $headers = [
            'Content-type' => 'application/json',
        ];
        if (isset($this->getConf()['bearer'])) {
            $headers['Authorization'] = 'Bearer '.trim((string) $this->getConf()['bearer']);
        }
        if (isset($this->getConf()['email']) && isset($this->getConf()['key'])) {
            $headers['X-Auth-Email'] = $this->getConf()['email'];
            $headers['X-Auth-Key'] = $this->getConf()['key'];
        }
        return new \GuzzleHttp\Psr7\Request(
            'POST',
            'https://api.cloudflare.com/client/'.$this->getConf()['version'].'/zones/'.$this->getConf()['zone'].'/purge_cache',
            $headers,
            json_encode($body)
        );
    }

    /**
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function createBanRequest()
    {
        return $this->createRequest([
            'purge_everything' => true,
        ]);
    }

    /**
     * @param string[] $uris
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function createPurgeRequest(array $uris = [])
    {
        return $this->createRequest([
            'files' => $uris
        ]);
    }
}
