<?php
/**
 * Copyright © 2015, Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file ThemeInstaller.php
 * @author Maxime Constantinian
 */
namespace RZ\Roadiz\Utils\Installer;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Kernel;
use Symfony\Component\Yaml\Yaml;

class ThemeInstaller
{
    /**
     * Get Theme information from its config.yml file.
     *
     * @param string $classname
     * @return array
     */
    public static function getThemeInformation(string $classname)
    {
        $themeFolder = call_user_func([$classname, 'getThemeFolder']);
        $file = $themeFolder . "/config.yml";
        if (file_exists($file)) {
            return Yaml::parse(file_get_contents($file));
        }
        return [
            "name" => 'Theme',
            "versionRequire" => '*',
            "supportedLocale" => [],
            "importFiles" => [],
        ];
    }

    /**
     * Install theme.
     *
     * @param string $classname
     * @param \Doctrine\ORM\EntityManager $em
     *
     * @return bool
     */
    public static function install(string $classname, EntityManager $em)
    {
        $data = static::getThemeInformation($classname);
        $data["className"] = $classname;
        $installedLanguage = $em->getRepository(Translation::class)->findAll();

        /**
         * @var int $key
         * @var Translation $locale
         */
        foreach ($installedLanguage as $key => $locale) {
            $installedLanguage[$key] = $locale->getLocale();
        }

        if (count($data["supportedLocale"]) > 0) {
            $exist = false;
            foreach ($data["supportedLocale"] as $locale) {
                if (in_array($locale, $installedLanguage)) {
                    $exist = true;
                }
            }

            if ($exist === false) {
                $newTranslation = new Translation();
                $newTranslation->setLocale($data["supportedLocale"][0]);
                $newTranslation->setName(Translation::$availableLocales[$data["supportedLocale"][0]]);
                $em->persist($newTranslation);
                $em->flush();
            }
        }

        $importFile = false;
        foreach ($data["importFiles"] as $name => $fileNames) {
            if (!empty($fileNames)) {
                $importFile = true;
                break;
            }
        }
        return $importFile;
    }

    /**
     * Assign summary theme information.
     *
     * @param string $classname
     * @param array $assignation
     * @param string $locale
     *
     * @return array
     */
    public static function assignSummaryInfo($classname, &$assignation, $locale)
    {
        $themeFolder = call_user_func([$classname, 'getThemeFolder']);
        $data = static::getThemeInformation($classname);

        $assignation["theme"] = [
            "name" => $data["name"],
            "version" => $data["versionRequire"],
            "supportedLocale" => $data["supportedLocale"],
            "imports" => $data["importFiles"],
        ];

        $assignation["cms"] = ["version" => Kernel::$cmsVersion];
        $assignation["status"] = [];
        $assignation["status"]["version"] = (version_compare($data["versionRequire"], Kernel::$cmsVersion) <= 0) ? true : false;
        $assignation["cms"]["locale"] = $locale;
        $assignation["status"]["locale"] = in_array($locale, $data["supportedLocale"]);
        $assignation["status"]["import"] = [];

        $assignation['theme']['haveFileImport'] = false;

        foreach ($data["importFiles"] as $name => $filenames) {
            foreach ($filenames as $filename) {
                $assignation["status"]["import"][$filename] = file_exists($themeFolder . "/" . $filename);
                $assignation['theme']['haveFileImport'] = true;
            }
        }

        $assignation['classname'] = $classname;
        return $assignation;
    }
}
