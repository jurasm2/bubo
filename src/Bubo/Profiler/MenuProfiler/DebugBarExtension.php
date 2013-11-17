<?php

namespace Bubo\Profiler\MenuProfiler;

use Nette;

/**
 * Description of DebugBarExtension
 *
 * @author toretak
 */
class DebugBarExtension extends Nette\Object implements Nette\Diagnostics\IBarPanel {

    /**
     * Renders HTML code for custom tab.
     * @return string
     */
    public function getTab() {
        ob_start();
        //$data = $this->data;
        include __DIR__ . '/templates/bar.profiler.tab.phtml';
        return ob_get_clean();
    }

    /**
     * Renders HTML code for custom panel.
     * @return string
     */
    public function getPanel() {
        ob_start();
        include __DIR__ . '/templates/bar.profiler.panel.phtml';
        return ob_get_clean();
    }
}
