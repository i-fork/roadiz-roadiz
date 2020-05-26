<?php
declare(strict_types=1);

namespace RZ\Roadiz\Console\Tools;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Config\YamlConfigurationHandler;
use RZ\Roadiz\Core\Entities\Group;
use RZ\Roadiz\Core\Entities\NodeTypeField;
use RZ\Roadiz\Core\Entities\Role;
use RZ\Roadiz\Core\Entities\Setting;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Entities\User;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fixtures class
 */
class Fixtures
{
    protected $entityManager;
    protected $request;
    protected $cacheDir;
    protected $debug;
    protected $configPath;
    private $rootDir;

    /**
     * @param EntityManager $entityManager
     * @param string $cacheDir
     * @param string $configPath
     * @param string $rootDir
     * @param boolean $debug
     * @param Request|null $request
     */
    public function __construct(
        EntityManager $entityManager,
        $cacheDir,
        $configPath,
        $rootDir,
        $debug = true,
        Request $request = null
    ) {
        $this->entityManager = $entityManager;
        $this->request = $request;
        $this->cacheDir = $cacheDir;
        $this->debug = $debug;
        $this->configPath = $configPath;
        $this->rootDir = $rootDir;
    }

    /**
     * @return void
     */
    public function installFixtures()
    {
        $this->installDefaultTranslation();
        $this->entityManager->flush();
        $this->clearResultCache();
    }

    protected function clearResultCache()
    {
        $cacheDriver = $this->entityManager->getConfiguration()->getResultCacheImpl();
        if ($cacheDriver !== null && $cacheDriver instanceof CacheProvider) {
            $cacheDriver->deleteAll();
        }
    }

    /**
     * @return void
     */
    public function createFolders()
    {
        $fs = new Filesystem();

        $folders = [
            $this->rootDir . '/cache',
            $this->rootDir . '/gen-src/Compiled',
            $this->rootDir . '/gen-src/Proxies',
            $this->rootDir . '/gen-src/GeneratedNodeSources',
        ];

        foreach ($folders as $folder) {
            if (!$fs->exists($folder)) {
                $fs->mkdir($folder, 0755);
            }
        }
    }

    /**
     * @return void
     */
    protected function installDefaultTranslation()
    {
        $existing = $this->entityManager
                         ->getRepository(Translation::class)
                         ->findOneBy(['defaultTranslation' => true, 'available' => true]);

        if (null === $existing) {
            $translation = new Translation();

            /*
             * Create a translation according to
             * current language
             */
            if (null !== $this->request) {
                $translation->setLocale($this->request->getLocale());
            } else {
                $translation->setLocale('en');
            }

            $translation->setDefaultTranslation(true);
            $translation->setName(Translation::$availableLocales[$translation->getLocale()]);
            $translation->setAvailable(true);

            $this->entityManager->persist($translation);
        }
    }

    /**
     * @param array $data
     * @return boolean
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function createDefaultUser($data)
    {
        $existing = $this->entityManager
                         ->getRepository(User::class)
                         ->findOneBy(['username' => $data['username'], 'email' => $data['email']]);

        if ($existing === null) {
            $user = new User();
            $user->setUsername($data['username']);
            $user->setPlainPassword($data['password']);
            $user->setEmail($data['email']);
            $user->setPictureUrl($user->getGravatarUrl());

            $existingGroup = $this->entityManager
                                  ->getRepository(Group::class)
                                  ->findOneByName('Admin');
            $user->addGroup($existingGroup);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        return true;
    }

    /**
     * Get role by name, and create it if does not exist.
     * @param string $roleName
     *
     * @return Role
     */
    protected function getRole($roleName = Role::ROLE_SUPERADMIN)
    {
        $role = $this->entityManager
                     ->getRepository(Role::class)
                     ->findOneBy(['name' => $roleName]);

        if ($role === null) {
            $role = new Role($roleName);
            $this->entityManager->persist($role);
            $this->entityManager->flush();
        }

        return $role;
    }
    /**
     * Get role by name, and create it if does not exist.
     * @param string $name
     *
     * @return \RZ\Roadiz\Core\Entities\Setting
     */
    protected function getSetting($name)
    {
        $setting = $this->entityManager
                        ->getRepository(Setting::class)
                        ->findOneBy(['name' => $name]);

        if (null === $setting) {
            $setting = new Setting();
            $setting->setName($name);
            $this->entityManager->persist($setting);
            $this->entityManager->flush();
        }

        return $setting;
    }

    /**
     * @param array $data
     *
     * @return void
     */
    public function saveInformations($data)
    {
        /*
         * Save settings
         */
        $set1 = $this->getSetting('site_name');
        $set1->setValue($data['site_name']);
        $set1->setType(NodeTypeField::STRING_T);

        $set2 = $this->getSetting('email_sender');
        $set2->setValue($data['email_sender']);
        $set2->setType(NodeTypeField::EMAIL_T);

        $set2 = $this->getSetting('email_sender_name');
        $set2->setValue($data['email_sender_name']);
        $set2->setType(NodeTypeField::STRING_T);

        $set2 = $this->getSetting('seo_description');
        $set2->setValue($data['seo_description']);
        $set2->setType(NodeTypeField::TEXT_T);

        $set2 = $this->getSetting('display_debug_panel');
        $set2->setValue(false);
        $set2->setType(NodeTypeField::BOOLEAN_T);

        $this->entityManager->flush();

        /*
         * Update timezone
         */
        if (!empty($data['timezone'])) {
            $conf = new YamlConfigurationHandler(
                $this->cacheDir,
                $this->debug,
                $this->configPath
            );

            $config = $conf->load();
            $config['timezone'] = $data['timezone'];

            $conf->setConfiguration($config);
            $conf->writeConfiguration();
        }
    }
}
