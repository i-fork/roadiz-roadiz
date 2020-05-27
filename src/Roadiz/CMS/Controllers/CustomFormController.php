<?php
declare(strict_types=1);

namespace RZ\Roadiz\CMS\Controllers;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\CMS\Forms\CustomFormsType;
use RZ\Roadiz\Core\Entities\CustomForm;
use RZ\Roadiz\Core\Entities\CustomFormAnswer;
use RZ\Roadiz\Core\Entities\CustomFormFieldAttribute;
use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;
use RZ\Roadiz\Utils\CustomForm\CustomFormHelper;
use RZ\Roadiz\Utils\EmailManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class CustomFormController extends CmsController
{
    /**
     * @return string
     */
    public function getStaticResourcesUrl()
    {
        return $this->get('assetPackages')->getUrl('/themes/Rozier/static/');
    }

    /**
     * @param Request $request
     * @param int     $customFormId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request, $customFormId)
    {
        /** @var CustomForm $customForm */
        $customForm = $this->get('em')
            ->find(CustomForm::class, $customFormId);

        if (null !== $customForm &&
            $customForm->isFormStillOpen()) {
            $mixed = $this->prepareAndHandleCustomFormAssignation(
                $request,
                $customForm,
                new RedirectResponse(
                    $this->generateUrl(
                        'customFormSentAction',
                        ["customFormId" => $customFormId]
                    )
                )
            );

            if ($mixed instanceof RedirectResponse) {
                $mixed->prepare($request);
                return $mixed->send();
            } else {
                $this->assignation = array_merge($this->assignation, $mixed);

                return $this->render('forms/customForm.html.twig', $this->assignation);
            }
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Request $request
     * @param int     $customFormId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function sentAction(Request $request, $customFormId)
    {
        $customForm = $this->get('em')
            ->find(CustomForm::class, $customFormId);

        if (null !== $customForm) {
            $this->assignation['customForm'] = $customForm;

            return $this->render('forms/customFormSent.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Send an answer form by Email.
     *
     * @param array $assignation
     * @param string|array $receiver
     * @return bool
     * @throws \Exception
     */
    public function sendAnswer(
        $assignation,
        $receiver
    ) {
        /** @var EmailManager $emailManager */
        $emailManager = $this->get('emailManager');
        $emailManager->setAssignation($assignation);
        $emailManager->setEmailTemplate('forms/answerForm.html.twig');
        $emailManager->setEmailPlainTextTemplate('forms/answerForm.txt.twig');
        $emailManager->setSubject($assignation['title']);
        $emailManager->setEmailTitle($assignation['title']);
        $emailManager->setSender($this->get('settingsBag')->get('email_sender'));

        if (null === $receiver || empty($receiver)) {
            $emailManager->setReceiver($this->get('settingsBag')->get('email_sender'));
        } else {
            $emailManager->setReceiver($receiver);
        }

        // Send the message
        return $emailManager->send() > 0;
    }

    /**
     * Add a custom form answer into database.
     *
     * @param array                       $data Data array from POST form
     * @param CustomForm                  $customForm
     * @param \Doctrine\ORM\EntityManager $em
     *
     * @return array $fieldsData
     * @throws \Doctrine\ORM\OptimisticLockException
     * @deprecated Use \RZ\Roadiz\Utils\CustomForm\CustomFormHelper to transform Form to CustomFormAnswer.
     */
    public function addCustomFormAnswer(array $data, CustomForm $customForm, EntityManager $em)
    {
        $now = new \DateTime('NOW');
        $answer = new CustomFormAnswer();
        $answer->setIp($data["ip"]);
        $answer->setSubmittedAt($now);
        $answer->setCustomForm($customForm);

        $fieldsData = [
            ["name" => "ip.address", "value" => $data["ip"]],
            ["name" => "submittedAt", "value" => $now],
        ];

        $em->persist($answer);

        foreach ($customForm->getFields() as $field) {
            $fieldAttr = new CustomFormFieldAttribute();
            $fieldAttr->setCustomFormAnswer($answer);
            $fieldAttr->setCustomFormField($field);

            if (isset($data[$field->getName()])) {
                $fieldValue = $data[$field->getName()];
                if ($fieldValue instanceof \DateTime) {
                    $strDate = $fieldValue->format('Y-m-d H:i:s');

                    $fieldAttr->setValue($strDate);
                    $fieldsData[] = ["name" => $field->getLabel(), "value" => $strDate];
                } elseif (is_array($fieldValue)) {
                    $values = $fieldValue;
                    $values = array_map('trim', $values);
                    $values = array_map('strip_tags', $values);

                    $displayValues = implode(CustomFormHelper::ARRAY_SEPARATOR, $values);
                    $fieldAttr->setValue($displayValues);
                    $fieldsData[] = ["name" => $field->getLabel(), "value" => $displayValues];
                } else {
                    $fieldAttr->setValue(strip_tags($fieldValue));
                    $fieldsData[] = ["name" => $field->getLabel(), "value" => $fieldValue];
                }
            }
            $em->persist($fieldAttr);
        }

        $em->flush();

        return $fieldsData;
    }

    /**
     * @param Request    $request
     * @param CustomForm $customForm
     * @param boolean    $forceExpanded
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    public function buildForm(
        Request $request,
        CustomForm $customForm,
        $forceExpanded = false
    ) {
        $defaults = $request->query->all();
        return $this->createForm(CustomFormsType::class, $defaults, [
            'recaptcha_public_key' => $this->get('settingsBag')->get('recaptcha_public_key'),
            'recaptcha_private_key' => $this->get('settingsBag')->get('recaptcha_private_key'),
            'request' => $request,
            'customForm' => $customForm,
            'forceExpanded' => $forceExpanded,
        ]);
    }

    /**
     * Prepare and handle a CustomForm Form then send a confirm email.
     *
     * * This method will return an assignation **array** if form is not validated.
     *     * customForm
     *     * fields
     *     * form
     * * If form is validated, **RedirectResponse** will be returned.
     *
     * @param Request          $request
     * @param CustomForm       $customFormsEntity
     * @param RedirectResponse $redirection
     * @param boolean          $forceExpanded
     * @param string|null      $emailSender
     *
     * @return array|RedirectResponse
     * @throws \Exception
     */
    public function prepareAndHandleCustomFormAssignation(
        Request $request,
        CustomForm $customFormsEntity,
        RedirectResponse $redirection,
        $forceExpanded = false,
        $emailSender = null
    ) {
        $assignation = [];
        $assignation['customForm'] = $customFormsEntity;
        $assignation['fields'] = $customFormsEntity->getFields();

        $form = $this->buildForm(
            $request,
            $customFormsEntity,
            $forceExpanded
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $helper = new CustomFormHelper($this->get('em'), $customFormsEntity);
                /*
                 * Parse form data and create answer.
                 */
                $answer = $helper->parseAnswerFormData($form, null, $request->getClientIp());

                /*
                 * Prepare field assignation for email content.
                 */
                $assignation["emailFields"] = [
                    ["name" => "ip.address", "value" => $answer->getIp()],
                    ["name" => "submittedAt", "value" => $answer->getSubmittedAt()->format('Y-m-d H:i:s')],
                ];
                $assignation["emailFields"] = array_merge($assignation["emailFields"], $answer->toArray(false));

                $msg = $this->get('translator')->trans(
                    'customForm.%name%.send',
                    ['%name%' => $customFormsEntity->getDisplayName()]
                );

                $this->publishMessage($request, $msg, 'confirm');

                $assignation['title'] = $this->get('translator')->trans(
                    'new.answer.form.%site%',
                    ['%site%' => $customFormsEntity->getDisplayName()]
                );

                if (null !== $emailSender &&
                    false !== filter_var($emailSender, FILTER_VALIDATE_EMAIL)) {
                    $assignation['mailContact'] = $emailSender;
                } else {
                    $assignation['mailContact'] = $this->get('settingsBag')->get('email_sender');
                }

                /*
                 * Send answer notification
                 */
                $receiver = array_filter(
                    array_map('trim', explode(',', $customFormsEntity->getEmail() ?? ''))
                );
                $this->sendAnswer(
                    [
                        'mailContact' => $assignation['mailContact'],
                        'fields' => $assignation["emailFields"],
                        'customForm' => $customFormsEntity,
                        'title' => $this->get('translator')->trans(
                            'new.answer.form.%site%',
                            ['%site%' => $customFormsEntity->getDisplayName()]
                        ),
                    ],
                    $receiver
                );

                return $redirection;
            } catch (EntityAlreadyExistsException $e) {
                $this->publishMessage($request, $e->getMessage(), 'error');
                return $redirection;
            }
        }

        $assignation['form'] = $form->createView();

        return $assignation;
    }
}
