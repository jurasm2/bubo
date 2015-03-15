<?php

namespace Bubo\ExtEngines;

use Bubo;
use Nette\Utils\Strings;

/**
 * Default Ext Engine
 * @author Marek Juras
 */
class DefaultExtEngine extends BaseExtEngine {

    /**
     * Returns value for basic data types
     * @param string $realName
     * @param array $extensionConfig
     * @param array|null $args
     * @param bool $isEntityParam
     * @return string
     */
    public function getExt($realName, array $extensionConfig, $args = NULL, $isEntityParam = FALSE)
    {
        $retValue = NULL;

        $x = $this->page->data['labels'];
        $_d = reset($x);

        if (isset($_d['ext_identifier'][$realName])) {

            switch ($extensionConfig['type']) {
                case 'color':
                    $retValue = $_d['ext_identifier'][$realName]['ext_value'];
                    break;
                case 'select':
                    $key = $_d['ext_identifier'][$realName]['ext_value'];
                    if (isset($extensionConfig['data']) && isset($extensionConfig['data'][$key])) {
                        $retValue['key'] = $key;
                        $retValue['value'] = $extensionConfig['data'][$key];
                    } else {
                        $retValue = $key;
                    }
                    break;
                case 'external_url':
                    $retValue = $_d['ext_identifier'][$realName]['ext_value'];
                    if ($retValue) {
                        if (!(Strings::startsWith($retValue, 'http://') || Strings::startsWith($retValue, 'https://'))) {
                            $retValue = 'http://' . $retValue;
                        }
                    }
                    break;
                case 'textArea':
                    $retValue = $_d['ext_identifier'][$realName]['ext_value'];

                    if (Strings::startsWith($realName, 'pdf_') && $args !== NULL) {
                        $filter = new Bubo\Filters\CMSFilter();
                        $pattern = $filter->getPattern('file');
                        if (preg_match_all($pattern, $_d['ext_identifier'][$realName]['ext_value'], $matches)) {
                            if (isset($matches[2]) && is_array($matches[2])) {
                                foreach ($matches[2] as $pdfFileId) {
                                     //$retValue = $pdfFileId;
                                     // i have file id in $pdfFileId
                                     // create newsletter storage
                                     //$newsletterStorage = WWW_DIR .

                                     switch ($args[0]) {
                                         case 'pages':
                                             $retValue = serialize($this->page->presenter->pdfConverterService->getNewsletterPages($pdfFileId));
                                             break;
                                         case 'thumb':
                                             $retValue = $this->page->presenter->pdfConverterService->getNewsletterThumb($pdfFileId);
                                             break;
                                     }

                                }
                            }
                        }
                    }

                    break;
                default:
                    $retValue = $_d['ext_identifier'][$realName]['ext_value'];
                    break;
            }
        }
        return $retValue;
    }



}