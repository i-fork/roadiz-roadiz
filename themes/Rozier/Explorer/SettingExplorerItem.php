<?php
/**
 * Copyright (c) 2017. Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
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
 * @file SettingExplorerItem.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace Themes\Rozier\Explorer;

use RZ\Roadiz\Core\Entities\Setting;

class SettingExplorerItem extends AbstractExplorerItem
{
    /**
     * @var Setting
     */
    private $setting;

    /**
     * SettingExplorerItem constructor.
     * @param Setting $setting
     */
    public function __construct(Setting $setting)
    {
        $this->setting = $setting;
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->setting->getId();
    }

    /**
     * @inheritDoc
     */
    public function getAlternativeDisplayable()
    {
        if (null !== $this->setting->getSettingGroup()) {
            return $this->setting->getSettingGroup()->getName();
        }
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getDisplayable()
    {
        return $this->setting->getName();
    }

    /**
     * @inheritDoc
     */
    public function getOriginal()
    {
        return $this->setting;
    }
}
