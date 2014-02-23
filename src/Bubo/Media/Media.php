<?php

namespace Bubo;

use Bubo\Application\UI\Control;

use Nette;

class Media extends Control
{

    const FOLDER = 'folder',
          GALLERY = 'gallery',
          FILE = 'file';

    /**
     * Maximal number of items to be revealed after mouse scroll
     */
    const SCROLLING_OFFSET = 24;

    /**
     * Currently selected folderId
     * @var int|null
     * @persistent
     */
    public $folderId;

    /**
     * Currently selected section
     * @var string
     * @persistent
     */
    public $section;

    /**
     * Currently selected fileId
     * @var int|null
     * @persistent
     */
    public $fileId;

    /**
     * Trigger of the media component
     * tiny |
     * @var string
     * @persistent
     */
    public $trigger = 'tiny';

    /**
     * Extension type
     * This variable needs to be renamed to $extType!!
     * @var string
     * @persistent
     */
    public $extName;

    /**
     * Generic factory for first order media subcomponents
     * @param string $name
     * @return mixed
     */
    public function createComponent($name)
    {
        $nsClassName = 'Bubo\\Media\\Components\\' . ucfirst($name);
        if (class_exists($nsClassName)) {
            return new $nsClassName($this, $name);
        }
        return parent::createComponent($name);
    }

    /**
     * Retuns javascript configuration of the component
     * @return string
     */
    public function getMediaJsConfig()
    {
        $jsConfig = array(
                        'initialOffset' =>  self::SCROLLING_OFFSET,
                        'offset'        =>  self::SCROLLING_OFFSET,
                        'link'          =>  $this['content']->link('loadContent!'),
                        'section'       =>  $this->getCurrentSection(),
                        'folderId'      =>  $this->getFolderId()

            );

        return json_encode($jsConfig);
    }

    /**
     * Sends tinyMce command as JSON response
     * @param array $tinyArgs
     */
    public function sendTinyMceCommand($tinyArgs)
    {
        $this->presenter->payload->tinyControl = array(
                                                    'command' => 'insertImage',
                                                    'args'    => $tinyArgs
                                                 );

        $this->presenter->terminate();
    }

    /**
     * Returns folderId
     * @return int
     */
    public function getFolderId()
    {
        return $this->folderId;
    }

    /**
     * Returns actually selected section
     * @return string
     */
    public function getCurrentSection()
    {
        $config = $this->getConfig();
        $defaultSection = $config['general']['defaultSection'];

        if ($this->section === NULL) {
            $this->section = $defaultSection;
        }

        return $this->section;
    }

    /**
     * Returns trigger of the component
     * @return string
     * @throws Nette\InvalidStateException
     */
    public function getTrigger()
    {
        $generalConfig = $this->getConfig('general');
        if (array_search($this->trigger, $generalConfig['allowedTriggers']) === FALSE) {
            throw new Nette\InvalidStateException("'".$this->trigger."' is unknown trigger");
        }
        return $this->trigger;
    }

    /**
     * Returns all sections of the media component
     * files, galleries ...
     * @return array
     */
    public function getAllSections()
    {
        $config = $this->getConfig();
        return $config['layout']['sections'];
    }

    /**
     * Returns media config
     * @param string|null $section
     * @return array
     */
    public function getConfig($section = NULL)
    {
        $config = $this->presenter->configLoaderService->load(__DIR__ . '/config/config.neon');
        return $section ? $config[$section] : $config;
    }

    /**
     * Render component
     */
    public function render()
    {
        try {
            $this->presenter->mediaManagerService->createStructure();
            $template = parent::initTemplate(__DIR__ . '/templates/default.latte');
            $template->render();
        } catch (Nette\InvalidStateException $ex) {
            $template = parent::initTemplate(__DIR__ . '/templates/error.latte');
            $template->message = $ex->getMessage();
            $template->render();
        }
    }

}
