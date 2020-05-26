<?php
declare(strict_types=1);

namespace Themes\Rozier\AjaxControllers;

use RZ\Roadiz\Core\Entities\Folder;
use RZ\Roadiz\Core\Entities\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Themes\Rozier\Widgets\FolderTreeWidget;

/**
 * Class AjaxFolderTreeController
 *
 * @package Themes\Rozier\AjaxControllers
 */
class AjaxFolderTreeController extends AbstractAjaxController
{
    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getTreeAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        /** @var FolderTreeWidget|null $folderTree */
        $folderTree = null;

        switch ($request->get("_action")) {
            /*
             * Inner folder edit for folderTree
             */
            case 'requestFolderTree':
                if ($request->get('parentFolderId') > 0) {
                    $folder = $this->get('em')
                                ->find(
                                    Folder::class,
                                    (int) $request->get('parentFolderId')
                                );
                } else {
                    $folder = null;
                }

                $folderTree = new FolderTreeWidget(
                    $this->getRequest(),
                    $this,
                    $folder
                );

                $this->assignation['mainFolderTree'] = false;

                break;
            /*
             * Main panel tree folderTree
             */
            case 'requestMainFolderTree':
                $parent = null;
                if (null !== $this->getUser() && $this->getUser() instanceof User) {
                    $parent = $this->getUser()->getChroot();
                }

                $folderTree = new FolderTreeWidget(
                    $this->getRequest(),
                    $this,
                    $parent
                );
                $this->assignation['mainFolderTree'] = true;
                break;
        }

        $this->assignation['folderTree'] = $folderTree;

        $responseArray = [
            'statusCode' => '200',
            'status' => 'success',
            'folderTree' => $this->getTwig()->render('widgets/folderTree/folderTree.html.twig', $this->assignation),
        ];

        return new JsonResponse(
            $responseArray
        );
    }
}
