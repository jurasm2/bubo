<?php

namespace Bubo\Media\Components\Forms;

// ??
use BuboApp\AdminModule\Forms\BaseForm;

abstract class AbstractLoadMediaForm
{

	public function __construct($parent, $name)
	{
		parent::__construct($parent, $name);
		$this->addMultipleFileUpload('upload','', 999);
		$media = $this->lookup('Bubo\\Media');
		$this->addHidden('folderId', $media->folderId);
		$this->onSuccess[] = array($this, 'formSubmited');
		$this->getElementPrototype()->class = 'mfu';
	}

	abstract public function formSubmited($form);

}