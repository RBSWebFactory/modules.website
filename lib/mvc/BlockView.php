<?php
class website_BlockView
{
	const ALERT = 'Alert';
	const BACKOFFICE = 'Backoffice';
	const INPUT = 'Input';
	const SUCCESS = 'Success';
	const ERROR = 'Error';
	const ITEM = 'Item';
	const SHORTITEM = 'ShortItem';
	const LISTITEM = 'ListItem';
	const MENU = 'Menu';
	const DUMMY = 'Dummy';
	const MAIL = 'Mail';
	const NONE = null;

	/**
	 * @var String
	 */
	private $relativeName;
	
	/**
	 * @var String
	 */
	private $templateModuleName;
	
	/**
	 * @var TemplateObject
	 */
	private $templateObject;

	/**
	 * @var String[]
	 */
	private $loadHandlers = array();

	/**
	 * @param string $relativeNameOrTemplate
	 * @param string $templateModuleName
	 */
	public function __construct($relativeNameOrTemplate, $templateModuleName = null)
	{
		if ($relativeNameOrTemplate instanceof TemplateObject)
		{
			$this->templateObject = $relativeNameOrTemplate;
		}
		else
		{
			$this->templateModuleName = $templateModuleName;
			$this->relativeName = $relativeNameOrTemplate;
		}
	}
	
	/**
	 * @param website_BlockActionRequest $request
	 * @return string
	 */
	private function getName($request)
	{
		return 'Block-' . ucfirst($request->getActionName()) . '-' . $this->relativeName;
	}

	/**
	 * @param string $className
	 * @param string $paramsString
	 */
	protected final function addLoadHandler($className, $paramsString = null)
	{
		$params = self::parseHandlerArgs($paramsString);
		$this->loadHandlers[] = array($className, $params);
	}

	/**
	 * @param string $args For example: 'arg1Value, arg2Value' or 'arg1Name : arg1Value, arg2Name : arg2Value'
	 * @return array<string, string>
	 */
	public static function parseHandlerArgs($args)
	{
		$params = array();
		if ($args !== null)
		{
			foreach (explode(',', $args) as $param)
			{
				$paramInfo = explode(':', $param);
				if (count($paramInfo) == 1)
				{
					// positional parameter
					$params[] = trim($param);
				}
				else
				{
					// named parameter
					$params[trim($paramInfo[0])] = trim($paramInfo[1]);
				}
			}
		}
		return $params;
	}

	/**
	 * @param website_BlockActionRequest $request
	 * @param website_BlockActionResponse $response
	 */
	private function executeLoadHandlers($request, $response)
	{
		foreach ($this->loadHandlers as $loadHandlerInfo)
		{
			$loadHandlerClassName = $loadHandlerInfo[0];
			$loadHandler = new $loadHandlerClassName();
			$loadHandler->setParameters($loadHandlerInfo[1]);
			$loadHandler->execute($request, $response);
		}
	}

	/**
	 * @param website_BlockActionRequest $request
	 */
	private function loadConfig($request)
	{
		$name = $this->getName($request);
		$viewFolder = f_util_FileUtils::buildProjectPath('modules', $request->getModuleName(), 'config', 'views');
		$viewConfigFile = $viewFolder.'/'.$name.".php";
		if (file_exists($viewConfigFile))
		{
			include($viewConfigFile);
		}
	}
	
	/**
	 * @param website_BlockActionRequest $request
	 * @return string
	 */
	private function getTemplateModule($request)
	{
		return $this->templateModuleName !== null ? $this->templateModuleName : $request->getModuleName();
	}

	/**
	 * @param website_BlockActionRequest $request
	 * @param website_BlockActionResponse $response
	 */
	public function execute($request, $response)
	{
		$this->loadConfig($request);
		$this->executeLoadHandlers($request, $response);
		if ($this->templateObject === null)
		{
			$moduleName = $this->getTemplateModule($request);
			
			$templateName = ucfirst($moduleName) .'-'. $this->getName($request);
			$this->templateObject = change_TemplateLoader::getNewInstance()->setExtension('html')
					->load('modules', $moduleName, 'templates', $templateName);
		}
		
		if ($this->templateObject !== null)
		{
			$template = $this->templateObject;
			$model = array_merge($request->getParameters(), $request->getAttributes());
			$model["session_Attributes"] = change_Controller::getInstance()->getStorage()->readAll();
			$model["context"] = $request->getContext();
			$model["website_page"] = $request->getContext();
			$template->importAttributes($model);
			$response->getWriter()->write($template->execute());
		}
	}
}