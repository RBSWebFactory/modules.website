<?php
class website_BlockAction extends f_mvc_Action implements website_PageBlock
{
	const SUBMIT_PARAMETER_NAME = "website_BlockAction_submit";
	const BLOCK_ID_PARAMETER_NAME = "blockId";
	const BLOCK_ERRORS_ATTRIBUTE_KEY = "website_BlockAction_errors";
	const BLOCK_MESSAGES_ATTRIBUTE_KEY = "website_BlockAction_messages";
	const BLOCK_PAGE_ATTRIBUTE = "website_page";
	const BLOCK_BO_MODE_ATTRIBUTE = "uixul_isInBackoffice";
	const BLOCK_PER_PROPERTY_ERRORS_ATTRIBUTE_KEY = "website_BlockAction_propertyErrors";

	/**
	 * 
	 * @var string
	 */
	private $blockType;
	
	/**
	 * @var string
	 */
	private $moduleName;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @param string $blocType
	 */
	public final function __construct($blocType = null)
	{
		
		if ($blocType === null)
		{
			$this->moduleName = $this->getModuleNameFromClassName();
			$this->name = $this->getNameFromClassName();
			$blocType = 'modules_' . $this->moduleName . '_' . $this->name;
		}
		else
		{
			list(,$this->moduleName, $blockName) = explode('_', $blocType);
			$this->name = f_util_StringUtils::lcfirst($blockName);
		}
		
		$this->blockType = $blocType;
		$this->setLang(RequestContext::getInstance()->getLang());
	}
	
	/**
	 * @return array
	 */
	public function getRequestModuleNames()
	{
		$names = array($this->moduleName);
		$requestModule = $this->getConfiguration()->getRequestModule();
		if ($requestModule !== null && $requestModule !== $this->moduleName)
		{
			$names[] = $requestModule;
		}
		return $names;
	}
	
