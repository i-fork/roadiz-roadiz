<?php
declare(strict_types=1);

namespace Themes\Rozier\Controllers\Documents;

use Exception;
use RZ\Roadiz\Core\AbstractEntities\AbstractEntity;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Core\Entities\DocumentTranslation;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Events\DocumentTranslationUpdatedEvent;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Themes\Rozier\Forms\DocumentTranslationType;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Traits\VersionedControllerTrait;
use Twig\Error\RuntimeError;

/**
 * Class DocumentTranslationsController
 * @package Themes\Rozier\Controllers\Documents
 */
class DocumentTranslationsController extends RozierApp
{
    use VersionedControllerTrait;

    /**
     * @param Request $request
     * @param int     $documentId
     * @param int     $translationId
     *
     * @return Response
     * @throws RuntimeError
     */
    public function editAction(Request $request, $documentId, $translationId = null)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        if (null === $translationId) {
            $translation = $this->get('defaultTranslation');

            $translationId = $translation->getId();
        } else {
            $translation = $this->get('em')
                                ->find(Translation::class, (int) $translationId);
        }

        $this->assignation['available_translations'] = $this->get('em')
             ->getRepository(Translation::class)
             ->findAll();

        /** @var Document $document */
        $document = $this->get('em')
                         ->find(Document::class, (int) $documentId);
        $documentTr = $this->get('em')
                           ->getRepository(DocumentTranslation::class)
                           ->findOneBy(['document' => (int) $documentId, 'translation' => (int) $translationId]);

        if ($documentTr === null && $document !== null && $translation !== null) {
            $documentTr = $this->createDocumentTranslation($document, $translation);
        }

        if ($documentTr !== null && $document !== null) {
            $this->assignation['document'] = $document;
            $this->assignation['translation'] = $translation;
            $this->assignation['documentTr'] = $documentTr;

            /**
             * Versioning
             */
            if ($this->isGranted('ROLE_ACCESS_VERSIONS')) {
                if (null !== $response = $this->handleVersions($request, $documentTr)) {
                    return $response;
                }
            }

            /*
             * Handle main form
             */
            $form = $this->createForm(DocumentTranslationType::class, $documentTr, [
                'referer' => $this->get('requestStack')->getCurrentRequest()->get('referer'),
                'disabled' => $this->isReadOnly,
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->get('em')->flush();
                $msg = $this->getTranslator()->trans('document.translation.%name%.updated', [
                    '%name%' => $document->getFilename(),
                ]);
                $this->publishConfirmMessage($request, $msg);

                $this->get("dispatcher")->dispatch(
                    new DocumentTranslationUpdatedEvent($document)
                );

                $routeParams = [
                    'documentId' => $document->getId(),
                    'translationId' => $translationId,
                ];

                if ($form->get('referer')->getData()) {
                    $routeParams = array_merge($routeParams, [
                        'referer' => $form->get('referer')->getData()
                    ]);
                }

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirect($this->generateUrl(
                    'documentsMetaPage',
                    $routeParams
                ));
            }

            $this->assignation['form'] = $form->createView();
            $this->assignation['readOnly'] = $this->isReadOnly;

            return $this->render('document-translations/edit.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Document    $document    [description]
     * @param Translation $translation [description]
     *
     * @return DocumentTranslation
     */
    protected function createDocumentTranslation(Document $document, Translation $translation)
    {
        $dt = new DocumentTranslation();
        $dt->setDocument($document);
        $dt->setTranslation($translation);

        $this->get('em')->persist($dt);

        return $dt;
    }

    /**
     * Return an deletion form for requested document.
     *
     * @param Request $request
     * @param int     $documentId
     * @param int     $translationId
     *
     * @return Response
     * @throws RuntimeError
     */
    public function deleteAction(Request $request, $documentId, $translationId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS_DELETE');

        $documentTr = $this->get('em')
                           ->getRepository(DocumentTranslation::class)
                           ->findOneBy(['document' => (int) $documentId, 'translation' => (int) $translationId]);
        $document = $this->get('em')
                         ->find(Document::class, (int) $documentId);

        if ($documentTr !== null &&
            $document !== null) {
            $this->assignation['documentTr'] = $documentTr;
            $this->assignation['document'] = $document;
            $form = $this->buildDeleteForm($documentTr);
            $form->handleRequest($request);

            if ($form->isSubmitted() &&
                $form->isValid() &&
                $form->getData()['documentId'] == $documentTr->getId()) {
                try {
                    $this->get('em')->remove($documentTr);
                    $this->get('em')->flush();

                    $msg = $this->getTranslator()->trans(
                        'document.translation.%name%.deleted',
                        ['%name%' => $document->getFilename()]
                    );
                    $this->publishConfirmMessage($request, $msg);
                } catch (Exception $e) {
                    $msg = $this->getTranslator()->trans(
                        'document.translation.%name%.cannot_delete',
                        ['%name%' => $document->getFilename()]
                    );
                    $this->publishErrorMessage($request, $msg);
                }
                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirect($this->generateUrl(
                    'documentsEditPage',
                    ['documentId' => $document->getId()]
                ));
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('document-translations/delete.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param DocumentTranslation $doc
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    private function buildDeleteForm(DocumentTranslation $doc)
    {
        $defaults = [
            'documentTranslationId' => $doc->getId(),
        ];
        $builder = $this->createFormBuilder($defaults)
                        ->add('documentTranslationId', HiddenType::class, [
                            'data' => $doc->getId(),
                            'constraints' => [
                                new NotNull(),
                                new NotBlank(),
                            ],
                        ]);

        return $builder->getForm();
    }

    /**
     * @param AbstractEntity $entity
     * @param Request        $request
     */
    protected function onPostUpdate(AbstractEntity $entity, Request $request): void
    {
        /*
         * Dispatch pre-flush event
         */
        if ($entity instanceof DocumentTranslation) {
            $this->get('em')->flush();
            $msg = $this->getTranslator()->trans('document.translation.%name%.updated', [
                '%name%' => $entity->getDocument()->getFilename(),
            ]);
            $this->publishConfirmMessage($request, $msg);

            $this->get("dispatcher")->dispatch(
                new DocumentTranslationUpdatedEvent($entity->getDocument())
            );
        }
    }

    /**
     * @param AbstractEntity $entity
     *
     * @return Response
     */
    protected function getPostUpdateRedirection(AbstractEntity $entity): ?Response
    {
        if ($entity instanceof DocumentTranslation) {
            $routeParams = [
                'documentId' => $entity->getDocument()->getId(),
                'translationId' => $entity->getTranslation()->getId(),
            ];
            /*
             * Force redirect to avoid resending form when refreshing page
             */
            return $this->redirect($this->generateUrl(
                'documentsMetaPage',
                $routeParams
            ));
        }
        return null;
    }
}
