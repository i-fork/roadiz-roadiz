<?php
declare(strict_types=1);

namespace RZ\Roadiz\Utils\DebugBar\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Locale;

final class LocaleCollector extends DataCollector implements Renderable
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * LocaleCollector constructor.
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @inheritDoc
     */
    function collect()
    {
        return [
            'locale' => $this->requestStack->getMasterRequest()->getLocale() .
                ' (' .
                Locale::getDisplayName($this->requestStack->getMasterRequest()->getLocale(), 'en') .
                ')',
        ];
    }

    /**
     * @inheritDoc
     */
    function getName()
    {
        return 'locale';
    }

    /**
     * @inheritDoc
     */
    public function getWidgets()
    {
        return [
            'current.locale' => [
                'icon' => 'flag',
                'map' => 'locale.locale',
                'default' => '',
            ]
        ];
    }
}