	/**
	 *
	 * @return change_Storage 
	 */
	function getStorage()
	{
		return change_Controller::getInstance()->getStorage();
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	function execute($request, $response)
	{
		// empty
	}

	/**
	 * @return string
	 */
	protected function getConfigurationClassname()
	{
		$className = block_BlockService::getInstance()->getBlockConfigurationClassname($this->blockType);
		if ($className !== null && f_util_ClassUtils::classExists($className))
		{
			return $className;
		}
		return parent::getConfigurationClassname();
	}

	/**
	 * @return integer
	 */
	final function getOrder()
	{
		$configuration = $this->getConfiguration();
		if ($configuration->getAfterAll())
		{
			return -100;
		}
		else if ($configuration->getBeforeAll())
		{
			return 100;
		}
		return 0;
	}
	
	/**
	 * @return block_BlockInfo
	 */
	function getBlockInfo()
	{
		return block_BlockService::getInstance()->getBlockInfo('modules_'. $this->getModuleName().'_'. $this->getName());
	}

	/**
	 * @param string $parameterName
	 * @param string $defaultValue
	 * @return mixed
	 */
	protected function findLocalParameterValue($parameterName, $defaultValue = null)
	{
		if ($this->hasNonEmptyConfigurationParameter($parameterName))
		{
			return $this->getConfigurationParameter($parameterName);
		}

		$actionRequest = website_BlockController::getInstance()->getRequest();
		if ($actionRequest->hasAttribute($parameterName))
		{
			return $actionRequest->getAttribute($parameterName);
		}

		$storage = $this->getStorage();
		if ($storage->read($parameterName))
		{
			return $storage->read($parameterName);
		}

		if ($actionRequest->hasNonEmptyParameter($parameterName))
		{
			return $actionRequest->getParameter($parameterName);
		}
		return $defaultValue;
	}

	/**
	 * @param string $paramName
	 * @return integer | null
	 * @see website_BlockAction::_getDocumentIdParameter()
	 */
	protected function getDocumentIdParameter($paramName = change_Request::DOCUMENT_ID)
	{
		return $this->_getDocumentIdParameter($paramName, false);
	}

	/**
	 * @param string $paramName
	 * @return integer | null
	 * @see website_BlockAction::_getDocumentIdParameter()
	 */
	protected function getRequiredDocumentIdParameter($paramName = change_Request::DOCUMENT_ID)
	{
		return $this->_getDocumentIdParameter($paramName, true);
	}

	/**
	 * @param string $paramName
	 * @param string $className
	 * @return f_persistentdocument_PersistentDocument | null
	 * @see website_BlockAction::_getDocumentParameter()
	 */
	protected function getDocumentParameter($paramName = change_Request::DOCUMENT_ID, $className = null)
	{
		return $this->_getDocumentParameter($paramName, false, $className);
	}

	/**
	 * @param string $paramName
	 * @param string $expectedClassName the class the document must be an instance of
	 * @return f_persistentdocument_PersistentDocument
	 * @throws Exception if no document could be founded or it is not an instance of the expected
	 * @see website_BlockAction::_getDocumentParameter()
	 */
	protected function getRequiredDocumentParameter($paramName = change_Request::DOCUMENT_ID, $expectedClassName = null)
	{
		return $this->_getDocumentParameter($paramName, true, $expectedClassName);
	}

	/**
	 * @param string $paramName
	 * @param boolean $required
	 * @param string $expectedClassName the class the document must be an instance of
	 * @return f_persistentdocument_PersistentDocument
	 * @throws Exception if required and no document could be founded
	 */
	private function _getDocumentParameter($paramName = change_Request::DOCUMENT_ID, $required, $expectedClassName)
	{
		$id = $this->_getDocumentIdParameter($paramName, $required);
		if ($id !== null)
		{
			$doc = DocumentHelper::getDocumentInstance($id);
			if ($expectedClassName !== null)
			{
				if (!($doc instanceof $expectedClassName))
				{
					throw new Exception("$paramName parameter does not correspond to any $expectedClassName instance but is a ".get_class($doc));
				}
			}
			return $doc;
		}
		return null;
	}
	
	/**
	 * @return string | null
	 */
	protected function getRefreshSectionName()
	{
		$paramName = $this->getConfiguration()->getBlockId() . '_section';
		return $this->getRequest()->getParameter($paramName);
	}

	/**
	 * @param string $paramName
	 * @param boolean $required
	 * @return integer
	 * @throws Exception if required and no document could be founded
	 */
	private function _getDocumentIdParameter($paramName = change_Request::DOCUMENT_ID, $required)
	{
		$value = $this->findLocalParameterValue($paramName);
		if (is_array($value))
		{
			$value = f_util_ArrayUtils::firstElement($value);
		}
		if (is_numeric($value) && $value > 0)
		{
			return intval($value);
		}
		if ($required)
		{
			throw new Exception("Could not find any document corresponding to '$paramName' parameter");
		}
		return null;
	}

	/**
	 * Search for $shortViewName locally and if not found, give a try
	 * in website module, searching for 'Generic-Block-'.$shortViewName in it.
	 * @param $shortViewName
	 * @throws TemplateNotFoundException if template could not be found in current module and generic module
	 * @return TemplateObject
	 */
	protected function genericView($shortViewName)
	{
		try
		{
			return $this->getTemplate($shortViewName);
		}
		catch (TemplateNotFoundException $e)
		{
			if (Framework::isDebugEnabled())
			{
				Framework::debug(__METHOD__ . ' EXCEPTION: ' . $e->getMessage());
			}
		}
			$templateName = 'Generic-Block-'.$shortViewName;
			return $this->getTemplateByFullName('modules_website', $templateName);
		}

	/**
	 * @return string[]
	 * @see f_mvc_Action::getInputValidationRules()
	 */
	function getInputValidationRules($request, $bean)
	{
		return array();
	}

	/**
	 * @see f_mvc_Action::getModuleName()
	 *
	 * @return string
	 */
	final function getModuleName()
	{
		return $this->moduleName;
	}

	/**
	 * @return string
	 * @see f_mvc_Action::getName()
	 */
	final function getName()
	{
		return $this->name;
	}

	/**
	 * @param f_mvc_Request $request
	 * @return void
	 * @see f_mvc_Action::onValidateInputFailed()
	 */
	function onValidateInputFailed($request)
	{

	}

	/**
	 * @return boolean
	 * @see f_mvc_Action::validateInput()
	 */
	function validateInput($request, $bean)
	{
		return $this->processValidationRules($this->getInputValidationRules($request, $bean), $request, $bean);
	}

	/**
	 * @see f_mvc_Action::getInputViewName()
	 *
	 * @return string
	 */
	function getInputViewName()
	{
		return website_BlockView::INPUT;
	}

	/**
	 * @see f_mvc_Action::findParameterValue()
	 *
	 * @param unknown_type $parameterName
	 */
	public final function findParameterValue($parameterName)
	{
		if ($this->hasNonEmptyConfigurationParameter($parameterName))
		{
			return $this->getConfigurationParameter($parameterName);
		}

		$actionRequest = website_BlockController::getInstance()->getRequest();
		if ($actionRequest->hasAttribute($parameterName))
		{
			return $actionRequest->getAttribute($parameterName);
		}

		$storage = $this->getStorage();
		if ($storage->read($parameterName))
		{
			return $storage->read($parameterName);
		}


		if ($actionRequest->hasNonEmptyParameter($parameterName))
		{
			return $actionRequest->getParameter($parameterName);
		}
		$globalRequest = change_Controller::getInstance()->getRequest();
		if ($globalRequest->hasNonEmptyParameter($parameterName))
		{
			return $globalRequest->getParameter($parameterName);
		}

		return null;
	}

	/**
	 * @param website_BlockActionRequest $request
	 * @return array
	 */
	public function getCacheKeyParameters($request)
	{
		return array();
	}

	/**
	 * @return website_Page
	 */
	public final function getContext()
	{
		return website_BlockController::getInstance()->getContext();
	}

	/**
	 * @param string $moduleName
	 * @param string $actionName
	 */
	public final function forward($moduleName, $actionName)
	{
		return website_BlockController::getInstance()->forward($moduleName, $actionName);
	}

	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @param Array<String, String> $moduleParams
	 * @param Array<String, String> $absParams
	 */
	public final function redirect($moduleName, $actionName, $moduleParams = null, $absParams = null)
	{
		return website_BlockController::getInstance()->redirect($moduleName, $actionName, $moduleParams, $absParams);
	}

	/**
	 * @see f_mvc_Request::addError()
	 *
	 * @param string $msg
	 */
	public final function addError($msg, $relKey = null)
	{
		$this->addAttributeWithKey($msg, self::BLOCK_ERRORS_ATTRIBUTE_KEY, $relKey);
	}

	/**
	 * @see f_mvc_Request::addMessage()
	 *
	 * @param string $msg
	 */
	public final function addMessage($msg, $relKey = null)
	{
		$this->addAttributeWithKey($msg, self::BLOCK_MESSAGES_ATTRIBUTE_KEY, $relKey);
	}

	/**
	 * @see f_mvc_Request::getErrors()
	 *
	 * @return array<String>
	 */
	public final function getErrors()
	{
		return $this->getAttributeByKey(self::BLOCK_ERRORS_ATTRIBUTE_KEY);
	}

	/**
	 * @see f_mvc_Request::getMessages()
	 *
	 * @return array<String>
	 */
	public final function getMessages()
	{
		return $this->getAttributeByKey(self::BLOCK_MESSAGES_ATTRIBUTE_KEY);
	}

	/**
	 * @see f_mvc_Request::hasErrors()
	 *
	 * @return boolean
	 */
	public final function hasErrors()
	{
		return $this->hasAttributeForKey(self::BLOCK_ERRORS_ATTRIBUTE_KEY);
	}

	/**
	 * @see f_mvc_Request::hasMessages()
	 *
	 * @return boolean
	 */
	public final function hasMessages()
	{
		return $this->hasAttributeForKey(self::BLOCK_MESSAGES_ATTRIBUTE_KEY);
	}

	/**
	 * @param string $name
	 * @param array $arguments
	 */
	final function __call($name, $arguments)
	{
		$matches = array();
		if (preg_match('/^validate(.+)Input$/', $name, $matches))
		{
			if (count($arguments) != 2)
			{
				throw new Exception("$name does not have exactly two arguments");
			}
			$executePart = $matches[1];
			$request = $arguments[0];
			return $this->processValidation($executePart, $request, $arguments[1], $request->getParameter("website_FormHelper_relkey"));
		} 
		else
		{
			throw new Exception("Method $name does not exist on " . get_class($this));
		}
	}

	/**
	 * @param website_BlockActionRequest $request
	 * @param Mixed $bean
	 * @param string $executePart
	 * @param string $relKey
	 * @return boolean
	 */
	protected final function processValidation($executePart, $request, $bean = null, $relKey = null)
	{
		$getRulesMethodName = 'get' . $executePart . 'InputValidationRules';
		if (method_exists($this, $getRulesMethodName))
		{
			return $this->processValidationRules($this->$getRulesMethodName($request, $bean), $request, $bean, $relKey);
		}
		return true;
	}

	/**
	 * @return string
	 */
	public final function getBlockId()
	{
		return $this->getName() . $this->getConfigurationParameter(self::BLOCK_ID_PARAMETER_NAME);
	}
	
	/**
	 * Shortcut returning the current BlockActionRequest
	 * @return website_BlockActionRequest
	 */
	protected function getRequest()
	{
		return website_BlockController::getInstance()->getRequest();
	}

	/**
	 * Called when the block is inserted into a page content
	 * @param website_persistentdocument_Page $page
	 * @param boolean $absolute true if block was introduced considering all versions (langs) of the page. Default value only for compatibility with old interface
	 */
	function onPageInsertion($page, $absolute = false)
	{
		// empty
		if (Framework::isDebugEnabled())
		{
			Framework::debug("Block ".get_class($this)." inserted in page ".$page->getId()." (absolute = " . ($absolute ? 'true' : 'false') . ")");
		}
	}

	/**
	 * Called when the block is removed from a page content
	 * @param website_persistentdocument_Page $page
	 * @param boolean $absolute true if block was removed considering all versions (langs) of the page. Default value only for compatibility with old interface
	 */
	function onPageRemoval($page, $absolute = false)
	{
		// empty
		if (Framework::isDebugEnabled())
		{
			Framework::debug("Block ".get_class($this)." removed from page ".$page->getId()." (absolute = " . ($absolute ? 'true' : 'false') . ")");
		}
	}

	// protected methods

	/**
	 * @param string $validationRules
	 * @param website_BlockActionRequest $request
	 * @param f_mvc_Bean|null $bean
	 * @param string $relKey
	 * @return boolean
	 */
	protected final function processValidationRules($validationRules, $request, $bean, $relKey = null)
	{
		if ($bean !== null)
		{
			$bean = BeanUtils::getBean($bean);
		}
		
		$validationResult = true;
		foreach ($validationRules as $validationRuleDeclaration)
		{
			$rule = null;
			$propertyName = null;
			if ($this->isPropertyRule($validationRuleDeclaration, $propertyName, $rule))
			{
				$propertyLabel = $this->getPropertyLabelFromBean($propertyName, $bean);

				if ($bean !== null && BeanUtils::hasProperty($bean, $propertyName))
				{
					$propertyValue = BeanUtils::getProperty($bean, $propertyName);
					$propertyType = BeanUtils::getBeanPropertyInfo($bean, $propertyName)->getType();
				}
				else
				{
					$propertyValue = $request->getParameter($propertyName);
					$propertyType = null;
				}
				$validationProperty = new validation_Property($propertyLabel, $propertyValue, $propertyType);
				$validationErrors = new validation_Errors();

				validation_ValidatorHelper::validate($validationProperty, $rule, $validationErrors);

				if (!$validationErrors->isEmpty())
				{
					$validationResult = false;
					foreach ($validationErrors as $validationError)
					{
						$this->addError($validationError, $relKey);
					}
					$this->addErrorsForProperty($propertyName, $validationErrors, $relKey);
				}
			}
			elseif ($this->isBeanRule($validationRuleDeclaration))
			{
				$errors = null;
				if ($bean !== null)
				{
					$values = $bean;
				}
				else
				{
					$values = $request->getParameters();
				}
				if (!validation_ValidatorHelper::validateBean($validationRuleDeclaration, $values, $errors))
				{
					$validationResult = false;
					foreach ($errors as $propName => $propertyErrors)
					{
						if (is_numeric($propName))
						{
							foreach ($propertyErrors as $propertyError)
							{
								$this->addError($propertyError, $relKey);
							}
						}
						else
						{
							$this->addErrorsForProperty($propName, $propertyErrors, $relKey);
						}
					}
				}
			}
			else
			{
				throw new ValidationException(__CLASS__ . " Invalid validation rule declaration $validationRuleDeclaration");
			}
		}
		return $validationResult;
	}

	/**
	 * @return boolean
	 */
	protected final function isInBackoffice()
	{
		return (RequestContext::getInstance()->getMode() === RequestContext::BACKOFFICE_MODE);
	}
	
	/**
	 * @return boolean
	 */
	protected final function isInBackofficeEdition()
	{
		return $this->getContext()->getAttribute(self::BLOCK_BO_MODE_ATTRIBUTE, false);
	}
	
	/**
	 * @return boolean
	 */
	protected final function isInBackofficePreview()
	{
		return $this->isInBackoffice() &&  !$this->isInBackofficeEdition();
	}

	/**
	 * @param string $viewName
	 * @return TemplateObject
	 */
	protected function getTemplate($viewName)
	{
		$templateName = ucfirst($this->moduleName) .'-'. 'Block-' . ucfirst($this->name) . '-' . $viewName;
		return $this->getTemplateByFullName('modules_' . $this->moduleName, $templateName);
	}

	/**
	 * @param string $packageName For example: 'modules_website'
	 * @param string $templateName For example: 'Website-Block-Taggedmenu-Footer'
	 * @param string $subDirectory
	 * @return TemplateObject
	 */
	protected function getTemplateByFullName($packageName, $templateName, $subDirectory = null)
	{
		try 
		{
			$directory = 'templates';
			if ($subDirectory !== null)
			{
				$directory .= DIRECTORY_SEPARATOR . $subDirectory;
			}
			$templateLoader = TemplateLoader::getInstance()
			->setMimeContentType('html')
			->setDirectory($directory)
			->setPackageName($packageName);
	
			return $templateLoader->load($templateName);
		}
		catch (Exception $e)
		{
			return null;	
		}
		
	}

	// private methods


	/**
	 * @param string $propertyName
	 * @param Mixed $bean
	 * @return string
	 */
	private function getPropertyLabelFromBean($propertyName, $bean)
	{
		if ($bean === null || !BeanUtils::hasProperty($bean, $propertyName))
		{
			return LocaleService::getInstance()->trans("m.".$this->getModuleName().".fo.blocks.".$this->getName().".".$propertyName);
		}

		return f_Locale::translate(BeanUtils::getBeanPropertyInfo($bean, $propertyName)->getLabelKey());
	}

	/**
	 * @param string $validationRuleDeclaration
	 * @param string $propertyName
	 * @param string $rule
	 * @return boolean
	 */
	private function isPropertyRule($validationRuleDeclaration, &$propertyName, &$rule)
	{
		if (f_util_StringUtils::isEmpty($validationRuleDeclaration))
		{
			return false;
		}
		$ruleLength = strlen($validationRuleDeclaration);
		$firstDelimiterPosition = strpos($validationRuleDeclaration, '{');
		if ($firstDelimiterPosition === false || $validationRuleDeclaration[$ruleLength - 1] != '}')
		{
			return false;
		}
		$propertyName = substr($validationRuleDeclaration, 0, $firstDelimiterPosition);
		$rule = substr($validationRuleDeclaration, $firstDelimiterPosition + 1, $ruleLength - $firstDelimiterPosition - 2);
		return true;
	}

	/**
	 * @param string $validationRuleDeclaration
	 * @param string $propertyName
	 * @param string $rule
	 * @return boolean
	 */
	private function isBeanRule($validationRuleDeclaration)
	{
		// TODO: more validation ? ... it will throw later anyway ... ?
		if (f_util_StringUtils::isEmpty($validationRuleDeclaration))
		{
			return false;
		}
		return true;
	}

	/**
	 * @return string
	 */
	private function getModuleNameFromClassName()
	{
		$className = get_class($this);
		return substr($className, 0, strpos($className, "_"));
	}

	/**
	 * @return string
	 */
	private function getNameFromClassName()
	{
		$result = array();
		preg_match('/' . $this->getModuleName() . '_Block(\w+)Action/', get_class($this), $result);
		return f_util_StringUtils::lcfirst($result[1]);
	}

	/**
	 * @param string $propertyName
	 * @param string[] $errors
	 */
	protected final function setErrorsForProperty($propertyName, $errors)
	{
		if ($this->hasAttributeForKey(self::BLOCK_PER_PROPERTY_ERRORS_ATTRIBUTE_KEY))
		{
			$errorsPerProperty = $this->getAttributeByKey(self::BLOCK_PER_PROPERTY_ERRORS_ATTRIBUTE_KEY);
		}
		else
		{
			$errorsPerProperty = array();
		}
		$errorsPerProperty[$propertyName] = $errors;
		$this->setAttributeWithKey($errorsPerProperty, self::BLOCK_PER_PROPERTY_ERRORS_ATTRIBUTE_KEY);
	}

	/**
	 * @param string $propertyName
	 * @param string $error
	 * @param string $relKey
	 */
	protected final function addErrorForProperty($propertyName, $error, $relKey = null)
	{
		$key = self::BLOCK_PER_PROPERTY_ERRORS_ATTRIBUTE_KEY;
		if ($this->hasAttributeForKey($key))
		{
			$errorsPerProperty = $this->getAttributeByKey($key);
		}
		else
		{
			$errorsPerProperty = array();
		}
		if (!isset($errorsPerProperty[$propertyName]))
		{
			$errorsPerProperty[$propertyName] = array();
		}
		$errorsPerProperty[$propertyName][] = $error;
		$this->setAttributeWithKey($errorsPerProperty, $key);
		
		if ($relKey === null)
		{
			$relKey = website_BlockController::getInstance()->getRequest()->getParameter("website_FormHelper_relkey");
		}
		if ($relKey !== null)
		{
			$key = self::BLOCK_PER_PROPERTY_ERRORS_ATTRIBUTE_KEY."_relative";
			if ($this->hasAttributeForKey($key))
			{
				$errorsPerProperty = $this->getAttributeByKey($key);
			}
			else
			{
				$errorsPerProperty = array();
			}
			if (!isset($errorsPerProperty[$propertyName]))
			{
				$errorsPerProperty[$propertyName] = array();
			}
			$errorsPerProperty[$propertyName][] = $error;
			$this->setAttributeWithKey($errorsPerProperty, $key, $this->getBlockId());
		}
	}
	
	/**
	 * @param string $propertyName
	 * @param string[] $errors
	 * @param string[] $relKey
	 */
	protected final function addErrorsForProperty($propertyName, $errors, $relKey = null)
	{
		foreach ($errors as $error)
		{
			$this->addErrorForProperty($propertyName, $error, $relKey);
		}	
	}

	/**
	 * @param string $msg
	 * @param string $key
	 */
	private function addAttributeWithKey($msg, $key, $relKey = null)
	{
		$this->addAttributeWithKeyForBlock($msg, $key, $this->getBlockId());
		if ($relKey === null)
		{
			$relKey = website_BlockController::getInstance()->getRequest()->getParameter("website_FormHelper_relkey");
		}
		if ($relKey !== null)
		{
			$this->addAttributeWithKeyForBlock($msg, $key."_relative", $this->getBlockId()."_".$relKey);
		}
	}

	private function addAttributeWithKeyForBlock($msg, $key, $blockId)
	{
		$context = $this->getContext();
		$blockAttributes = $context->getAttribute($key, array());
		if (!isset($blockAttributes[$blockId]))
		{
			$blockAttributes[$blockId] = array();
		}
		$blockAttributes[$blockId][] = $msg;
		$context->setAttribute($key, $blockAttributes);
	}

	private function getAttributeByKey($key)
	{
		$context = $this->getContext();
		$blockAttributes = $context->getAttribute($key, array());
		$blockId = $this->getBlockId();
		if (!isset($blockAttributes[$blockId]))
		{
			return array();
		}
		return $blockAttributes[$blockId];
	}

	private function hasAttributeForKey($key)
	{
		$context = $this->getContext();
		$blockAttributes = $context->getAttribute($key, array());
		$blockId = $this->getBlockId();
		return isset($blockAttributes[$blockId]);
	}

	private function setAttributeWithKey($value, $key, $blockId = null)
	{
		$context = $this->getContext();
		$blockAttributes = $context->getAttribute($key, array());
		if ($blockId === null)
		{
			$blockId = $this->getBlockId();
		}
		$blockAttributes[$blockId] = $value;
		$context->setAttribute($key, $blockAttributes);
	}
}

abstract class f_mvc_Action
{
	/**
	 * @var block_BlockConfiguration
	 */
	private $configuration;

