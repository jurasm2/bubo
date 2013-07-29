<?php
namespace Bubo\Application\UI;

use Nette\Application\UI\Presenter as NettePresenter;
use Nette\ComponentModel\IComponent;

/**
 * Parent of all presenters in Bubo application.
 * Supports handling of "native" components.
 */
class Presenter extends NettePresenter
{

    /**
     * Array mapping the component name (key) to component class (value)
     * Component name is representer by regular expression.
     * @var array
     */
    protected $nativeControlMap = array();

    public function setup()
    {
        parent::setup();
    }

    /**
     * Generic component factory
     * @param string $name
     * @return IComponent  the created component
     */
    public function createComponent($name)
    {
        if (is_array($this->nativeControlMap)) {

            foreach ($this->nativeControlMap as $regexp => $classPrefix) {
                if (preg_match($regexp, $name)) {
                    $className = sprinf('%s\\%s', $classPrefix, ucfirst($name));
                    if (class_exists($className)) {
                        // TODO check signature of constructor
                        $component = new $classname($this, $name);
                        $component->setTranslator($this->context->translator);
                        return $component;
                    }
                }
            }

        }
        return parent::createComponent($name);
    }

}