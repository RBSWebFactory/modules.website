<?php

class website_PageRessourceService extends BaseService
{
	const GLOBAL_SCREEN_NAME = 'screen';
	const GLOBAL_PRINT_NAME = 'print';

	/**
	 * @var f_web_CSSVariables
	 */
	private $skin;

	/**
	 * @var website_persistentdocument_page
	 */
	private $page;

	/**
	 * @var website_PageRessourceService
	 */
	private static $instance;


	/**
	 * @return website_PageRessourceService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}


	/**
	 * @param f_web_CSSVariables $skin
	 */
	public function setSkin($skin)
	{
		$this->skin = $skin;
	}

	/**
	 * @return f_web_CSSVariables
	 */
	private function getSkin()
	{
		return $this->skin;
	}

	/**
	 * @return website_persistentdocument_page
	 */
	public function getPage()
	{
		return $this->page;
	}

	/**
	 * @param website_persistentdocument_page $page
	 */
	public function setPage($page)
	{
		$this->page = $page;
	}

	/**
	 * @param website_persistentdocument_page $page
	 * @param boolean $throwException
	 * @return theme_persistentdocument_pagetemplate
	 */	
	public function getPageTemplate($page, $throwException = true)
	{		
		$template = theme_PagetemplateService::getInstance()->getByCodeName($page->getTemplate());
		if ($throwException && !$template)
		{
			throw new TemplateNotFoundException($page->getTemplate());
		}
		return $template;
	}
	
	/**
	 * @param website_persistentdocument_page $page
	 * @return DOMDocument
	 */
	public function getPageDocType($page)
	{
		$template = $this->getPageTemplate($page, false);
		return  ($template) ? $template->getDocTypeDeclaration() : null;
	}	
	
	/**
	 * @param website_persistentdocument_page $page
	 * @return DOMDocument
	 */
	public function getPagetemplateAsDOMDocument($page)
	{
		return $this->getPageTemplate($page)->getDOMContent();
	}

	/**
	 * @param website_persistentdocument_page $page
	 * @return DOMDocument
	 */
	public function getBackpagetemplateAsDOMDocument($page)
	{
		return $this->getPageTemplate($page)->getDOMContent();
	}
	
