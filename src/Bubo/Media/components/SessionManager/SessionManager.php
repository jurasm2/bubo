<?php
namespace Bubo\Media\Components;

use Bubo\Application\UI\Control;

class SessionManager extends Control {

    const MEDIA_SESSION_NAMESPACE = 'media';

    public $sessionSectionName = 'default';

    public function __construct($parent, $name) {
        parent::__construct($parent, $name);
    }

    public function createComponentPasteBin($name) {
        return new SessionManager\PasteBin($this, $name);
    }

    public function getSessionSection() {
        $session = $this->presenter->context->getService('session');
        return $session->getSection(self::MEDIA_SESSION_NAMESPACE . '-' . $this->sessionSectionName);
    }

}
