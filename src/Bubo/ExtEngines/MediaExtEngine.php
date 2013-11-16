<?php

namespace Bubo\ExtEngines;

use Nette,
    Nette\Utils\Strings;

/**
 * 
 * @author Marek Juras
 */
class MediaExtEngine extends BaseExtEngine {

    private function _getData($realName, $isEntityParam) {
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
    
    public function getExt($realName, $extensionConfig, $args = NULL, $isEntityParam = FALSE) {
        $retValue = NULL;
        
        $data = $this->_getData($realName, $isEntityParam);
        
//        if ($isEntityParam) {
//            dump($this->_getData($realName, $extensionConfig, $isEntityParam));
//            die();
//        }
        
        if ($data !== NULL) {
            switch ($extensionConfig['type']) {
                case 'mediaGallery':
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
        
//        $x = $this->page->data['labels'];
//                    $_d = reset($x);
//
//                    if (isset($_d['ext_identifier'][$realName])) {
//
//                        switch ($extensionConfig['type']) {
//                            case 'mediaFile':
//                                $id = $_d['ext_identifier'][$realName]['ext_value'];
//                                                                
//                                // autodetect mediaFile - file or gallery?
//                                if (preg_match('#([[:alnum:]]+)\-([0-9]+)#', $id, $matches)) {
//                                    switch ($matches[1]) {
//                                        case 'file':
////                                            dump('tu');
////                                            die();
//                                            $retValue = $id;
//                                            if ($args !== NULL) {
//                                                $retValue = $this->page->presenter->mediaManagerService->loadFile($matches[2], $extensionConfig);
////                                                dump($retValue);
////                                                die();
//                                            }
//                                            break;
//                                        case 'gallery':
//                                            $retValue = $id;
//                                            if ($args !== NULL) {
//                                                $code = "{control ".$args[0]." '".$retValue."', \$_page, '".$extensionConfig['mode']."'}";
//                                                $retValue = $this->page->avelanche($code);
//                                            }
//                                            break;
//                                    }
//                                }
//                                
//                                if ($retValue === NULL && $args !== NULL) {
//                                    $retValue = new \Bubo\Media\TemplateContainers\MediaFile(NULL);
//                                }
//                                
//                                
//                                break;
//                            case 'mediaGallery':
//                                $id = $_d['ext_identifier'][$realName]['ext_value'];
//                                    
//                                $jsonData = json_decode($id, TRUE);
//                                
////                                dump($jsonData);
////                                die();
//                                
//                                // autodetect mediaFile - file or gallery?
//                                //if (preg_match('#([[:alnum:]]+)\-([0-9]+)#', $id, $matches)) {
//                                    switch ($jsonData['mediaType']) {
//                                        case 'file':
////                                            dump('tu');
////                                            die();
//                                            $retValue = $jsonData['mediaId'];
//                                            if ($args !== NULL) {
//                                                $retValue = $this->page->presenter->mediaManagerService->loadFile($jsonData['mediaId'], $extensionConfig);
////                                                dump($retValue);
////                                                die();
//                                            }
//                                            break;
//                                        case 'gallery':
//                                            $retValue = $jsonData['mediaId'];
//                                            if ($args !== NULL) {
//                                                $code = "{control ".$args[0]." '".$retValue."', \$_page, '".$extensionConfig['mode']."'}";
//                                                $retValue = $this->page->avelanche($code);
//                                            }
//                                            break;
//                                    }
//                                //}
//                                
//                                if ($retValue === NULL && $args !== NULL) {
//                                    $retValue = new \Bubo\Media\TemplateContainers\MediaFile(NULL);
//                                }
//                                break;
//                            default:
//                                $retValue = $_d['ext_identifier'][$realName]['ext_value'];
//                                break;
//                        }
//                    }
                    
                    return $retValue;
        
    }
    
    

}