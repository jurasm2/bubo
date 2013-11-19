<?php

namespace Bubo\ExtEngines;

use Bubo\Pages\AbstractPage;

use Nette;

/**
 * Base Ext Engine
 * @author Marek Juras
 */
abstract class BaseExtEngine extends Nette\Object {

    /**
     * Page
     * @var AbstractPage
     */
    protected $page;

    /**
     * Constructor
     * @param AbstractPage $page
     */
    public function __construct(AbstractPage $page)
    {
        $this->page = $page;
    }

    abstract function getExt($realName, array $extensionConfig, $args = NULL, $isEntityParam = FALSE);

    /**
     * Returns page
     * @return AbstractPage;
     */
    public function getPage()
    {
        return $this->page;
    }
}