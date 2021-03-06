<?php

class LinkHelper
{	
	/**
	 * @param array $queryParams
	 * @param website_persistentdocument_website $website
	 * @return f_web_ParametrizedLink
	 */
	public static function getParametrizedLink($queryParams = array(), $website = NULL)
	{
	    if ($website === NULL)
	    {
	        $website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
	    }
	    $link = new f_web_ParametrizedLink($website->getProtocol(), $website->getDomain(), f_web_HttpLink::SITE_PATH);
	    $link->setQueryParameters($queryParams);
	    return $link;
	}
		
	/**
	 * @param array $queryParams
	 * @return f_web_ParametrizedLink
	 */
	public static function getUIParametrizedLink($queryParams = array())
	{
	    $link = new f_web_ParametrizedLink(Framework::getUIProtocol(), Framework::getUIDefaultHost(), f_web_HttpLink::UI_PATH);
	    $link->setQueryParameters($queryParams);
	    return $link;
	}
	
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @param website_persistentdocument_website $website
	 * @return f_web_ParametrizedLink
	 */
	public static function getActionLink($moduleName, $actionName, $website = NULL)
	{
	    if ($website === NULL)
	    {
	        $website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
	    }
	    $lang = RequestContext::getInstance()->getLang();
	    $link = website_UrlRewritingService::getInstance()->getDefaultActionWebLink($moduleName, $actionName, $website, $lang, array());
	    return $link;
	}
		
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @return f_web_ParametrizedLink
	 */
	public static function getUIActionLink($moduleName, $actionName)
	{
	    $link = new f_web_ParametrizedLink(Framework::getUIProtocol(), Framework::getUIDefaultHost(), f_web_HttpLink::UI_PATH);
	    $link->setQueryParameters(array('module' => $moduleName, 'action' => $actionName));
	    return $link;
	}
	
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @return f_web_ParametrizedLink
	 */
	public static function getUIChromeActionLink($moduleName, $actionName)
	{
		if (!isset($_SESSION['ChromeBaseUri']))
		{
			return self::getUIActionLink($moduleName, $actionName);
		}
	    $link = new f_web_ChromeParametrizedLink($_SESSION['ChromeBaseUri']);
	    $link->setQueryParameters(array('module' => $moduleName, 'action' => $actionName));
	    return $link;
	}	
	
	/**
	 * @param string $ressourceName
	 * @param website_persistentdocument_website $website
	 * @return f_web_ResourceLink
	 */
	public static function getRessourceLink($ressourceName, $website = NULL)
	{
		if ($website === NULL)
	    {
	        $website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
	    }
	    $link = new f_web_ResourceLink($website->getProtocol(), $website->getDomain());
	    $link->setPath($ressourceName);
	    return $link;
	}	
	

	
	/**
	 * @param string $ressourceName
	 * @return f_web_ResourceLink
	 */
	public static function getUIRessourceLink($ressourceName)
	{
	    $link = new f_web_ResourceLink(Framework::getUIProtocol(), Framework::getUIDefaultHost());
	    $link->setPath($ressourceName);
	    return $link;
	}
	
