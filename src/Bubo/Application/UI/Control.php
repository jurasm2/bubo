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
		$template->setTranslator($this->getPresenter()->translator);
		return $template;
	}

}