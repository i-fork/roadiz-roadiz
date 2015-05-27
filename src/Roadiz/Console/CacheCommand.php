<?php
/**
 * Copyright © 2014, Ambroise Maupate and Julien Blanchet
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
 * @file CacheCommand.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Console;

use RZ\Roadiz\Utils\Clearer\AssetsClearer;
use RZ\Roadiz\Utils\Clearer\DoctrineCacheClearer;
use RZ\Roadiz\Utils\Clearer\RoutingCacheClearer;
use RZ\Roadiz\Utils\Clearer\TemplatesCacheClearer;
use RZ\Roadiz\Utils\Clearer\TranslationsCacheClearer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command line utils for managing Cache from terminal.
 */
class CacheCommand extends Command
{
    private $entityManager;

    protected function configure()
    {
        $this->setName('cache')
             ->setDescription('Manage cache and compiled data.')
             ->addOption(
                 'infos',
                 null,
                 InputOption::VALUE_NONE,
                 'Get informations about caches.'
             )
             ->addOption(
                 'clear-doctrine',
                 null,
                 InputOption::VALUE_NONE,
                 'Clear doctrine metadata cache and entities proxies.'
             )
             ->addOption(
                 'clear-routes',
                 null,
                 InputOption::VALUE_NONE,
                 'Clear compiled route collections.'
             )
             ->addOption(
                 'clear-assets',
                 null,
                 InputOption::VALUE_NONE,
                 'Clear compiled route collections'
             )
             ->addOption(
                 'clear-templates',
                 null,
                 InputOption::VALUE_NONE,
                 'Clear compiled Twig templates.'
             )
             ->addOption(
                 'clear-translations',
                 null,
                 InputOption::VALUE_NONE,
                 'Clear compiled translations catalogues.'
             )
             ->addOption(
                 'clear-all',
                 null,
                 InputOption::VALUE_NONE,
                 'Clear all caches (Doctrine, proxies, routes, templates, assets and translations)'
             )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $text = "";
        $this->entityManager = $this->getHelperSet()->get('em')->getEntityManager();

        $assetsClearer = new AssetsClearer();
        $doctrineClearer = new DoctrineCacheClearer($this->entityManager);
        $routingClearer = new RoutingCacheClearer();
        $templatesClearer = new TemplatesCacheClearer();
        $translationsClearer = new TranslationsCacheClearer();

        $clearers = [
            $assetsClearer,
            $doctrineClearer,
            $routingClearer,
            $templatesClearer,
            $translationsClearer,
        ];

        if ($input->getOption('clear-all')) {
            foreach ($clearers as $clearer) {
                $clearer->clear();
                $text .= $clearer->getOutput();
            }

            $text .= '<info>All caches have been been purged…</info>' . PHP_EOL;
        } elseif ($input->getOption('clear-doctrine')) {
            $doctrineClearer->clear();
            $text .= $doctrineClearer->getOutput();
        } elseif ($input->getOption('clear-routes')) {
            $routingClearer->clear();
            $text .= $routingClearer->getOutput();
        } elseif ($input->getOption('clear-assets')) {
            $assetsClearer->clear();
            $text .= $assetsClearer->getOutput();
        } elseif ($input->getOption('clear-templates')) {
            $templatesClearer->clear();
            $text .= $templatesClearer->getOutput();
        } elseif ($input->getOption('clear-translations')) {
            $translationsClearer->clear();
            $text .= $translationsClearer->getOutput();
        } else {
            $text .= $this->getInformations();
        }

        $output->writeln($text);
    }

    public function getInformations()
    {
        $text = '';

        $cacheDriver = $this->entityManager->getConfiguration()->getResultCacheImpl();
        if (null !== $cacheDriver) {
            $text .= "<info>Result cache driver:</info> " . get_class($cacheDriver) . PHP_EOL;
        }

        $cacheDriver = $this->entityManager->getConfiguration()->getHydrationCacheImpl();
        if (null !== $cacheDriver) {
            $text .= "<info>Hydratation cache driver:</info> " . get_class($cacheDriver) . PHP_EOL;
        }

        $cacheDriver = $this->entityManager->getConfiguration()->getQueryCacheImpl();
        if (null !== $cacheDriver) {
            $text .= "<info>Query cache driver:</info> " . get_class($cacheDriver) . PHP_EOL;
        }

        $cacheDriver = $this->entityManager->getConfiguration()->getMetadataCacheImpl();
        if (null !== $cacheDriver) {
            $text .= "<info>Metadata cache driver:</info> " . get_class($cacheDriver) . PHP_EOL;
        }

        return $text;
    }
}
