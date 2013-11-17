<?php

namespace Bubo\Profiler\MenuProfiler;

use Nette;

/**
 * Description of DebugBarExtension
 *
 * @author toretak
 */
class DebugBarExtension extends Nette\Object implements Nette\Diagnostics\IBarPanel {

        public $data;

	/**
	 * Renders HTML code for custom tab.
	 * @return string
	 */
	public function getTab()
	{
		ob_start();
		$data = $this->data;
                require __DIR__ . '/templates/bar.profiler.tab.phtml';
		return ob_get_clean();
	}


        public function setData($data){
                $this->data = $data;
        }

	/**
	 * Renders HTML code for custom panel.
	 * @return string
	 */
	public function getPanel()
	{
		ob_start();
		$data = $this->data;
                require __DIR__ . '/templates/bar.profiler.panel.phtml';
		return ob_get_clean();
	}

}
