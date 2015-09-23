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
 * @file AssetsServiceProvider.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Core\Services;

use Pimple\Container;
use AM\InterventionRequest\Configuration;

/**
 * Register assets services for dependency injection container.
 */
class AssetsServiceProvider implements \Pimple\ServiceProviderInterface
{
    /**
     * @param Pimple\Container $container [description]
     */
    public function register(Container $container)
    {
        $container['interventionRequestConfiguration'] = function ($c) {

            $cacheDir = ROADIZ_ROOT . '/cache/rendered';
            if (!file_exists($cacheDir)) {
                mkdir($cacheDir);
            }

            $imageDriver = !empty($c['config']['assetsProcessing']['driver']) ? $c['config']['assetsProcessing']['driver'] : 'gd';
            $defaultQuality = !empty($c['config']['assetsProcessing']['defaultQuality']) ? (int) $c['config']['assetsProcessing']['defaultQuality'] : 90;

            $conf = new Configuration();
            $conf->setCachePath($cacheDir);
            $conf->setImagesPath(ROADIZ_ROOT . '/files');
            $conf->setDriver($imageDriver);
            $conf->setDefaultQuality($defaultQuality);

            return $conf;
        };


        return $container;
    }
}
