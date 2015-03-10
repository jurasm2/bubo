<?php
namespace Bubo\Templating;

use Bubo;
use Nette\Templating\Template as NetteTemplate;
use Nette\Caching;
use Latte;

class Template extends NetteTemplate
{

	/**
	 * Renders template to output.
	 * @return void
	 */
	public function render()
	{
		if (!$this->getFilters()) {
			$this->onPrepareFilters($this);
		}

		// apply CMS filter if registered
		$filters = $this->getFilters();
		if (isset($filters[0]) && $filters[0] instanceof Bubo\Filters\CMSFilter) {
			$cmsFilter = $filters[0];
			$this->setSource($cmsFilter($this->getSource()));
		}

		if ($latte = $this->getLatte()) {
			return $latte->setLoader(new Latte\Loaders\StringLoader)->render($this->getSource(), $this->getParameters());
		}

		$cache = new Caching\Cache($storage = $this->getCacheStorage(), 'Nette.Template');
		$cached = $compiled = $cache->load($this->getSource());

		if ($compiled === NULL) {
			$compiled = $this->compile();
			$cache->save($this->getSource(), $compiled, array(Caching\Cache::CONSTS => 'Nette\Framework::REVISION'));
			$cached = $cache->load($this->getSource());
		}

		$isFile = $cached !== NULL && $storage instanceof Caching\Storages\PhpFileStorage;
		self::load($isFile ? $cached['file'] : $compiled, $this->getParameters(), $isFile);
	}

}