	/**
	 * @param string $ressourceName
	 * @return f_web_ResourceLink
	 */
	public static function getUIChromeRessourceLink($ressourceName)
	{
		if (!isset($_SESSION['ChromeBaseUri']))
		{
			return self::getUIRessourceLink($ressourceName);
		}
		
	    $link = new f_web_ChromeParametrizedLink($_SESSION['ChromeBaseUri']);
	    $link->setArgSeparator(f_web_HttpLink::ESCAPE_SEPARATOR);
	    $link->setQueryParameters(array('module' => 'uixul', 'action' => 'GetChromeRessource', 'path' => $ressourceName));
	    return $link;
	}	

	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string $lang
	 * @param array $parameters
	 * @return string or null
	 */
	public static function getDocumentUrl($document, $lang = null, $parameters = array())
	{
		return self::getDocumentUrlForWebsite($document, null, $lang, $parameters);
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param website_persistentdocument_website $website
	 * @param string $lang
	 * @param array $parameters
	 * @return string or null
	 */
	public static function getDocumentUrlForWebsite($document, $website, $lang = null, $parameters = array())
	{
		if (!($document instanceof f_persistentdocument_PersistentDocument))
		{
			Framework::error(f_util_ProcessUtils::getBackTrace());
			return null;
		}
		if ($lang === null) {$lang = RequestContext::getInstance()->getLang();}
		return website_UrlRewritingService::getInstance()->getDocumentLinkForWebsite($document, $website, $lang, $parameters)->getUrl();
	}
	
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @param array $parameters
	 * @return string or null
	 */
	public static function getActionUrl($moduleName, $actionName, $parameters = array())
	{
		return self::getActionUrlForWebsite($moduleName, $actionName, null, null, $parameters);
	}	
	
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @param website_persistentdocument_website $website
	 * @param string $lang
	 * @param array $parameters
	 * @return string or null
	 */
	public static function getActionUrlForWebsite($moduleName, $actionName, $website = null, $lang = null, $parameters = array())
	{
		if (empty($moduleName) || empty($actionName))
		{
			Framework::error(f_util_ProcessUtils::getBackTrace());
			return null;
		}
		if ($website === null){$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();}
		if ($lang === null) {$lang = RequestContext::getInstance()->getLang();}
		return website_UrlRewritingService::getInstance()->getActionLinkForWebsite($moduleName, $actionName, $website, $lang, $parameters)->getUrl();
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 */
	public static function getPermalink($document)
	{
		return self::getActionUrl('website', 'Permalink', array('cmpref' => $document->getId()));
	}
	
	/**
	 * @param string $tag
	 * @param string $lang
	 * @param array $parameters
	 * @return string or null
	 */
	public static function getTagUrl($tag, $lang = null, $parameters = array())
	{
		return self::getTagUrlForContext($tag, null, $lang, $parameters);
	}
	
	/**
	 * @param string $tag
	 * @param f_persistentdocument_PersistentDocument $context
	 * @param string $lang
	 * @param array $parameters
	 * @return string or null
	 */
	public static function getTagUrlForContext($tag, $context = null, $lang = null, $parameters = array())
	{
		if (empty($tag))
		{
			Framework::error(f_util_ProcessUtils::getBackTrace());
			return null;
		}		
		if ($lang === null) {$lang = RequestContext::getInstance()->getLang();}
		$website = ($context instanceof website_persistentdocument_website) ? $context : null;
		
		$urs = website_UrlRewritingService::getInstance();		
		$ts = TagService::getInstance();
		try 
		{
			$document = null;
			if ($ts->isExclusiveTag($tag))
			{
				$document = $ts->getDocumentByExclusiveTag($tag);
			}
			else if ($ts->isFunctionalTag($tag))
			{
				$pageId = null;
				if ($context === null)
				{
					$pageId = website_WebsiteModuleService::getInstance()->getCurrentPageId();				
				}
				else if ($context instanceof website_persistentdocument_page)
				{
					$pageId = $context->getId();
				}
				else if ($context instanceof website_persistentdocument_topic)
				{
					$page = $context->getIndexPage();
					if ($page) {$pageId = $page->getId();}
				}
				
				if ($pageId)
				{
					$currentPage = DocumentHelper::getDocumentInstance($pageId);
					$document = $ts->getDocumentBySiblingTag($tag, $currentPage);
				}
			}
			else if ($ts->isContextualTag($tag) && $ts->getTagContext($tag) == 'modules_website/website')
			{
				if ($context === null)
				{
					$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
				}
				else if ($context instanceof f_persistentdocument_PersistentDocument)
				{
					$websiteId = $context->getDocumentService()->getWebsiteId($context);
					if ($websiteId) {$website = website_persistentdocument_website::getInstanceById($websiteId);}
				}
				
				if ($website !== null && !$website->isNew())
				{
					$document = $ts->getDocumentByContextualTag($tag, $website);
				}
			}
			else
			{
				$taggedDocuments = $ts->getDocumentsByTag($tag);
				if (f_util_ArrayUtils::isNotEmpty($taggedDocuments))
				{
					$document = $taggedDocuments[0];
				}
			}
			
			if ($document !== null)
			{
				return $urs->getDocumentLinkForWebsite($document, $website, $lang, $parameters)->getUrl();
			}
			else
			{
				Framework::warn(__METHOD__ . ' no document found for tag ' . $tag);
			}
		} 
		catch (Exception $e)
		{
			Framework::warn(__METHOD__ . ' ' . $e->getMessage());
		}
		return null;
	}

	/**
	 * Return the URL of the home page of the current website
	 * @return string
	 */
	public static function getHomeUrl()
	{
	    $website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
	    $lang = RequestContext::getInstance()->getLang();
        return website_UrlRewritingService::getInstance()->getRewriteLink($website, $lang, '')->getUrl();
	}


	/**
	 * Return the URL of the help page of the current website
	 * @return string
	 */
	public static function getHelpUrl()
	{
	    $ws = website_WebsiteModuleService::getInstance();
		try
		{
		    $website = $ws->getCurrentWebsite();
            $page = TagService::getInstance()->getDocumentByContextualTag('contextual_website_website_help', $website);
            if ($page !== null)
            {
    			return self::getDocumentUrl($page);
            }
		}
		catch (Exception $e)
		{
			Framework::exception($e);
		}
	    return '#';
	}
	
	/**
	 * @param string $url
	 * @return f_web_ParametrizedLink
	 */
	public static function buildLinkFromUrl($url)
	{
		if (f_util_StringUtils::isEmpty($url)) {return null;}
		$infos = parse_url($url);
		$link = new f_web_ParametrizedLink($infos['scheme'], $infos['host'], (isset($infos['path'])) ? $infos['path']: '/');
		if (isset($infos['query']) && $infos['query'] != '')
		{
			$parameters = array();
			parse_str($infos['query'], $parameters);
			if (count($parameters))
			{
				$link->setQueryParameters($parameters);
			}
		}
		if (isset($infos['fragment']) && $infos['fragment'] != '')
		{
			$link->setFragment($infos['fragment']);
		}
		return $link;
	}


	/**
	 * Build a full link (<a/> element) for the given document.
	 *
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string $lang
	 * @param string $class
	 * @param string $title
	 * @param array<string=>string> $attributes
	 * @return string
	 */
	public static function getLink($document, $lang = null, $class = 'link', $title = '', $attributes = null)
	{
	    return self::buildLink($document, $lang, $class, $title, false, $attributes);
	}
	
	/**
	 * Build a full link (<a/> element) for the given document.
	 *
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string $lang
	 * @param string $class
	 * @param string $title
	 * @param boolean $popup
	 * @param array<string=>string> $attributes
	 * @return string
	 */
	private static function buildLink($document, $lang = null, $class = 'link', $title = '', $popup = false, $attributes = null)
	{
		if (!is_array($attributes))
		{
			$attributes = array();
		}
		
		if (isset($attributes['label']))
		{
			$label = $attributes['label'];
			unset($attributes['label']);
		}
		else
		{
			$label = $document->getNavigationLabel();
		}
		if (empty($label))
		{
			return '';
		}
		
		if ($lang === null)
		{
			$lang = RequestContext::getInstance()->getLang();
		}
		if (!isset($attributes['lang']))
		{
			$attributes['lang'] = $lang;
			$attributes['xml:lang'] = $lang;
		}		
		$attributes['href'] = LinkHelper::getDocumentUrl($document, $lang);
		
		if (!empty($class))
		{
			$attributes['class'] = (isset($attributes['class'])) ? ($class . ' ' . $attributes['class']) : $class;
		}
		
		if (!empty($title) && !isset($attributes['title']))
		{
			$attributes['title'] = f_util_StringUtils::shortenString($title, 80);
		}
		
		// @deprecated popup parameter
		if ($popup !== false)
		{
			$attributes['onclick'] = 'return accessiblePopup(this);';
			$attributes['class'] = (isset($attributes['class'])) ? ($attributes['class'] . ' popup') : 'popup';
		}

		return '<a ' . f_util_HtmlUtils::buildAttributes($attributes) . '>' . f_util_HtmlUtils::textToHtml($label) . '</a>';
	}

	/**
	 * Returns the <a/> element for the link "Add to favorites".
	 *
	 * @param string $label Text in the link.
	 * @param string $title Title of the link (tooltip).
	 * @param string $class CSS class name.
	 * @return string
	 */
	public static function getAddToFavoriteLink($label = null, $title = null, $class = null)
	{
		try
		{
			$url = LinkHelper::getTagUrl('contextual_website_website_favorite');
			if (f_util_StringUtils::isEmpty($url))
			{
				$url = '#';
			}
		}
		catch (Exception $e)
		{
			if (Framework::isDebugEnabled())
			{
				Framework::exception($e);
			}
			$url = '#';
		}
		
		if (is_null($label))
		{
			$label = LocaleService::getInstance()->transFO('m.website.frontoffice.addtofavorite', array('ucf', 'html'));
		}
		if (is_null($title))
		{
			$title = LocaleService::getInstance()->transFO('m.website.frontoffice.addtofavoritetitle', array('ucf', 'attr'));
		}
		if (is_string($class))
		{
			$class = ' class="'.$class.'"';
		}
		return sprintf(
			'<a href="%s" title="%s"%s onclick="accessibleAddToFavorite(this); return false;">%s</a>',
			$url, $title, $class, $label
			);
	}


	/**
	 * Returns the <a/> element for the link "Print this page".
	 *
	 * @param string $label Text in the link.
	 * @param string $title Title of the link (tooltip).
	 * @param string $class CSS class name.
	 * @return string
	 */
	public static function getPrintLink($label = null, $title = null, $class = null)
	{
		try
		{
			$url = LinkHelper::getTagUrl('contextual_website_website_print');
			if (f_util_StringUtils::isEmpty($url))
			{
				$url = '#';
			}
		}
		catch (Exception $e)
		{
			if (Framework::isDebugEnabled())
			{
				Framework::exception($e);
			}
			$url = '#';
		}		

		if (is_null($label))
		{
			$label = LocaleService::getInstance()->transFO('m.website.frontoffice.print', array('ucf', 'html'));
		}
		if (is_null($title))
		{
			$title = LocaleService::getInstance()->transFO('m.website.frontoffice.printtitle', array('ucf', 'attr'));
		}
		if (is_string($class))
		{
			$class = ' class="'.$class.'"';
		}
		return sprintf(
			'<a href="%s" title="%s"%s onclick="accessiblePrint(this); return false;">%s</a>',
			$url, $title, $class, $label
			);
	}


	/**
	 * Returns the <a/> element for the link to the help page.
	 *
	 * @param string $label Text in the link.
	 * @param string $title Title of the link (tooltip).
	 * @param string $class CSS class name.
	 * @return string
	 */
	public static function getHelpLink($label = null, $title = null, $class = null)
	{
		try
		{
			$url = LinkHelper::getTagUrl('contextual_website_website_help');
			if (f_util_StringUtils::isEmpty($url))
			{
				$url = '#';
			}
		}
		catch (Exception $e)
		{
			if (Framework::isDebugEnabled())
			{
				Framework::exception($e);
			}
			$url = '#';
		}	
		
		if (is_null($label))
		{
			$label = LocaleService::getInstance()->transFO('m.website.frontoffice.help', array('ucf', 'html'));
		}
		if (is_null($title))
		{
			$title = LocaleService::getInstance()->transFO('m.website.frontoffice.helptitle', array('ucf', 'attr'));
		}
		if (is_string($class))
		{
			$class = ' class="'.$class.'"';
		}
		return sprintf('<a href="%s" title="%s"%s>%s</a>', $url, $title, $class, $label);
	}


	/**
	 * Returns the <a/> element for the link to the legal notice page.
	 *
	 * @param string $label Text in the link.
	 * @param string $title Title of the link (tooltip).
	 * @param string $class CSS class name.
	 * @return string
	 */
	public static function getLegalNoticeLink($label = null, $title = null, $class = null)
	{
		
		try
		{
			$url = LinkHelper::getTagUrl('contextual_website_website_legal');
			if (f_util_StringUtils::isEmpty($url))
			{
				$url = '#';
			}
		}
		catch (Exception $e)
		{
			if (Framework::isDebugEnabled())
			{
				Framework::exception($e);
			}
			$url = '#';
		}	

		if (is_null($label))
		{
			$label = LocaleService::getInstance()->transFO('m.website.frontoffice.legalnotice', array('ucf', 'html'));
		}
		if (is_null($title))
		{
			$title =  LocaleService::getInstance()->transFO('m.website.frontoffice.legalnoticetitle', array('ucf', 'attr'));
		}
		if (is_string($class))
		{
			$class = ' class="'.$class.'"';
		}
		return sprintf('<a href="%s" title="%s"%s>%s</a>', $url, $title, $class, $label);
	}

	/**
	 * Returns the current URL with all the parameters.
	 * If $extraAttributes is an array, the parameters it contains will override
	 * the ones of the current URL.
	 * @param array $extraAttributes
	 * @return string
	 */
	public static function getCurrentUrl($extraAttributes = array())
	{
		$rq = RequestContext::getInstance();
		if ($rq->getAjaxMode())
		{
			$requestUri = $rq->getAjaxFromURI();
		}
		else
		{
			$requestUri = $rq->getPathURI();
		}
		$parts = explode('?', $requestUri);
		$currentLink = new f_web_ParametrizedLink($rq->getProtocol(), $_SERVER['SERVER_NAME'], $parts[0]);
		$flatParams = array();
		if (isset($parts[1]) && $parts[1] != '')
		{
			parse_str($parts[1], $queryParameters);
			$flatParams = f_web_HttpLink::flattenArray($queryParameters);
		}
		if (is_array($extraAttributes) && count($extraAttributes))
		{
		    $flatParams = array_merge($flatParams, f_web_HttpLink::flattenArray($extraAttributes));
		}
		if (count($flatParams))
		{
		    $currentLink->setQueryParameters($flatParams);
		}
		return $currentLink->getUrl();
	}
	
	// Deprecated.
	
	/**
	 * @deprecated (will be removed in 4.0)
	 */
	public static function getCurrentUrlComplete($extraAttributes = array())
	{
		return self::getCurrentUrl($extraAttributes);
	}
	
	/**
	 * @deprecated (will be removed in 4.0) use LinkHelper::getDocumentUrl or LinkHelper::getActionUrl
	 */
	public static function getUrl()
	{
		$args = func_get_args();
		$argsCount = count($args);		
		if ($argsCount >= 1 && $args[0] instanceof f_persistentdocument_PersistentDocument)
		{
			if (!isset($args[1]))
			{
				$args[1] = RequestContext::getInstance()->getLang(); // lang
			}
			if (!isset($args[2]))
			{
				$args[2] = array(); // additional parameters
			}
			return self::getDocumentUrl($args[0], $args[1], $args[2]);
		}
		else if (($argsCount == 2 || $argsCount == 3) && is_string($args[0]) && is_string($args[1]))
		{
			if (!isset($args[2]))
			{
				$args[2] = array(); // additional parameters
			}
			if (!isset($args[2]['lang']))
			{
				$args[2]['lang'] = RequestContext::getInstance()->getLang(); // lang
			}
			return self::getActionUrl($args[0], $args[1], $args[2]);
		}
		return '';
	}
	
	/**
	 * @deprecated (will be removed in 4.0)
	 */
	public static function getPopupLink($document, $lang = null, $class = 'link', $title = '', $attributes = null, $width = null, $height = null)
	{
		return self::buildLink($document, $lang, $class, $title, true, $attributes);
	}
	
	/**
	 * @deprecated
	 */
	public static function _buildLink($document, $lang = null, $class = 'link', $title = '', $popup = false, $attributes = null)
	{
		return self::buildLink($document, $lang, $class, $title, $popup, $attributes);
	}
}