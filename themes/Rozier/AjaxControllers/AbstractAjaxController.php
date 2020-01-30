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
 * @file AbstractAjaxController.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\AjaxControllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Themes\Rozier\RozierApp;

/**
 * Extends common back-office controller, but add a request validation
 * to secure Ajax connexions.
 */
abstract class AbstractAjaxController extends RozierApp
{
    protected static $validMethods = [
        Request::METHOD_POST,
        Request::METHOD_GET,
    ];

    /**
     * @param Request $request
     * @param string  $method
     * @param bool    $requestCsrfToken
     *
     * @return boolean  Return true if request is valid, else throw exception
     */
    protected function validateRequest(Request $request, $method = 'POST', $requestCsrfToken = true)
    {
        if ($request->get('_action') == "") {
            throw new BadRequestHttpException('Wrong action requested');
        }

        if ($requestCsrfToken === true) {
            /** @var CsrfTokenManager $tokenManager */
            $tokenManager = $this->get('csrfTokenManager');
            $token = $tokenManager->getToken(static::AJAX_TOKEN_INTENTION);
            if ($token->getValue() !== $request->get('_token')) {
                throw new BadRequestHttpException('Bad CSRF token');
            }
        }

        if (in_array(strtolower($method), static::$validMethods) &&
            strtolower($request->getMethod()) != strtolower($method)) {
            throw new BadRequestHttpException('Bad method');
        }

        return true;
    }

    protected function sortIsh(array &$arr, array $map)
    {
        $return = [];

        while ($element = array_shift($map)) {
            foreach ($arr as $key => $value) {
                if ($element == $value->getId()) {
                    $return[] = $value;
                    unset($arr[$key]);
                    break 1;
                }
            }
        }

        return $return;
    }
}
