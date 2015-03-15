<?php

namespace Bubo\Media\Components\Forms;

class LoadFilesForm extends AbstractLoadMediaForm
{

	public function formSubmited($form)
	{
		$formValues = $form->getValues();
		$media = $this->lookup('Bubo\\Media');
		$this->presenter->mediaManagerService->addFiles($formValues, $media->getCurrentSection());
		$this->parent->view = NULL;
		$media->invalidateControl();
	}

}