	/**
	 * @var String
	 */
	private $lang;

	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	function execute($request, $response)
	{

	}

	protected function getConfigurationClassname()
	{
		return 'block_BlockConfiguration';
	}

	/**
	 * @return block_BlockConfiguration
	 */
	public function getConfiguration()
	{
		if ($this->configuration === null)
		{
			$className = $this->getConfigurationClassname();
			$this->configuration = new $className();
		}
		return $this->configuration;
	}

	/**
	 * @return array
	 */
	public final function getConfigurationParameters()
	{
		return $this->getConfiguration()->getConfigurationParameters();
	}


	/**
	 * @param string $name
	 * @param string $value
	 */
	public final function setConfigurationParameter($name, $value)
	{
		$this->getConfiguration()->setConfigurationParameter($name, $value);
	}

	/**
	 * @param string $parameterName
	 * @param Mixed $defaultValue
	 * @return Mixed
	 */
	public final function getConfigurationParameter($parameterName, $defaultValue = null)
	{
		return $this->getConfiguration()->getConfigurationParameter($parameterName, $defaultValue);
	}

	/**
	 * @param string $parameterName
	 * @return boolean
	 */
	public final function hasConfigurationParameter($parameterName)
	{
		return $this->getConfiguration()->hasConfigurationParameter($parameterName);
	}