	/**
	 * @param integer[] $ancestorsId
	 * @return string
	 */
	public function getContainerStyleIdByAncestorIds($ancestorsId)
	{		
		if (f_util_ArrayUtils::isEmpty($ancestorsId))
		{
			return null;
		}
		
		$ancestors = array();
		foreach (array_reverse($ancestorsId) as $ancestorId)
		{
			$ancestors[] = DocumentHelper::getDocumentInstance($ancestorId);
		}
		return $this->getContainerStyleId($ancestors);
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument[] $ancestors
	 * @return string
	 */
	public function getContainerStyleIdByAncestors($ancestors)
	{		
		if (f_util_ArrayUtils::isEmpty($ancestors))
		{
			return null;
		}
		return $this->getContainerStyleId(array_reverse($ancestors));
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument[] $ancestors
	 * @return string
	 */
	private function getContainerStyleId($ancestors)
	{
		foreach ($ancestors as $ancestor)
		{
			if ($ancestor instanceof website_persistentdocument_topic  || 
				$ancestor instanceof website_persistentdocument_website) 
			{
				$stylesheet = $ancestor->getStylesheet();
				if ($stylesheet !== null)
				{
					return 'modules.website.' . $stylesheet;
				}
			}
		}
		return null;	
	}
	
	/**
	 * Gets the <link .../> tag for the current page's template stylesheet
	 *
	 * @return String
	 */
	public function getPageStylesheetInclusion()
	{
		$page = $this->getPage();
		if ($page === null)
		{
			return null;
		}
		$template = $this->getPageTemplate($page, false);
		if ($template === null)
		{
			return null;
		}		
		$stylesheetName = $template->getId() . '/' . self::GLOBAL_SCREEN_NAME;
		$rc = RequestContext::getInstance();
		$relativePath = $this->getStylesheetRelativePath($stylesheetName, $rc->getUserAgentType(), $rc->getUserAgentTypeVersion(), $rc->getProtocol());		
		return $this->buildStylesheetInclusion($relativePath, "screen");
	}
	
	/**
	 * Gets the <style media="screen">.... tag for the current page's template stylesheet
	 * @return String
	 */
	public function getPageStylesheetInLine()
	{
		$page = $this->getPage();
		if ($page === null)
		{
			return null;
		}
		$template = $this->getPageTemplate($page, false);
		if ($template === null)
		{
			return null;
		}
		
		$rc = RequestContext::getInstance();
		$css = $this->getTemplateScreenStylesheet($template, $rc->getUserAgentType(), $rc->getUserAgentTypeVersion(), $rc->getProtocol());
		return $this->buildStylesheetInline($css);
	}
	
	public function getPageJavascriptInclusion()
	{
		$page = $this->getPage();
		if ($page === null)
		{
			return null;
		}
		$template = $this->getPageTemplate($page, false);
		if ($template === null)
		{
			return null;
		}
		$relativePath = $this->getJavascriptRelativePath('template', $template->getId());
		return $this->buildJavascriptInclusion($relativePath);
	}
	
	public function getPageJavascriptInlineInclusion($scriptNames)
	{
		
		$page = $this->getPage();
		if ($page === null)
		{
			return null;
		}
		$template = $this->getPageTemplate($page, false);
		if ($template === null)
		{
			return null;
		}
		sort($scriptNames, SORT_STRING);
		
		$scriptNames[] = 'page';
		$fullNames = implode('/', $scriptNames);
		$relativePath = $this->getJavascriptRelativePath($fullNames, $template->getId());
		return $this->buildJavascriptInclusion($relativePath);
	}
	
	/**
	 * @param theme_persistentdocument_pagetemplate $template
	 * @param string $engine
	 * @param string $version
	 * @param string $protocol
	 * @return string
	 */
	public function getTemplateScreenStylesheet($template, $engine, $version, $protocol)
	{	
		$stylesheetName = $template->getId() . '/' . self::GLOBAL_SCREEN_NAME;
		$relativePath = $this->getStylesheetRelativePath($stylesheetName, $engine, $version, $protocol);
		$fullengine =  $engine.'.'. $version;
		$absolutePath = f_util_FileUtils::buildDocumentRootPath($relativePath);
		if (!file_exists($absolutePath) || file_exists($relativePath.".deleted") || Framework::inDevelopmentMode())
		{
			f_util_FileUtils::mkdir(dirname($absolutePath));
			$fh = fopen($absolutePath, 'w');
			foreach ($template->getScreenStyleIds() as $styleName) 
			{
				$this->appendStylesheetContent($fh, $styleName, $fullengine);
			}
			fclose($fh);
		}
		if (file_exists($relativePath.".deleted"))
		{
			unlink($relativePath.".deleted");
		}
		return file_get_contents($absolutePath);			
	}	
	
	/**
	 * Gets the <link .../> tag for the combination of all print stylesheets
	 *
	 * @return String
	 */
	public function getPagePrintStylesheetInclusion()
	{
		$page = $this->getPage();
		if ($page === null)
		{
			return null;
		}
		$template = $this->getPageTemplate($page, false);
		if ($template === null)
		{
			return null;
		}		
		$stylesheetName = $template->getId() . '/' . self::GLOBAL_PRINT_NAME;
		
		$rc = RequestContext::getInstance();
		$relativePath = $this->getStylesheetRelativePath($stylesheetName, $rc->getUserAgentType(), $rc->getUserAgentTypeVersion(), $rc->getProtocol());
		return $this->buildStylesheetInclusion($relativePath, "print");
	}
	
	/**
	 * @param theme_persistentdocument_pagetemplate $template
	 * @param string $engine
	 * @param string $version
	 * @param string $protocol
	 * @return string
	 */
	public function getTemplatePrintStylesheet($template, $engine, $version, $protocol)
	{	
		$stylesheetName = $template->getId() . '/' . self::GLOBAL_PRINT_NAME;
		$relativePath = $this->getStylesheetRelativePath($stylesheetName, $engine, $version, $protocol);
		$fullengine =  $engine.'.'. $version;
		$absolutePath = f_util_FileUtils::buildDocumentRootPath($relativePath);
		if (!file_exists($absolutePath) || file_exists($relativePath.".deleted") || Framework::inDevelopmentMode())
		{
			f_util_FileUtils::mkdir(dirname($absolutePath));
			$fh = fopen($absolutePath, 'w');
			foreach ($template->getPrintStyleIds() as $styleName) 
			{
				$this->appendStylesheetContent($fh, $styleName, $fullengine);
			}
			fclose($fh);
		}
		if (file_exists($relativePath.".deleted"))
		{
			unlink($relativePath.".deleted");
		}
		return file_get_contents($absolutePath);			
	}	
	
	public function getStylesheet($name, $engine, $version, $protocol)
	{
		$relativePath = $this->getStylesheetRelativePath($name, $engine, $version, $protocol);
		$absolutePath = f_util_FileUtils::buildDocumentRootPath($relativePath);
		$fullengine =  $engine.'.'. $version;
		if (!file_exists($absolutePath) || file_exists($relativePath.".deleted") || Framework::inDevelopmentMode())
		{
			f_util_FileUtils::mkdir(dirname($absolutePath));
			$fh = fopen($absolutePath, 'w');
			$this->appendStylesheetContent($fh, $name, $fullengine);
			fclose($fh);
		}
		if (file_exists($relativePath.".deleted"))
		{
			unlink($relativePath.".deleted");
		}
		return file_get_contents($absolutePath);
	}
	
	private $globalTemplateName = 'PageDynamic-ContentBasis';
	
	public function setGlobalTemplateName($globalTemplateName)
	{
		$this->globalTemplateName = $globalTemplateName;
	}
	
	/**
	 * Returns the path of the "Global template" used to render the page
	 *
	 * @return String
	 */
	public function getGlobalTemplate()
	{
		return TemplateResolver::getInstance()->setPackageName('modules_website')->setDirectory('templates')
		->setMimeContentType('php')
		->getPath($this->globalTemplateName);
	}

	/**
	 * Returns the array of scripts for the page
	 *
	 * @return Array
	 */

	public function getAvailableScripts()
	{
		$page = $this->getPage();
		if ($page === null) {return array();}
	
		$template = $this->getPageTemplate($page, false);
		if ($template === null) {return array();}
		
		$frontOfficeScriptsCache = f_util_FileUtils::buildCachePath("frontofficeScripts", str_replace('/', '.', $template->getCodename()));
		if (Framework::inDevelopmentMode())
		{
			if (file_exists($frontOfficeScriptsCache))
			{
				unlink($frontOfficeScriptsCache);
			}
			return $template->getScriptIds();
		}
		if (!file_exists($frontOfficeScriptsCache))
		{
			$availableScripts = $template->getScriptIds();
			f_util_FileUtils::writeAndCreateContainer($frontOfficeScriptsCache, serialize($availableScripts), f_util_FileUtils::OVERRIDE);
			return $availableScripts;
		}
		return unserialize(file_get_contents($frontOfficeScriptsCache));
	}

	/**
	 * @param String $name
	 * @param String $engine
	 * @param String $version
	 * @param String $protocol
	 * @return String
	 */
	private function getStylesheetRelativePath($name, $engine, $version, $protocol = 'http')
	{
		$fullName = $name;
		if ($this->skin)
		{
			$fullName .= '-' . $this->skin->getIdentifier();
		}
		$fullName .= '.css';
		$lang = RequestContext::getInstance()->getLang();
		$websiteId = website_WebsiteModuleService::getInstance()->getCurrentWebsite()->getId();
		if ($websiteId < 0) {$websiteId = 0;}
		return f_util_FileUtils::buildPath('cache', 'www', 'css', $protocol , $websiteId, $lang, $engine, $version, $fullName);
	}
	
	/**
	 * @param String $name
	 * @return String
	 */
	private function getJavascriptRelativePath($name, $templateId)
	{
		$fullName = $name . '.js';
		$lang = RequestContext::getInstance()->getLang();
		$websiteId = website_WebsiteModuleService::getInstance()->getCurrentWebsite()->getId();
		if ($websiteId < 0) {$websiteId = 0;}
		$protocol = website_WebsiteModuleService::getInstance()->getCurrentWebsite()->getProtocol();
		return f_util_FileUtils::buildPath('cache', 'www', 'js', $protocol, $websiteId, $lang, $templateId, $fullName);
	}

	/**
	 * @param String $styleSheetRelativePath
	 * @param String $mediaType (screen | print)
	 * @return String
	 */
	private function buildStylesheetInclusion($styleSheetRelativePath, $mediaType)
	{
		$inclusionSrc = LinkHelper::getRessourceLink('/' . $styleSheetRelativePath)->getUrl();
		return '<link rel="stylesheet" href="' . $inclusionSrc . '" type="text/css" media="' . $mediaType . '" />';
	}
	
	/**
	 * @param String $styleSheetRelativePath
	 * @return String
	 */
	private function buildJavascriptInclusion($javascriptRelativePath)
	{
		$inclusionSrc = LinkHelper::getRessourceLink('/' . $javascriptRelativePath)->getUrl();
		return '<script src="' . $inclusionSrc . '" type="text/javascript"></script>';
	}
	
	/**
	 * @param string $css
	 * @param string $mediaType (screen | print)
	 * @return string
	 */
	private function buildStylesheetInline($css, $mediaType = 'screen')
	{
		if (empty($css)) {return null;}
		return '<style type="text/css" media="' . $mediaType . '">' . $css . '</style>';
	}

	/**
	 * @param Ressource $fileHandle
	 * @param String $styleName
	 * @param String $fullengine
	 */
	private function appendStylesheetContent($fileHandle, $styleName, $fullengine)
	{
		$content = StyleService::getInstance()->getCSS($styleName, $fullengine, $this->getSkin());
		if ($content !== null)
		{
			fwrite($fileHandle, $content);
		}
	}
}
