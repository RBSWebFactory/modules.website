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
	 * @var TemplateObject
	 */
	private $templateObject;

	/**
	 * @var String[]
	 */
	private $loadHandlers = array();

	/**
	 * @param String $name
	 */
	public function __construct($relativeNameOrTemplate)
	{
		if ($relativeNameOrTemplate instanceof TemplateObject)
		{
			$this->templateObject = $relativeNameOrTemplate;
		}
		else
		{
			$this->relativeName = $relativeNameOrTemplate;
		}
	}
	
	/**
	 * @param website_BlockActionRequest $request
	 * @return String
	 */
	private function getName($request)
	{
		return 'Block-' . ucfirst($request->getActionName()) . '-' . $this->relativeName;
	}

	/**
	 * @param String $className
	 * @param String $paramsString
	 */
	protected final function addLoadHandler($className, $paramsString = null)
	{
		$params = self::parseHandlerArgs($paramsString);
		$this->loadHandlers[] = array($className, $params);
	}

	/**
	 * @example website_BlockView::parseHandlerArgs('arg1Value, arg2Value')
	 * @example website_BlockView::parseHandlerArgs('arg1Name : arg1Value, arg2Name : arg2Value')
	 * @param String $args
	 * @return array<String, String>
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
		$viewFolder = f_util_FileUtils::buildWebeditPath('modules', $request->getModuleName(), 'config', 'views');
		$viewConfigFile = $viewFolder.'/'.$name.".php";
		if (file_exists($viewConfigFile))
		{
			include($viewConfigFile);
		}
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
			$templateName = ucfirst($request->getModuleName()) .'-'. $this->getName($request);
			$templateLoader = TemplateLoader::getInstance()->setMimeContentType(K::HTML)->setDirectory('templates')->setPackageName('modules_' . $request->getModuleName());
			$this->templateObject = $templateLoader->load($templateName);
		}
		
		$template = $this->templateObject;
		$model = array_merge($request->getParameters(), f_mvc_HTTPRequest::getInstance()->getSession()->getAttributes(), $request->getAttributes());
		$model["context"] = $request->getContext();
		$model["website_page"] = $request->getContext()->getAttribute("website_page");
		$template->importAttributes($model);
		self::pushTemplate($template);
		try
		{
			$response->getWriter()->write($template->execute());
			self::popTemplate();	
		}
		catch (Exception $e)
		{
			// simulate finally
			self::popTemplate();
			throw $e;
		}
	}
	
	private static $templates = array();
	
	private static function pushTemplate($template)
	{
		 self::$templates[] = $template;
	}
	
	private static function popTemplate()
	{
		array_pop(self::$templates);
	}
	
	static function getCurrentTemplate()
	{
		return f_util_ArrayUtils::lastElement(self::$templates);
	}
}