	/**
	 * @param string $parameterName
	 * @return boolean
	 */
	public final function hasNonEmptyConfigurationParameter($parameterName)
	{
		return $this->getConfiguration()->hasNonEmptyConfigurationParameter($parameterName);
	}

	/**
	 * @return string
	 */
	public final function getLang()
	{
		return $this->lang;
	}

	/**
	 * @param string $lang
	 */
	public final function setLang($lang)
	{
		$this->lang = $lang;
	}
	
	protected $cacheEnabled;

	/**
	 * @return boolean
	 */
	public function isCacheEnabled()
	{
		if ($this->cacheEnabled === null)
		{
			$this->cacheEnabled = $this->getConfiguration()->isCacheEnabled() 
				&& $this->getConfiguration()->getCusecache();
		}
		return $this->cacheEnabled;
	}
	
	/**
	 * @return int
	 */
	public function getCacheTtl()
	{
		return $this->getConfiguration()->getCacheTtl();
	}

	/**
	 * @return array
	 */
	public function getCacheDependencies()
	{
		return null;
	}
	
	/**
	 * @param f_mvc_Request $request
	 */
	public function getCacheKeyParameters($request)
	{
		return null;
	}
	
	/**
	 * @return array
	 */
	public function getConfiguredCacheKeys()
	{
		return null;
	}
	
