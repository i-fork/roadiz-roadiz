<?php
declare(strict_types=1);

namespace RZ\Roadiz\Core\Viewers;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use RZ\Roadiz\Core\Bags\Settings;
use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Repositories\TranslationRepository;
use RZ\Roadiz\Core\Routing\RouteHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

/**
 * TranslationViewer
 */
class TranslationViewer
{
    /**
     * @var bool
     */
    private $preview;
    /**
     * @var Settings
     */
    private $settingsBag;
    /**
     * @var EntityManager
     */
    private $entityManager;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var Translation
     */
    private $translation;

    /**
     * TranslationViewer constructor.
     *
     * @param EntityManager $entityManager
     * @param Settings $settingsBag
     * @param RouterInterface $router
     * @param boolean $preview
     */
    public function __construct(
        EntityManager $entityManager,
        Settings $settingsBag,
        RouterInterface $router,
        $preview = false
    ) {
        $this->settingsBag = $settingsBag;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->preview = $preview;
    }

    /**
     * @return TranslationRepository<Translation>
     */
    public function getRepository()
    {
        return $this->entityManager->getRepository(Translation::class);
    }

    /**
     * Return available page translation information.
     *
     * Be careful, for static routes Roadiz will generate a localized
     * route identifier suffixed with "Locale" text. In case of "force_locale"
     * setting to true, Roadiz will always use suffixed route.
     *
     * ## example return value
     *
     *     array (size=3)
     *       'en' =>
     *         array (size=4)
     *             'name' => string 'newsPage'
     *             'url' => string 'http://localhost/news/test'
     *             'locale' => string 'en'
     *             'active' => boolean false
     *             'translation' => string 'English'
     *       'fr' =>
     *         array (size=4)
     *             'name' => string 'newsPageLocale'
     *             'url' => string 'http://localhost/fr/news/test'
     *             'locale' => string 'fr'
     *             'active' => boolean true
     *             'translation' => string 'French'
     *       'es' =>
     *         array (size=4)
     *             'name' => string 'newsPageLocale'
     *             'url' => string 'http://localhost/es/news/test'
     *             'locale' => string 'es'
     *             'active' => boolean false
     *             'translation' => string 'Spanish'
     *
     * @param Request $request
     * @param boolean $absolute Generate absolute url or relative paths
     *
     * @return array
     * @throws ORMException
     */
    public function getTranslationMenuAssignation(Request $request, $absolute = false)
    {
        $attr = $request->attributes->all();
        $query = $request->query->all();
        $name = '';
        $forceLocale = (boolean) $this->settingsBag->get('force_locale');

        /*
         * Fix absolute boolean to Int constant.
         */
        $absolute = $absolute ? Router::ABSOLUTE_URL : Router::ABSOLUTE_PATH;

        /** @var Node $node */
        if (key_exists('node', $attr) && $attr['node'] instanceof Node) {
            $node = $attr["node"];
            $this->entityManager->refresh($node);
        } else {
            $node = null;
        }
        /*
         * If using a static route (routes.yml)…
         */
        if (!empty($attr['_route']) && is_string($attr['_route'])) {
            $translations = $this->getRepository()->findAllAvailable();
            /*
             * Search for a route without Locale suffix
             */
            $baseRoute = RouteHandler::getBaseRoute($attr["_route"]);
            if (null !== $this->router->getRouteCollection()->get($baseRoute)) {
                $attr["_route"] = $baseRoute;
            }
        } elseif (null !== $node) {
            /*
             * If using dynamic routing…
             */
            if ($this->preview === true) {
                $translations = $this->getRepository()->findAvailableTranslationsForNode($node);
            } else {
                $translations = $this->getRepository()->findStrictlyAvailableTranslationsForNode($node);
            }
            $name = "node";
        } else {
            return [];
        }

        $return = [];

        foreach ($translations as $translation) {
            $url = null;
            /*
             * Remove existing _locale in query string
             */
            if (key_exists('_locale', $query)) {
                unset($query["_locale"]);
            }
            /*
             * Remove existing page parameter in query string
             * if listing is different between 2 languages, maybe
             * page 2 or 3 does not exist in language B but exists in
             * language A
             */
            if (key_exists('page', $query)) {
                unset($query['page']);
            }

            if (!empty($attr['_route']) && is_string($attr['_route'])) {
                $name = $attr['_route'];
                /*
                 * Use suffixed route if locales are forced or
                 * if it’s not default translation.
                 */
                if (true === $forceLocale || !$translation->isDefaultTranslation()) {
                    /*
                     * Search for a Locale suffixed route
                     */
                    if (null !== $this->router->getRouteCollection()->get($attr['_route'] . "Locale")) {
                        $name = $attr['_route'] . 'Locale';
                    }

                    $attr['_route_params']['_locale'] = $translation->getPreferredLocale();
                } else {
                    if (key_exists('_locale', $attr['_route_params'])) {
                        unset($attr['_route_params']['_locale']);
                    }
                }

                /*
                 * Remove existing page parameter in route parameters
                 * if listing is different between 2 languages, maybe
                 * page 2 or 3 does not exist in language B but exists in
                 * language A
                 */
                if (key_exists('page', $attr['_route_params'])) {
                    unset($attr['_route_params']['page']);
                }

                $url = $this->router->generate(
                    $name,
                    array_merge($attr['_route_params'], $query),
                    $absolute
                );
            } elseif ($node) {
                $nodesSources = $node->getNodeSourcesByTranslation($translation)->first();
                if (null !== $nodesSources && false !== $nodesSources) {
                    $url = $this->router->generate(
                        $nodesSources,
                        $query,
                        $absolute
                    );
                }
            }

            if (null !== $url) {
                $return[$translation->getPreferredLocale()] = [
                    'name' => $name,
                    'url' => $url,
                    'locale' => $translation->getPreferredLocale(),
                    'active' => $this->translation->getPreferredLocale() == $translation->getPreferredLocale(),
                    'translation' => $translation->getName(),
                ];
            }
        }
        return $return;
    }

    /**
     * @return Translation
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    /**
     * @param Translation $translation
     * @return TranslationViewer
     */
    public function setTranslation($translation)
    {
        $this->translation = $translation;
        return $this;
    }
}
