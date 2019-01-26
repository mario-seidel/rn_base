<?php
namespace Sys25\RnBase\Frontend\View\Marker;

use Sys25\RnBase\Frontend\Request\RequestInterface;
use Sys25\RnBase\Frontend\View\ViewInterface;
use Sys25\RnBase\Configuration\ConfigurationInterface;
use Sys25\RnBase\Frontend\View\ContextInterface;

/***************************************************************
* Copyright notice
*
* (c) 2007-2019 René Nitzsche <rene@system25.de>
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 */
class BaseView implements ViewInterface
{
    private $pathToTemplates;
    protected $templateFile;

    /**
     * @param RequestInterface $request
     * @return string
     */
    public function render($view, RequestInterface $request)
    {
        $configurations = $request->getConfigurations();
        $this->_init($configurations);
        $templateCode = \tx_rnbase_util_Files::getFileResource($this->getTemplate($view, '.html'));
        if (!strlen($templateCode)) {
            \tx_rnbase::load('tx_rnbase_util_Misc');
            \tx_rnbase_util_Misc::mayday('TEMPLATE NOT FOUND: ' . $this->getTemplate($view, '.html'));
        }

        // Die ViewData bereitstellen
        $viewData = $request->getViewContext();
        // Optional kann schon ein Subpart angegeben werden
        $subpart = $this->getMainSubpart($viewData);
        if (!empty($subpart)) {
            $templateCode = \tx_rnbase_util_Templates::getSubpart($templateCode, $subpart);
            if (!strlen($templateCode)) {
                \tx_rnbase::load('tx_rnbase_util_Misc');
                \tx_rnbase_util_Misc::mayday('SUBPART NOT FOUND: ' . $subpart);
            }
        }

        // disable substitution marker cache
        if ($configurations->getBool($request->getConfId().'_caching.disableSubstCache')) {
            \tx_rnbase_util_Templates::disableSubstCache();
        }

        $out = $this->createOutput($templateCode, $request, $configurations->getFormatter());
        $out = $this->renderPluginData($out, $request);

        $params = array();
        $params['confid'] = $request->getConfId();
        $params['item'] = $request->getViewContext()->offsetGet('item');
        $params['items'] = $request->getViewContext()->offsetGet('items');
        $markerArray = $subpartArray = $wrappedSubpartArray = array();
        \tx_rnbase_util_BaseMarker::callModules(
            $out,
            $markerArray,
            $subpartArray,
            $wrappedSubpartArray,
            $params,
            $configurations->getFormatter()
        );
        $out = \tx_rnbase_util_BaseMarker::substituteMarkerArrayCached(
            $out,
            $markerArray,
            $subpartArray,
            $wrappedSubpartArray
        );

        return $out;
    }

    /**
     * render plugin data and additional flexdata
     *
     * @param string $templateCode
     * @param RequestInterface $request
     * @return string
     */
    protected function renderPluginData(
        $templateCode,
        RequestInterface $request
        ) {
            // check, if there are plugin markers to render
            if (!\tx_rnbase_util_BaseMarker::containsMarker($templateCode, 'PLUGIN_')) {
                return $templateCode;
            }

            $configurations = $request->getConfigurations();
            $confId = $request->getConfId();

            // build the data to render
            $pluginData = array_merge(
                // use the current data (tt_conten) to render
                (array) $configurations->getCObj()->data,
                // add some aditional columns, for example from the flexform od typoscript directly
                $configurations->getExploded(
                    $confId . 'plugin.flexdata.'
                    )
                );
            // check for unused columns
            $ignoreColumns = \tx_rnbase_util_BaseMarker::findUnusedCols(
                $pluginData,
                $templateCode,
                'PLUGIN'
                );
            // create the marker array with the parsed columns
            $markerArray = $configurations->getFormatter()->getItemMarkerArrayWrapped(
                $pluginData,
                $confId . 'plugin.',
                $ignoreColumns,
                'PLUGIN_'
            );

            return \tx_rnbase_util_BaseMarker::substituteMarkerArrayCached($templateCode, $markerArray);
    }

    /**
     * Entry point for child classes
     *
     * @param string $template
     * @param RequestInterface $configurations
     * @param \tx_rnbase_util_FormatUtil $formatter
     */
    protected function createOutput($template, RequestInterface $request, $formatter)
    {
        return $template;
    }

    /**
     * Kindklassen können hier einen Subpart-Marker angeben, der initial als Template
     * verwendet wird.
     * Es wird dann in createOutput nicht mehr das gesamte
     * Template übergeben, sondern nur noch dieser Abschnitt. Außerdem wird sichergestellt,
     * daß dieser Subpart im Template vorhanden ist.
     *
     * @return string like ###MY_MAIN_SUBPART### or FALSE
     */
    protected function getMainSubpart(ContextInterface $viewData)
    {
        $subpart = $subpart = $this->getController()->getConfigurations()->get(
            $this->getController()->getConfId() . 'template.subpart'
            );

        return empty($subpart) ? false : $subpart;
    }

    /**
     * This method is called first.
     *
     * @param ConfigurationInterface $configurations
     */
    protected function _init(ConfigurationInterface $configurations)
    {
    }

    /**
     * Set the path of the template directory
     *
     * You can make use the syntax EXT:myextension/somepath.
     * It will be evaluated to the absolute path by tx_rnbase_util_Files::getFileAbsFileName()
     *
     * @param string path to the directory containing the php templates
     * @return void
     */
    public function setTemplatePath($pathToTemplates)
    {
        $this->pathToTemplates = $pathToTemplates;
    }

    /**
     * Set the path of the template file.
     *
     * You can make use the syntax EXT:myextension/template.php
     *
     * @param string path to the file used as templates
     * @return void
     */
    public function setTemplateFile($templateFile)
    {
        $this->templateFile = $templateFile;
    }

    /**
     * Returns the template to use.
     * If TemplateFile is set, it is preferred. Otherwise
     * the filename is build from pathToTemplates, the templateName and $extension.
     *
     * @param string name of template
     * @param string file extension to use
     * @return string complete filename of template
     */
    public function getTemplate($templateName, $extension = '.php', $forceAbsPath = 0)
    {
        if (strlen($this->templateFile) > 0) {
            return ($forceAbsPath) ? \tx_rnbase_util_Files::getFileAbsFileName($this->templateFile) : $this->templateFile;
        }
        $path = $this->pathToTemplates;
        $path .= substr($path, -1, 1) == '/' ? $templateName : '/' . $templateName;
        $extLen = strlen($extension);
        $path .= substr($path, ($extLen * -1), $extLen) == $extension ? '' : $extension;

        return $path;
    }
}