	/**
	 * @return array
	 */
	public function getConfiguredCacheDeps()
	{
		return null;
	}

	/**
	 * @param f_mvc_Request $request
	 * @return array
	 */
	abstract function getInputValidationRules($request, $bean);

	/**
	 * @param f_mvc_Request $request
	 * @return boolean
	 */
	abstract function validateInput($request, $bean);

	/**
	 * @return void
	 */
	abstract function onValidateInputFailed($request);

	/**
	 * @return string
	 */
	abstract function getModuleName();

	/**
	 * @return string
	 */
	abstract function getName();

	/**
	 * @return string
	 */
	abstract function getInputViewName();
	
	/**
	 * @return f_mvc_Context
	 */
	abstract public function getContext();

	/**
	 * @param string $moduleName
	 * @param string $actionName
	 */
	abstract public function forward($moduleName, $actionName);

	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @param Array $moduleParams
	 * @param Array $absParams
	 */
	abstract public function redirect($moduleName, $actionName, $moduleParams = null, $absParams = null);

	/**
	 * @param $url
	 * @return void
	 */
	public function redirectToUrl($url)
	{
		f_web_http_Header::setStatus(302);
		header("Location: $url");
	}

	/**
	 * @param string $parameterName
	 */
	abstract protected function findParameterValue($parameterName);
	
	
	/**
	 * @deprecated (will be removed in RBS Change 5.0)
	 */
	protected function getSession()
	{
		return f_mvc_HTTPRequest::getInstance()->getSession();
	}
	
	/**
	 * @deprecated (will be removed in RBS Change 5.0)
	 */
	protected function getHTTPRequest()
	{
		return f_mvc_HTTPRequest::getInstance();
	}
}