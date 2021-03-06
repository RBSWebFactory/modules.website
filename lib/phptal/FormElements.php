<?php
class FormElement extends ChangeTalAttribute 
{
	
	/**
	 * @see ChangeTalAttribute::getEvaluatedParameters()
	 *
	 * @return String[]
	 */
	protected function getEvaluatedParameters()
	{
		return array('value', 'evaluatedname', 'evaluatedlabel');
	}
	
	/**
	 * @see ChangeTalAttribute::getRenderClassName()
	 *
	 * @return String
	 */
	protected function getRenderClassName()
	{
		return 'website_FormHelper';
	}
}

/**
 * Use in HTML: <anytag change:submit="name toto"/>
 */
class PHPTAL_Php_Attribute_CHANGE_submit extends FormElement
{

}

/**
 * Use in HTML: <anytag change:textinput="name toto; label &modules.tutu.tata.titiLabel"/>
 */
class PHPTAL_Php_Attribute_CHANGE_textinput extends FormElement
{

}
class PHPTAL_Php_Attribute_CHANGE_radioinput extends FormElement
{

}

class PHPTAL_Php_Attribute_CHANGE_checkboxinput extends FormElement
{

}

class PHPTAL_Php_Attribute_CHANGE_passwordinput extends FormElement
{
	
}

class PHPTAL_Php_Attribute_CHANGE_booleaninput extends FormElement
{
	
}

class PHPTAL_Php_Attribute_CHANGE_fileinput extends FormElement
{
	
}

class PHPTAL_Php_Attribute_CHANGE_uploadfield extends FormElement
{
	
}

class PHPTAL_Php_Attribute_CHANGE_listmultifield extends FormElement 
{
	
}

/**
 * Use in HTML: <anytag change:dateinput="name toto; label &modules.tutu.tata.titiLabel; format dd/yy/uu"/>
 */
class PHPTAL_Php_Attribute_CHANGE_dateinput extends FormElement
{
	/**
	 * @see FormElement::getEvaluatedParameters()
	 *
	 * @return array
	 */
	protected function getEvaluatedParameters()
	{
		$evaluatedParameters = parent::getEvaluatedParameters();
		$evaluatedParameters[] = 'startdate';
		$evaluatedParameters[] = 'enddate';
		return $evaluatedParameters;
	}
}

/**
 * Use in HTML: <anytag change:dateinput="name toto; label &modules.tutu.tata.titiLabel; format dd/yy/uu"/>
 */
class PHPTAL_Php_Attribute_CHANGE_datecombo extends PHPTAL_Php_Attribute_CHANGE_dateinput
{
	/**
	 * @return String
	 */
	protected function getRenderMethodName()
	{
		return 'renderDateCombo';
	}
}

/**
 * Use in HTML: <anytag change:errors="[key myKey]"/>
 */
class PHPTAL_Php_Attribute_CHANGE_errors extends FormElement
{

}

/**
 * Use in HTML: <anytag change:messages="[key myKey]"/>
 */
class PHPTAL_Php_Attribute_CHANGE_messages extends FormElement
{

}

/**
 * Use in HTML: <anytag change:hiddeninput="name toto;"/>
 */
class PHPTAL_Php_Attribute_CHANGE_hiddeninput extends FormElement
{

}

class PHPTAL_Php_Attribute_CHANGE_richtextinput extends FormElement 
{
	
}

class PHPTAL_Php_Attribute_CHANGE_bbcodeinput extends FormElement 
{
	
}

class PHPTAL_Php_Attribute_CHANGE_durationinput extends FormElement 
{
	
}

class PHPTAL_Php_Attribute_CHANGE_selectinput extends FormElement 
{
	/**
	 * @see FormElement::getEvaluatedParameters()
	 *
	 * @return array
	 */
	protected function getEvaluatedParameters()
	{
		$evaluatedParameters = parent::getEvaluatedParameters();
		$evaluatedParameters[] = 'list';
		return $evaluatedParameters;
	}
}

/**
 * Use in HTML: <anytag change:field="name toto;"/>
 */
class PHPTAL_Php_Attribute_CHANGE_field extends FormElement
{
	
	/**
	 * @see FormElement::getEvaluatedParameters()
	 *
	 * @return array
	 */
	protected function getEvaluatedParameters()
	{
		$evaluatedParameters = parent::getEvaluatedParameters();
		$evaluatedParameters[] = 'startdate';
		$evaluatedParameters[] = 'enddate';
		return $evaluatedParameters;
	}

}

/**
 * Use in HTML: <anytag change:textarea="name toto;"/>
 */
class PHPTAL_Php_Attribute_CHANGE_textarea extends FormElement
{

}

class PHPTAL_Php_Attribute_CHANGE_fieldlabel extends FormElement
{

}

class PHPTAL_Php_Attribute_CHANGE_label extends FormElement
{
	public function start()
	{
		// We rewrite element
		$this->tag->headFootDisabled = true;	
		parent::start();
	}
	
	public function end()
	{
		$this->tag->generator->doEchoRaw('website_FormHelper::endLabel()');
	}
}

/**
 * Use in HTML: <anytag change:form="method get">[...]</anytag>
 */
class PHPTAL_Php_Attribute_CHANGE_form extends FormElement
{
		
	/**
	 * @see ChangeTalAttribute::getDefaultValues()
	 *
	 * @return String[]
	 */
	protected function getDefaultValues()
	{
		return array('showErrors' => false);
	}
	
	public function start()
	{	
		$this->tag->headFootDisabled = true;	
		parent::start();
	}
	
	/**
	 * @see ChangeTalAttribute::getRenderMethodName()
	 *
	 * @return String
	 */
	protected function getRenderMethodName()
	{
		return 'initialize';
	}

	public function end()
	{
		$this->tag->generator->doEcho('website_FormHelper::finalize()');
	}
}
