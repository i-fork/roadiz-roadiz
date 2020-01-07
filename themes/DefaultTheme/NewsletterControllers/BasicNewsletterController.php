<?php
declare(strict_types=1);
/**
 * Copyright © 2016, Ambroise Maupate and Julien Blanchet
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
 * @file BasicNewsletterController.php
 * @author Maxime Constantinian
 */
namespace Themes\DefaultTheme\NewsletterControllers;

use RZ\Roadiz\CMS\Controllers\NewsletterRendererInterface;
use RZ\Roadiz\Core\Entities\Newsletter;
use Symfony\Component\HttpFoundation\Request;
use Themes\DefaultTheme\DefaultThemeApp;

/**
 * Class BasicNewsletterController.
 *
 * Class to generate html form BasicNewsletter newsletter nodetype.
 *
 * @package Themes\DefaultTheme\NewsletterControllers
 */
class BasicNewsletterController extends DefaultThemeApp implements NewsletterRendererInterface
{
    /**
     * Generate HTML. The function name makeHtml is important because it will be used
     * by NewsletterUtilsController to get your newsletter HTML body.
     *
     * @param Request $request
     * @param Newsletter $newsletter
     *
     * @return string
     */
    public function makeHtml(Request $request, Newsletter $newsletter): string
    {
        $this->prepareThemeAssignation($newsletter->getNode(), null);
        $this->assignation["nodeSource"] = $newsletter->getNode()->getNodeSources()->first();

        return $this->getTwig()->render('newsletters/basicNewsletter.html.twig', $this->assignation);
    }
}
