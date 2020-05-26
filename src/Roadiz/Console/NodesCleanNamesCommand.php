<?php
declare(strict_types=1);

namespace RZ\Roadiz\Console;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Utils\Node\NodeNameChecker;
use RZ\Roadiz\Utils\StringHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class NodesCleanNamesCommand
 * @package RZ\Roadiz\Console
 */
class NodesCleanNamesCommand extends Command
{
    /** @var  EntityManager */
    private $entityManager;

    protected function configure()
    {
        $this->setName('nodes:clean-names')
            ->setDescription('Clean every nodes names according to their default node-source title.')
            ->addOption(
                'use-date',
                null,
                InputOption::VALUE_NONE,
                'Use date instead of uniqid.'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Do nothing, only print information.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->entityManager = $this->getHelper('entityManager')->getEntityManager();
        $io = new SymfonyStyle($input, $output);

        $translation = $this->entityManager
            ->getRepository(Translation::class)
            ->findDefault();

        if (null !== $translation) {
            $nodes = $this->entityManager
                ->getRepository(Node::class)
                ->setDisplayingNotPublishedNodes(true)
                ->findBy([
                    'dynamicNodeName' => true,
                    'locked' => false,
                    'translation' => $translation,
                ]);

            $io->note(
                'This command will rename EVERY nodes (except for locked and not dynamic ones) names according to their node-source for current default translation.' . PHP_EOL .
                count($nodes) . ' nodes might be affected.'
            );

            $question1 = new ConfirmationQuestion('<question>Are you sure to proceed? This could break many page URLs!</question>', false);

            if ($io->askQuestion($question1)) {
                $io->note('Renaming ' . count($nodes) . ' nodes…');
                $renameCount = 0;
                $names = [];

                /** @var Node $node */
                foreach ($nodes as $node) {
                    $nodeSource = $node->getNodeSources()->first();
                    $prefixName = $nodeSource->getTitle() != "" ?
                        $nodeSource->getTitle() :
                        $node->getNodeName();

                    $prefixNameSlug = StringHandler::slugify($prefixName);
                    /*
                     * Proceed to rename only if best name is not the current
                     * node-name AND if it is not ALREADY suffixed with a unique ID.
                     */
                    /** @var NodeNameChecker $nodeNameChecker */
                    $nodeNameChecker = $this->getHelper('kernel')->getKernel()->get('utils.nodeNameChecker');
                    if ($prefixNameSlug != $node->getNodeName() &&
                        $nodeNameChecker->isNodeNameValid($prefixNameSlug) &&
                        !$nodeNameChecker->isNodeNameWithUniqId($prefixNameSlug, $nodeSource->getNode()->getNodeName())) {
                        $alreadyUsed = $nodeNameChecker->isNodeNameAlreadyUsed($prefixName);
                        if (!$alreadyUsed) {
                            $names[] = [
                                $node->getNodeName(),
                                $prefixNameSlug
                            ];
                            $node->setNodeName($prefixName);
                        } else {
                            if ($input->getOption('use-date') && $node->getNodeSources()->first() && null !== $node->getNodeSources()->first()->getPublishedAt()) {
                                $suffixedNameSlug = $prefixNameSlug . '-' . $node->getNodeSources()->first()->getPublishedAt()->format('Y-m-d');
                            } else {
                                $suffixedNameSlug = $prefixNameSlug . '-' . uniqid();
                            }
                            if (!$nodeNameChecker->isNodeNameAlreadyUsed($suffixedNameSlug)) {
                                $names[] = [
                                    $node->getNodeName(),
                                    $suffixedNameSlug
                                ];
                                $node->setNodeName($suffixedNameSlug);
                            } else {
                                $suffixedNameSlug = $prefixNameSlug . '-' . uniqid();
                                $names[] = [
                                    $node->getNodeName(),
                                    $suffixedNameSlug
                                ];
                                $node->setNodeName($suffixedNameSlug);
                            }
                        }
                        if (!$input->getOption('dry-run')) {
                            $this->entityManager->flush();
                        }
                        $renameCount++;
                    }
                }

                $io->table(['Old name', 'New name'], $names);

                if (!$input->getOption('dry-run')) {
                    $io->success('Renaming done! ' . $renameCount . ' nodes have been affected. Do not forget to reindex your Solr documents if you are using it.');
                } else {
                    $io->success($renameCount . ' nodes would have been affected. Nothing was saved to database.');
                }
            } else {
                $io->warning('Renaming cancelled…');
                return 1;
            }
        }

        return 0;
    }
}
