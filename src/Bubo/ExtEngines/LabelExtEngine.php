<?php

namespace Bubo\ExtEngines;

/**
 * Ext engine resposible for media types retireval
 * @author Marek Juras
 */
class LabelExtEngine extends BaseExtEngine {

    /**
     *
     * @param string $realName
     * @param bool $isEntityParam
     * @return string (json)
     */
    private function _getData($realName, $isEntityParam)
    {
        $data = NULL;
        if ($isEntityParam) {
            $data = $this->page->data[$realName];
        } else {
            $x = $this->page->data['labels'];
            $_d = reset($x);
            if (isset($_d['ext_identifier'][$realName])) {
                $data = $_d['ext_identifier'][$realName]['ext_value'];
            }
        }
        return $data;
    }

    /**
     * Return page binded through label extension
     * @param string $realName
     * @param array $extensionConfig
     * @param array|null $args
     * @param bool $isEntityParam
     * @return AbstractPage|NULL
     */
    public function getExt($realName, array $extensionConfig, $args = NULL, $isEntityParam = FALSE)
    {
        $retValue = NULL;

        // data represents tree node id of the page
        $data = $this->_getData($realName, $isEntityParam);

        if ($data) {
            $loadPageParams = array(
                'treeNodeId' => $data,
                'lang' => $this->page->presenter->lang ?: $this->page->presenter->langManagerService->getDefaultLanguage(),
            );
            $retValue = $this->page->presenter->pageManagerService->getPage($loadPageParams);
        } else {
            $retValue = NULL;
        }

        return $retValue;
    }



}