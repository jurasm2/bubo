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
     * Return values for media data types (mediaGallery, mediaFile)
     * @param string $realName
     * @param array $extensionConfig
     * @param array|null $args
     * @param bool $isEntityParam
     * @return type
     */
    public function getExt($realName, array $extensionConfig, $args = NULL, $isEntityParam = FALSE)
    {
        $retValue = NULL;

        $data = $this->_getData($realName, $isEntityParam);

        if ($data !== NULL) {
            switch ($extensionConfig['type']) {
                case 'label':
                    $jsonData = json_decode($data, TRUE);
                    $retValue = $jsonData['mediaId'];

                    if ($args !== NULL) {
                        $galleryTemplateComponent = isset($args[0]) ? $args[0] : 'defaultGallery';
                        $code = "{control ".$galleryTemplateComponent." '".$retValue."', \$_page, '".$extensionConfig['mode']."'}";
                        $retValue = $this->page->avelanche($code);
                    }

                    if ($retValue === NULL && $args !== NULL) {
                        $retValue = new \Bubo\Media\TemplateContainers\MediaFile(NULL);
                    }
                    break;
                case 'mediaFile':
                    $jsonData = json_decode($data, TRUE);
                    $fileId = $jsonData['mediaId'];
                    $mode = isset($extensionConfig['mode']) ? $extensionConfig['mode'] : NULL;
                    $retValue = $this->page->presenter->mediaManagerService->loadFile($fileId, $mode);
                    break;
                default:
                    $retValue = $data;
            }
        }
        return $retValue;

    }



}