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
 *
 *
 *
 * @file CustomFormsController.php
 * @author Ambroise Maupate
 */

namespace Themes\Rozier\Controllers;

use Doctrine\Common\Collections\Collection;
use RZ\Roadiz\Core\Entities\CustomFormAnswer;
use RZ\Roadiz\Core\Entities\CustomFormFieldAttribute;
use Symfony\Component\HttpFoundation\Request;
use Themes\Rozier\RozierApp;

/**
 * CustomForm controller
 */
class CustomFormFieldAttributesController extends RozierApp
{
    /**
     * List every node-types.
     * @param Request $request
     * @param int     $customFormAnswerId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Request $request, $customFormAnswerId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_CUSTOMFORMS');
        /*
         * Manage get request to filter list
         */

        /** @var CustomFormAnswer $customFormAnswer */
        $customFormAnswer = $this->get("em")->find(CustomFormAnswer::class, $customFormAnswerId);
        $answers = $this->getAnswersByGroups($customFormAnswer->getAnswers());

        $this->assignation['fields'] = $answers;
        $this->assignation['answer'] = $customFormAnswer;
        $this->assignation['customFormId'] = $customFormAnswer->getCustomForm()->getId();

        return $this->render('custom-form-field-attributes/list.html.twig', $this->assignation);
    }

    /**
     * @param Collection|array $answers
     * @return array
     */
    protected function getAnswersByGroups($answers)
    {
        $fieldsArray = [];

        /** @var CustomFormFieldAttribute $answer */
        foreach ($answers as $answer) {
            $groupName = $answer->getCustomFormField()->getGroupName();
            if ($groupName != '') {
                if (!isset($fieldsArray[$groupName])) {
                    $fieldsArray[$groupName] = [];
                }
                $fieldsArray[$groupName][] = $answer;
            } else {
                $fieldsArray[] = $answer;
            }
        }

        return $fieldsArray;
    }
}
