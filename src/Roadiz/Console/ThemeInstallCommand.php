<?php
declare(strict_types=1);
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
 * @file ThemeInstallCommand.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Console;

use Doctrine\ORM\EntityNotFoundException;
use RZ\Roadiz\Attribute\Importer\AttributeImporter;
use RZ\Roadiz\CMS\Importers\EntityImporterInterface;
use RZ\Roadiz\CMS\Importers\GroupsImporter;
use RZ\Roadiz\CMS\Importers\NodesImporter;
use RZ\Roadiz\CMS\Importers\NodeTypesImporter;
use RZ\Roadiz\CMS\Importers\RolesImporter;
use RZ\Roadiz\CMS\Importers\SettingsImporter;
use RZ\Roadiz\CMS\Importers\TagsImporter;
use RZ\Roadiz\Core\ContainerAwareInterface;
use RZ\Roadiz\Core\ContainerAwareTrait;
use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;
use RZ\Roadiz\Utils\Theme\ThemeInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Command line utils for managing themes from terminal.
 */
class ThemeInstallCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var bool
     */
    private $dryRun = false;

    /**
     * @var ThemeInfo
     */
    private $themeInfo;

    /**
     * @var string
     */
    private $themeConfigPath;

    protected function configure()
    {
        $this->setName('themes:install')
            ->setDescription('Manage themes installation')
            ->addArgument(
                'classname',
                InputArgument::REQUIRED,
                'Main theme classname (Use / instead of \\ and do not forget starting slash)'
            )
            ->addOption(
                'data',
                null,
                InputOption::VALUE_NONE,
                'Import default data (node-types, roles, settings and tags)'
            )
            ->addOption(
                'nodes',
                null,
                InputOption::VALUE_NONE,
                'Import nodes data. This cannot be done at the same time with --data option.'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Do nothing, only print information.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('dry-run')) {
            $this->dryRun = true;
        }
        $this->io = new SymfonyStyle($input, $output);

        /*
         * Replace slash by anti-slashes
         */
        $classname = str_replace('/', '\\', $input->getArgument('classname'));
        $this->themeInfo = new ThemeInfo($classname, $this->get('kernel')->getProjectDir());
        $this->themeConfigPath = $this->themeInfo->getThemePath() . '/config.yml';
        if (!$this->themeInfo->isValid()) {
            throw new RuntimeException($this->themeInfo->getClassname() . ' is not a valid Roadiz theme.');
        }
        if (!file_exists($this->themeConfigPath)) {
            $this->io->warning($this->themeInfo->getName() .' theme does not have any configuration.');
            return 1;
        }

        if ($output->isVeryVerbose()) {
            $this->io->writeln('Theme name is: <info>'. $this->themeInfo->getName() .'</info>.');
            $this->io->writeln('Theme assets are located in <info>'. $this->themeInfo->getThemePath() .'/static</info>.');
        }

        if ($input->getOption('data')) {
            $this->importThemeData();
        } elseif ($input->getOption('nodes')) {
            $this->importThemeNodes();
        } else {
            $this->io->note(
                'Roadiz themes are no more registered into database. ' .
                'You should use --data or --nodes option.'
            );
        }
        return 0;
    }

    protected function importThemeData()
    {
        $data = $this->getThemeConfig();

        if (false !== $data && isset($data["importFiles"])) {
            if (isset($data["importFiles"]['groups'])) {
                foreach ($data["importFiles"]['groups'] as $filename) {
                    $this->importFile($filename, $this->get(GroupsImporter::class));
                }
            }
            if (isset($data["importFiles"]['roles'])) {
                foreach ($data["importFiles"]['roles'] as $filename) {
                    $this->importFile($filename, $this->get(RolesImporter::class));
                }
            }
            if (isset($data["importFiles"]['settings'])) {
                foreach ($data["importFiles"]['settings'] as $filename) {
                    $this->importFile($filename, $this->get(SettingsImporter::class));
                }
            }
            if (isset($data["importFiles"]['nodetypes'])) {
                foreach ($data["importFiles"]['nodetypes'] as $filename) {
                    $this->importFile($filename, $this->get(NodeTypesImporter::class));
                }
            }
            if (isset($data["importFiles"]['tags'])) {
                foreach ($data["importFiles"]['tags'] as $filename) {
                    $this->importFile($filename, $this->get(TagsImporter::class));
                }
            }
            if (isset($data["importFiles"]['attributes'])) {
                foreach ($data["importFiles"]['attributes'] as $filename) {
                    $this->importFile($filename, $this->get(AttributeImporter::class));
                }
            }
            if ($this->io->isVeryVerbose()) {
                $this->io->note(
                    'You should do a `bin/roadiz generate:nsentities`' .
                    ' to regenerate your node-types source classes, ' .
                    'and a `bin/roadiz orm:schema-tool:update --dump-sql --force` ' .
                    'to apply your changes into database.'
                );
            }
        } else {
            $this->io->warning('Theme class ' . $this->themeInfo->getClassname() . ' has no data to import.');
        }
    }

    /**
     * @param string                  $filename
     * @param EntityImporterInterface $importer
     */
    protected function importFile(string $filename, EntityImporterInterface $importer): void
    {
        $file = new File($this->themeInfo->getThemePath() . "/" . $filename);
        if (!$this->dryRun) {
            try {
                $importer->import(file_get_contents($file->getPathname()));
                $this->get('em')->flush();
                $this->io->writeln(
                    '* <info>' . $file->getPathname() . '</info> file has been imported.'
                );
                return;
            } catch (EntityAlreadyExistsException $e) {
                $this->io->writeln(
                    '* <info>' . $file->getPathname() . '</info>' .
                    ' <error>has NOT been imported ('.$e->getMessage().')</error>.'
                );
            }
        }
        $this->io->writeln(
            '* <info>' . $file->getPathname() . '</info> file has been imported.'
        );
    }

    protected function importThemeNodes()
    {
        $data = $this->getThemeConfig();

        if (false !== $data && isset($data["importFiles"])) {
            if (isset($data["importFiles"]['nodes'])) {
                foreach ($data["importFiles"]['nodes'] as $filename) {
                    try {
                        $this->importFile($filename, $this->get(NodesImporter::class));
                    } catch (EntityNotFoundException $e) {
                        $this->io->writeln('* <error>' . $e->getMessage() . '</error>');
                    }
                }
            }
        } else {
            $this->io->warning('Theme class ' . $this->themeInfo->getThemeName() . ' has no nodes to import.');
        }
    }

    /**
     * @return array
     */
    protected function getThemeConfig()
    {
        return Yaml::parse(file_get_contents($this->themeConfigPath));
    }
}
