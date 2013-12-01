<?php
namespace Bubo\Application\UI;

use Nette\Application\UI\Control as NetteControl;

// TODO
// This class should have the functionality to automatic template registration
// This class should be the parent of all UI components in Bubo application
abstract class Control extends NetteControl
{

    public function initTemplate($templateFile) {
        $template = $this->getTemplate();
        $template->setFile($templateFile);
        $template->setTranslator($this->getPresenter()->getTranslator());
        return $template;
    }

    /**
     * @param  string|NULL
     * @return Nette\Templating\ITemplate
     */
//    protected function createTemplate($class = NULL)
//    {
//            $template = parent::createTemplate($class);
//
//            return $template;
//    }


//    public function getBasePath() {
//        $baseUrl = rtrim($this->presenter->context->httpRequest->getUrl()->getBaseUrl(), '/');
//        return preg_replace('#https?://[^/]+#A', '', $baseUrl);
//    }

//    public function createNewTemplate($fileName = NULL) {
//        $template = NULL;
//
//        if ($fileName !== NULL) {
//            $template = new \Nette\Templating\FileTemplate();
//            $template->setFile($fileName);
//        } else {
//            $template = new Nette\Templating\Template();
//        }
//
//        $template->setTranslator($this->getPresenter()->context->translator);
//        $template->registerFilter(new \Nette\Latte\Engine());
//        $template->registerHelperLoader('Nette\Templating\Helpers::loader');
//
//        //$baseUrl = rtrim($this->presenter->context->httpRequest->getUrl()->getBaseUrl(), '/');
//        $template->basePath = $this->getBasePath();
//        $template->themePath = $template->basePath . '/' . strtolower($this->presenter->pageManagerService->getCurrentModule());
//        $template->_presenter = $this->presenter;
//        $template->_control = $this;
//
//        return $template;
//    }

}