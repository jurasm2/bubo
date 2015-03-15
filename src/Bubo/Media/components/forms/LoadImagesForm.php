<?php

namespace Bubo\Media\Components\Forms;

class LoadImagesForm extends AbstractLoadMediaForm
{

	public function formSubmited($form)
	{
		$formValues = $form->getValues();
		$media = $this->lookup('Bubo\\Media');
		$this->presenter->mediaManagerService->addImages($formValues);
		$this->parent->view = NULL;
		$media->invalidateControl();
	}
}
