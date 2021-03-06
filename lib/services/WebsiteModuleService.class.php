<?php
class website_WebsiteModuleService extends f_persistentdocument_DocumentService
{
	/**
	 * @var website_WebsiteModuleService
	 */
	private static $instance;

	/**
	 * @var Integer The current page ID.
	 * Set by website/DisplayAction.
	 */
	private $currentPageId = null;
	private $currentPageAncestorsIds = array();
	private $currentPageAncestors = array();


	/**
	 * @var website_persistentdocument_website
	 */
	private $defaultWebsite = null;

	/**
	 * @var website_persistentdocument_website
	 */
	private $currentWebsite = null;


    /**
     * Tableau des modeles de documents pouvant apparaitre dans un menu
     * @var array<string>
     */
    public static $modelNamesForMenu = array('modules_website/topic',
    	'modules_website/page', 'modules_website/pagegroup',
    	'modules_website/pageexternal', 'modules_website/pagereference');

	/**
	 * Returns the unique instance of website_WebsiteModuleService.
	 * @return website_WebsiteModuleService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	protected function __construct()
	{
		parent::__construct();
		$this->tm = f_persistentdocument_TransactionManager::getInstance();
		$this->pp = f_persistentdocument_PersistentProvider::getInstance();
	}

	// --- Configuration methods ---

	/**
	 * Returns the index page for a topic or a website.
	 *
	 * @param website_persistentdocument_website | website_persistentdocument_topic $topic
	 * @param boolean $getFirstPageIfNotFound If true, and if no index page is defined, get the first child page.
	 *
	 * @return website_persistentdocument_page
	 *
	 * @throws IllegalArgumentException
	 */
	public function getIndexPage($topic, $getFirstPageIfNotFound = true)
	{
		$indexPage = $topic->getIndexPage();
		if (is_null($indexPage) && $topic instanceof website_persistentdocument_topic && $getFirstPageIfNotFound)
		{
			$indexPage = $this->getFirstChildOf($topic);
		}

		return $indexPage;
	}

	/**
	 * Sets the index page for a topic.
	 *
	 * @param website_persistentdocument_page $page
	 * @param Boolean $userSetting
	 */
	public function setIndexPage($page, $userSetting = false)
	{
		//Recuperation de la page
		if ($page instanceof website_persistentdocument_pageversion)
		{
			$indexPage = website_PageversionService::getInstance()->getVersionOf(DocumentHelper::getByCorrection($page));
		}
		else if ($page instanceof website_persistentdocument_page)
		{
		    $indexPage = DocumentHelper::getByCorrection($page);
		}
		// Fix #736: external pages may not pe index pages.
		else
		{
		    throw new IllegalArgumentException('page', 'website_persistentdocument_page');
		}

		try
		{
			$this->tm->beginTransaction();
			// FIXME : what if $indexPage is under website directy ?!
			$topic = $indexPage->getTopic();
			website_TopicService::getInstance()->setIndexPage($topic, $indexPage, $userSetting);
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
		}
	}

	/**
	 * Removes the index page of the given topic. If the given document is a page
	 *
	 * @param website_persistentdocument_topic $topicOrPage
	 * @param Boolean $userSetting
	 */
	public function removeIndexPage($topicOrPage, $userSetting = false)
	{
		if ($topicOrPage instanceof website_persistentdocument_topic)
        {
            $topic = $topicOrPage;
            $indexPage = $topic->getIndexPage();
        }
        else if ($topicOrPage instanceof website_persistentdocument_pageversion)
		{
			$indexPage = website_PageversionService::getInstance()->getVersionOf(DocumentHelper::getByCorrection($topicOrPage));
			$topic = $indexPage->getDocumentService()->getParentOf($indexPage);
		}
		else if ($topicOrPage instanceof website_persistentdocument_page)
        {
        	$indexPage = DocumentHelper::getByCorrection($topicOrPage);
        	$topic = $indexPage->getDocumentService()->getParentOf($indexPage);
        }
        else
        {
            throw new IllegalArgumentException('topicOrPage', 'website_persistentdocument_page,website_persistentdocument_topic');
        }

		try
		{
			$this->tm->beginTransaction();
			website_TopicService::getInstance()->setIndexPage($topic, null, $userSetting);
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
			throw $e;
		}
	}

	/**
	 * Sets the homepage for a website.
	 *
	 * @param website_persistentdocument_page $page
	 */
	public function setHomePage($page)
	{
		if ($page instanceof website_persistentdocument_pageversion)
		{
			$indexPage = website_PageversionService::getInstance()->getVersionOf(DocumentHelper::getByCorrection($page));
		}
		else if ($page instanceof website_persistentdocument_page)
		{
		    $indexPage = DocumentHelper::getByCorrection($page);
		}
		else
		{
		    throw new IllegalArgumentException('page', 'website_persistentdocument_page');
		}

		$websites = $indexPage->getDocumentService()->getAncestorsOf($indexPage, 'modules_website/website');
        if (count($websites) == 1)
        {
    		try
    		{
    			$this->tm->beginTransaction();
    			$website = $websites[0];
    			$website->getDocumentService()->setHomePage($website, $indexPage);
    			$this->tm->commit();
    		}
    		catch (Exception $e)
    		{
    			$this->tm->rollBack($e);
    		}
        }
	}

	/**
	 * Returns the default website for the whole project.
	 *
	 * If a website is exclusively tagged with 'default_modules_website_default-website',
	 * this website will be returned. Otherwise, this method returns the first website it
	 * finds in the website module, <strong>using an undefined order</strong>.
	 *
	 * @return website_persistentdocument_website
	 */
	public function getDefaultWebsite()
	{
		if ($this->defaultWebsite === null)
		{
			try
			{
				$this->defaultWebsite = TagService::getInstance()->getDocumentByExclusiveTag('default_modules_website_default-website');
			}
			catch (TagException $e)
			{
				if (Framework::isDebugEnabled())
				{
			    	Framework::exception($e);
				}

				$this->defaultWebsite = website_WebsiteService::getInstance()->getNewDocumentInstance();
				$this->defaultWebsite->setLabel('Temporary web site');
				$this->defaultWebsite->setDomain(Framework::getUIDefaultHost());
				$protocol = RequestContext::getInstance()->getProtocol();
				$this->defaultWebsite->setProtocol($protocol);
				$this->defaultWebsite->setUrl($protocol . '://'. Framework::getUIDefaultHost());
			}
		}
		return $this->defaultWebsite;
	}


	/**
	 * @param website_persistentdocument_website $website
	 */
	public function setDefaultWebsite($website)
	{
		TagService::getInstance()->setExclusiveTag($website, 'default_modules_website_default-website');
		$this->defaultWebsite = $website;
	}

	/**
	 * @param string $domaine
	 * @param boolean $setLang
	 * @return website_persistentdocument_website
	 */
	public final function getWebsiteByUrl($domaine, $setLang = false)
	{
	    $domaines = $this->getWebsitesDomaine();
	    if (isset($domaines[$domaine]))
	    {
	        $data = $domaines[$domaine];
	        if ($setLang)
	        {
	            RequestContext::getInstance()->setLang($data['langs'][0]);
	        }

	        return $this->getDocumentInstance($data['id'], "modules_website/website");
	    }
	    return null;
	}

	/**
	 * @param boolean $setLang try to set the context language
	 * @return website_persistentdocument_website
	 */
	public final function getCurrentWebsite($setLang = false)
	{
		if ($this->currentWebsite === null)
		{
			$currentWebsite = null;

			if (isset($_SERVER['HTTP_HOST']))
			{
		    	$host = $_SERVER['HTTP_HOST'];
				if (Framework::isDebugEnabled())
		    	{
		        	Framework::debug(__METHOD__ . "($setLang, " . $host . ")");
		    	}
				$currentWebsite = $this->getWebsiteByUrl($host, $setLang);
			}

			if ($currentWebsite === null)
			{
			    $currentWebsite = $this->getDefaultWebsite();
				if ($setLang)
				{
					RequestContext::getInstance()->setLang($currentWebsite->getLang());
				}
			}

			$this->setCurrentWebsite($currentWebsite);
		}

		return $this->currentWebsite;
	}

	/**
	 * @param integer $websiteId
	 * @return website_persistentdocument_website
	 */
	public final function setCurrentWebsiteId($websiteId)
	{
		$this->setCurrentWebsite($this->getDocumentInstance($websiteId, 'modules_website/website'));
		return $this->currentWebsite;
	}


	/**
	 * @param website_persistentdocument_website $currentWebsite
	 */
	public final function setCurrentWebsite($currentWebsite)
	{
		if (Framework::isDebugEnabled())
	    {
	        Framework::debug(__METHOD__);
	    }

	   if (RequestContext::getInstance()->inHTTPS())
	    {
	        $currentWebsite->setProtocol('https');
	    }
	    $this->currentWebsite = $currentWebsite;
	}

	/**
	 * @param string $domaine
	 * @return array<id=>integer, localizebypath=>boolean, langs=>array<lang>>
	 */
	public function getWebsiteInfos($domaine)
	{
		$domaines = $this->getWebsitesDomaine();
		if (isset($domaines[$domaine]))
		{
			return $domaines[$domaine];
		}

		return null;
	}

	/**
	 * @return array<>
	 */
    private function getWebsitesDomaine()
    {
            $isCacheEnabled = (f_DataCacheService::getInstance()->isEnabled());
            if ($isCacheEnabled)
            {
                $simpleCache = f_DataCacheService::getInstance();
                $cacheItem = $simpleCache->readFromCache(__CLASS__, array('domaines'), array('modules_website/website'));
                
                if ($cacheItem !== null && $cacheItem->isValid())
                {
                    return unserialize($cacheItem->getValue('sites'));
                }
            }

            $domaines = $this->compileWebsitesDomaine();

            if ($isCacheEnabled)
            {
            	$cacheItem->setValue('sites', serialize($domaines));
            	$simpleCache->writeToCache($cacheItem);
            }

            return $domaines;
    }

    private function compileWebsitesDomaine()
    {
    	$rc = RequestContext::getInstance();
        $domaines = array();

        $websites = website_WebsiteService::getInstance()->getAll();

        $supportedLanguages = $rc->getSupportedLanguages();
        foreach ($websites as $website)
        {
        	$localizebypath = $website->getLocalizebypath();
        	$domaineInfo = array('id' => $website->getId(), 'localizebypath' => $localizebypath, 'langs' => array());
        	foreach ($supportedLanguages as $supportedLanguage)
        	{
        		if ($website->isLangAvailable($supportedLanguage))
        		{
        		   $domaine = $website->getDomainForLang($supportedLanguage);
        		   if (!isset($domaines[$domaine]))
        		   {
        		   		$domaines[$domaine] = $domaineInfo;
        		   }
        		   $domaines[$domaine]['langs'][] = $supportedLanguage;
        		}
        	}
        }
    	return $domaines;
    }

	/**
	 * Returns the parent website document for $document or null if no website
	 * document is a parent of $document.
	 *
	 * @param f_persistentdocument_PersistentDocument $document
	 * @return website_persistentdocument_website
	 */
	public function getParentWebsite($document)
	{
		$documentService = $document->getDocumentService();
		$websiteId = $documentService->getWebsiteId($document);
		if ($websiteId)
		{
			return DocumentHelper::getDocumentInstance($websiteId, 'modules_website/website');
		}
		return null;
	}

	/**
	 * Set the meta websiteId on the given document, using the parent document one.
	 * Warning: the document has to be persisted.
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param Integer $parentId
	 */
	public function setWebsiteMetaFromParentId($document, $parentId)
	{
		if ($parentId !== null)
		{
			$parent = $this->getDocumentInstance($parentId);
			if ($parent instanceof website_persistentdocument_website)
			{
				$website = $parent;
			}
			else
			{
				$website = website_WebsiteModuleService::getInstance()->getParentWebsite($parent);
			}
			$document->setMeta("websiteId", $website->getId());
		}
		else
		{
			$document->setMeta("websiteId", null);
		}
	}

	/**
	 * Indicates whether the given $website has a unique URL for its version in
	 * language $lang.
	 *
	 * @param website_persistentdocument_website $website
	 * @param string $lang
	 *
	 * @return boolean
	 */
	public function hasUniqueDomainNameForLang($website, $lang)
	{
		$urlForLang = $website->getUrlForLang($lang);
		$i18nWebsites = $this->pp->getI18nWebsitesFromUrl($urlForLang);
		return count($i18nWebsites) == 1;
	}


	/**
	 * This function set the currentPageId and calculate :
	 * 	- currentPageAncestors[Ids]
	 *  - currentWebsite
	 * @param Integer $currentPageId
	 */
	public function setCurrentPageId($currentPageId)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . ' ' . $currentPageId);
		}
		$this->currentPageId = $currentPageId;
		$this->currentPageAncestorsIds = array();
		$this->currentPageAncestors = array();
		$page = DocumentHelper::getDocumentInstance($this->currentPageId);
		$ancestors = $page->getDocumentService()->getAncestorsOf($page);
		foreach ($ancestors as $document)
		{
			if ($document instanceof website_persistentdocument_website)
			{
			    $this->currentPageAncestors[] = $document;
				$this->currentPageAncestorsIds[] = $document->getId();
				$this->setCurrentWebsite($document);
			}
			elseif ($document instanceof website_persistentdocument_topic)
			{
			    $this->currentPageAncestors[] = $document;
			    $this->currentPageAncestorsIds[] = $document->getId();
			}
		}
	}

	/**
	 * @return Integer
	 */
	public function getCurrentPageId()
	{
		return $this->currentPageId;
	}

	/**
	 * @return website_persistentdocument_page
	 */
	function getCurrentPage()
	{
		return $this->getDocumentInstance($this->currentPageId, "modules_website/page");
	}

	/**
	 * @return array the current page ancestors ids
	 */
	public function getCurrentPageAncestorsIds()
	{
		return $this->currentPageAncestorsIds;
	}

	/**
	 * @return array the current page ancestors
	 */
	public function getCurrentPageAncestors()
	{
		return $this->currentPageAncestors;
	}


	private static $_systemStylesheets = array('backoffice', 'print', 'bindings', 'frontoffice', 'richtext');

	public function getWebsiteAndTopicStylesheets()
	{
		$availablePaths = FileResolver::getInstance()
            ->setPackageName('modules_website')
            ->setDirectory('style')
            ->getPaths('');

        $styles = array();

        foreach ($availablePaths as $availablePath)
        {
            if (is_dir($availablePath))
            {
            	$dh = opendir($availablePath);
                if ($dh)
                {
                    while (($file = readdir($dh)) !== false)
                    {
                    	$fileMatch = array();
                        if (preg_match('/^((?:website|topic)[a-zA-Z0-9_-]+)\.css$/', $file, $fileMatch))
            			{
            			    $fileName = $fileMatch[1];
            			    if (!in_array($fileName, self::$_systemStylesheets))
            			    {
            			    	$styles[$fileName] = f_Locale::translateUI('&modules.website.bo.styles.' . $fileName . ';');
            			    }
            			}
                    }
                    closedir($dh);
                }
            }
        }
		return $styles;
	}
	
	
	
	// Deprecated
	
	/**
	 * @deprecated
	 */
	const EMPTY_URL = '#';

	/**
	 * @deprecated
	 */
	public function getEmptyUrl()
	{
		return self::EMPTY_URL;
	}
	
	/**
	 * @deprecated
	 */
	private $ignoreVisibility = false;
	
	/**
	 * @deprecated
	 */
	public function ignoreVisibility($bool)
	{
		$this->ignoreVisibility = $bool;
		return $this;
	}
	
	/**
	 * @deprecated
	 */
	private $lang;
	
	/**
	 * @deprecated
	 */
	public function setLang($lang)
	{
		$this->lang = $lang;
		return $this;
	}
	
	/**
	 * @deprecated
	 */
	public static function getJsPagePath($pageRef)
	{
		return $this->getBreadcrumb($pageRef)->renderAsJavascript();
	}
	
	
	/**
	 * @deprecated
	 */
	public final function getSitemap($website = null, $maxLevel = 5)
	{
		if (is_null($website))
		{
			$website = $this->getDefaultWebsite();
		}
		$sitemap = new Sitemap();
		$sitemap->setMaxLevel($maxLevel);
		$treeNode = TreeService::getInstance()->getInstanceByDocument($website);
		if ($maxLevel > 0 || $maxLevel == -1)
		{
			TreeService::getInstance()->loadDescendants($treeNode, $maxLevel);
		}
	
		$this->populateSitemapFromDescendants($sitemap, $treeNode, 0, $maxLevel);
		return $sitemap;
	}	
	
	/**
	 * @deprecated
	 */
	public final function getMenuByTag($shortTagName, $maxLevel = -1)
	{
		$menuDoc = $this->getMenuDocumentByTag($shortTagName);
		return $this->getMenu($menuDoc, $maxLevel);
	}
	
	/**
	 * @deprecated
	 */
	public final function getMenusByMenuItemId($id)
	{
		$ancestorsIds = DocumentHelper::getIdArrayFromDocumentArray(TreeService::getInstance()->getInstanceByDocumentId($id)->getAncestors());
		$ancestorsIds[] = $id;
		$menuObjects = array();
		$menuItemDocuments = $this->pp->createQuery('modules_website/menuitemdocument')->add(Restrictions::in('document.id', $ancestorsIds))->find();
		foreach ($menuItemDocuments as $menuItemDocument)
		{
			$menuArray = $menuItemDocument->getMenuArrayInverse();
			$menuObjects[$menuArray[0]->getId()] = $menuArray[0];
		}
		return array_values($menuObjects);
	}
	
	/**
	 * @deprecated
	 */
	public final function getMenuByMenuItemId($id)
	{
		$menuObjects = $this->getMenusByMenuItemId($id);
		if (!empty($menuObjects))
		{
			return $menuObjects[0];
		}
		return null;
	}
	
	/**
	 * @deprecated
	 */
	public final function getRestrictedMenuByTag($shortTagName, $maxLevel = -1, $topEntriesLevel = 1)
	{
		$menuDoc = $this->getMenuDocumentByTag($shortTagName);
		return $this->getRestrictedMenu($menuDoc, $maxLevel, $topEntriesLevel);
	}
		
	/**
	 * @deprecated
	 */
	public function getBreadcrumb($document, $includeCurrentDocument = true)
	{
		$breadcrumb = new Breadcrumb();
	
		$documents = $document->getDocumentService()->getAncestorsOf($document);
		if (count($documents) == 0)
		{
			throw new TopicException('Could not find the tree node for document "'.$document->__toString().'".');
		}
	
		if ($includeCurrentDocument)
		{
			array_push($documents, $document);
		}
	
		$level = 0;
		foreach ($documents as $nodeDocument)
		{
			if (!$nodeDocument->isPublished())
			{
				continue;
			}
	
			if ($document instanceof website_persistentdocument_pageversion &&
				$nodeDocument instanceof website_persistentdocument_pagegroup)
			{
				continue;
			}
	
			// topic are visible in the breadcrumb if they are visible in the menus.
			if ($nodeDocument instanceof website_persistentdocument_topic
				&& ($this->ignoreVisibility || WebsiteHelper::isVisibleInMenu($nodeDocument)))
			{
				$breadcrumb->append($this->buildMenuItemFromDocument($nodeDocument, $level++));
			}
			elseif ($nodeDocument instanceof website_persistentdocument_website)
			{
				$breadcrumb->append($this->buildMenuItemFromDocument($nodeDocument, $level++));
			}
			elseif ($nodeDocument instanceof website_persistentdocument_page && !$nodeDocument->getIsIndexPage())
			{
				$breadcrumb->append($this->buildMenuItemFromDocument($nodeDocument, $level++));
			}
		}
		return $breadcrumb;
	}
	
	/**
	 * @deprecated
	 */
	private function getMenuDocumentByTag($shortTagName)
	{
		$tagName = 'contextual_website_website_' . $shortTagName;
		$menuDoc = TagService::getInstance()->getDocumentByContextualTag($tagName, $this->getCurrentWebsite(), false);
		if ($menuDoc === null)
		{
			throw new TopicException('No menu has the tag "'.$tagName.'".');
		}
		return $menuDoc;
	}
	
	
	/**
	 * @deprecated
	 */
	public function getMenu($menuDocument, $maxLevel = -1)
	{
		$menuObject = new Menu();
		$menuObject->setMaxLevel($maxLevel);
		foreach ($menuDocument->getMenuItemArray() as $menuItemDocument)
		{
			if (WebsiteHelper::isVisibleInMenu($menuItemDocument))
			{
				$count = $menuObject->count();
				$menuItem = $this->buildMenuItemFromDocument($menuItemDocument, 0);
				$menuObject[$count] = $menuItem;
				if ($menuItemDocument instanceof website_persistentdocument_menuitemdocument)
				{
					$item = $menuItemDocument->getDocument();
					if ($item instanceof website_persistentdocument_topic)
					{
						if ($maxLevel > 0 || $maxLevel == -1)
						{
							$node = TreeService::getInstance()->getInstanceByDocument($item);
							TreeService::getInstance()->loadDescendants($node, $maxLevel);
							$subItemsCount = $this->populateNavigationElementFromDescendants($menuObject, $node, 1, $maxLevel);
							if (!$subItemsCount && !$menuItem->hasUrl())
							{
								$menuObject->offsetUnset($count);
							}
						}
					}
				}
			}
		}
		return $menuObject;
	}
	
	/**
	 * @deprecated
	 */
	public function getRestrictedMenu($menuDocument, $maxLevel = -1, $topEntriesLevel = 1)
	{
		$menuObject = new Menu();
		$menuObject->setMaxLevel($maxLevel);
		foreach ($menuDocument->getMenuItemArray() as $menuItemDocument)
		{
			if (WebsiteHelper::isVisibleInMenu($menuItemDocument))
			{
				$menuItem = $this->buildMenuItemFromDocument($menuItemDocument, 0);
				$menuObject->append($menuItem);
				if ($menuItemDocument instanceof website_persistentdocument_menuitemdocument)
				{
					$item = $menuItemDocument->getDocument();
					if ($item instanceof website_persistentdocument_topic)
					{
						if (($maxLevel > 0 || $maxLevel == - 1) && (in_array($item->getId(), $this->getCurrentPageAncestorsIds())))
						{
							$node = TreeService::getInstance()->getInstanceByDocument($item);
							TreeService::getInstance()->loadDescendants($node, $maxLevel);
							$this->populateNavigationElementFromCurrentDescendants($menuObject, $node, 1, $maxLevel);
						}
						else if ($topEntriesLevel > 1)
						{
							$node = TreeService::getInstance()->getInstanceByDocument($item);
							TreeService::getInstance()->loadDescendants($node, $topEntriesLevel);
							$this->populateNavigationElementFromDescendants($menuObject, $node, 1, $topEntriesLevel-1);
						}
					}
				}
			}
		}
	
		return $menuObject;
	}
	
	
	/**
	 * @deprecated
	 */
	public final function getContextMenu($fromDocument = null, $maxLevel = -1)
	{
		return $this->getMenuByTopic($this->getParentNodeFromDocument($fromDocument), $maxLevel);
	}
	
	/**
	 * @deprecated
	 */
	public final function getRestrictedContextMenu($fromDocument = null, $maxLevel = -1)
	{
		return $this->getRestrictedMenuByTopic($this->getParentNodeFromDocument($fromDocument), $maxLevel);
	}
	
	
	/**
	 * @deprecated
	 */
	public final function getRestrictedMenuByTopic($topic, $maxLevel = -1)
	{
		$menuObject = new Menu();
		$menuObject->setMaxLevel($maxLevel);
		$node = TreeService::getInstance()->getInstanceByDocument($topic);
		if ($maxLevel > 0 || $maxLevel == -1)
		{
			TreeService::getInstance()->loadDescendants($node, $maxLevel);
		}
		$this->populateNavigationElementFromCurrentDescendants($menuObject, $node, 0, $maxLevel);
		return $menuObject;
	}
	
	
	/**
	 * @deprecated
	 */
	public final function getMenuByTopic($topic, $maxLevel = -1)
	{
		$menuObject = new Menu();
		$menuObject->setMaxLevel($maxLevel);
		$node = TreeService::getInstance()->getInstanceByDocument($topic);
		if ($maxLevel > 0 || $maxLevel == -1)
		{
			TreeService::getInstance()->loadDescendants($node, $maxLevel);
		}
		$this->populateNavigationElementFromDescendants($menuObject, $node, 0, $maxLevel);
		return $menuObject;
	}

	/**
	 * @deprecated
	 */
	public final function getParentNodeFromDocument($document)
	{
		$document = DocumentHelper::getByCorrection($document);
	
		if ($document instanceof website_persistentdocument_pageversion)
		{
			$parentNode = TreeService::getInstance()->getInstanceByDocument($document)->getParent()->getParent();
		}
		else if ($document === null)
		{
			$parentNode = TreeService::getInstance()->getInstanceByDocument($this->getCurrentWebsite());
		}
		else
		{
			$parentNode = TreeService::getInstance()->getInstanceByDocument($document)->getParent();
		}
		return $parentNode;
	}
		
	/**
	 * @deprecated
	 */
	private function populateNavigationElementFromDescendants(NavigationElementImpl $navigationElement, f_persistentdocument_PersistentTreeNode $parentNode, $level, $maxLevel)
	{
		$count = $navigationElement->count();
		foreach ($parentNode->getChildren() as $child)
		{
			$doc = $child->getPersistentDocument();
			if (WebsiteHelper::isVisibleInMenu($doc))
			{
				$menuItem = $this->buildMenuItemFromDocument($doc, $level);
				$navigationElement[$navigationElement->count()] = $menuItem;
				if (($level < $maxLevel || $maxLevel == -1) && $doc instanceof website_persistentdocument_topic)
				{
					$this->populateNavigationElementFromDescendants($navigationElement, $child, $level+1, $maxLevel);
				}
			}
		}
		return $navigationElement->count() - $count;
	}
	
	
	/**
	 * @deprecated
	 */
	private function populateNavigationElementFromCurrentDescendants(NavigationElementImpl $navigationElement, f_persistentdocument_PersistentTreeNode $parentNode, $level, $maxLevel)
	{
		$count = $navigationElement->count();
		foreach ($parentNode->getChildren() as $child)
		{
			$doc = $child->getPersistentDocument();
			if (WebsiteHelper::isVisibleInMenu($doc))
			{
				$menuItem = $this->buildMenuItemFromDocument($doc, $level);
				$navigationElement[$navigationElement->count()] = $menuItem;
				if (($level < $maxLevel || $maxLevel == -1) && $doc instanceof website_persistentdocument_topic)
				{
					if (in_array($doc->getId(), $this->getCurrentPageAncestorsIds()))
					{
						$this->populateNavigationElementFromCurrentDescendants($navigationElement, $child, $level+1, $maxLevel);
					}
				}
			}
		}
		return $navigationElement->count() - $count;
	}
	
	/**
	 * @deprecated
	 */
	private function populateSitemapFromDescendants(Sitemap $navigationElement, f_persistentdocument_PersistentTreeNode $parentNode, $level, $maxLevel)
	{
		foreach ($parentNode->getChildren() as $child)
		{
			$doc = $child->getPersistentDocument();
			if ($doc instanceof website_persistentdocument_menufolder || !$doc->isPublished())
			{
				continue;
			}
			if (WebsiteHelper::isVisibleInSitemap($doc) )
			{
				$navigationElement->append($this->buildMenuItemFromDocument($doc, $level));
				if (($level < $maxLevel || $maxLevel == -1) && $doc instanceof website_persistentdocument_topic)
				{
					$this->populateSitemapFromDescendants($navigationElement, $child, $level+1, $maxLevel);
				}
			}
		}
	}
		
	/**
	 * @deprecated
	 */
	public static function getNavigationTitleFor($document)
	{
		return $document->getNavigationLabelAsHtml();
	}
	
	/**
	 * @deprecated
	 */
	private function buildMenuItemFromDocument($document, $level = 0)
	{
		$entry = new website_MenuItem();
	
		if ($document instanceof website_persistentdocument_menuitemdocument)
		{
			$entry->setPopup($document->getPopup());
			$entry->setPopupParameters($document->getPopupParameters());
			$document = $document->getDocument();
		}
	
		$this->buildMenuItemUrlFromDocument($entry, $document);
		$this->buildMenuItemLabelFromDocument($entry, $document);
	
		$entry->setId($document->getId());
		$entry->setType(($document instanceof website_persistentdocument_topic) ? website_MenuItem::TYPE_TOPIC : website_MenuItem::TYPE_PAGE);
		$entry->setDocumentModelName($document->getDocumentModelName());
		$entry->setLevel($level);
		if (method_exists($document, 'getDescription'))
		{
			$entry->setDescription($document->getDescription());
		}
		if (method_exists($document, 'getNavigationVisibility'))
		{
			$entry->setNavigationVisibility($document->getNavigationVisibility());
		}
		return $entry;
	}	
	
	/**
	 * @deprecated
	 */
	protected function buildMenuItemLabelFromDocument($entry, $document)
	{
		$label = null;
		if ($document instanceof website_persistentdocument_website)
		{
			$label = f_Locale::translate('&modules.website.frontoffice.thread.Homepage-href-name;');
		}
		else if (!$document instanceof website_persistentdocument_menuitemtext)
		{
			$label = self::getNavigationTitleFor($document);
		}
		else if ($document instanceof website_persistentdocument_menuitemtext)
		{
			$label = $document->getLabelAsHtml();
		}
		$entry->setLabel($label);
	}
	
	/**
	 * @deprecated
	 */
	protected function buildMenuItemUrlFromDocument($entry, $document)
	{
		if (!$document instanceof website_persistentdocument_menuitemtext)
		{
			if ($document instanceof website_persistentdocument_menuitemfunction)
			{
				$this->updateMenuitemFromMenuitemfunction($entry, $document);
			}
			else if ($document instanceof website_persistentdocument_topic)
			{
				if ($document->hasPublishedIndexPage())
				{
					$entry->setUrl(LinkHelper::getDocumentUrl($document));
				}
			}
			else
			{
				$entry->setUrl(LinkHelper::getDocumentUrl($document));
			}
		}
	}
	
	/**
	 * @deprecated
	 */
	private function updateMenuitemFromMenuitemfunction($menuitem, $menuitemfunction)
	{
		// OK, I admit this is not very clean, but... I don't think this will
		// evolve anymore.
		$url = $menuitemfunction->getUrl();
		if (f_util_StringUtils::beginsWith($url, "function:"))
		{
			$menuFunctionClass = 'website_MenuItem'.ucfirst(substr($url, 9))."Function";
			if (f_util_ClassUtils::classExists($menuFunctionClass))
			{
				f_util_ClassUtils::callMethodArgs($menuFunctionClass, "execute", array($menuitem));
				return;
			}
		}
		$menuitem->setUrl($url);
	}
	
	/**
	 * @deprecated (will be removed in 4.0)
	 */
	public final function getCurrentWebsiteAndSetLang()
	{
		return $this->getCurrentWebsite(true);
	}
	
	/**
	 * @deprecated (will be removed in 4.0) Use LinkHelper::getAddToFavoriteLink()
	 */
	public function getAddToFavoriteLink($label = null, $title = null, $class = null)
	{
		return LinkHelper::getAddToFavoriteLink($label, $title, $class);
	}

	/**
	 * @deprecated (will be removed in 4.0) Use LinkHelper::getPrintLink()
	 */
	public function getPrintLink($label = null, $title = null, $class = null)
	{
		return LinkHelper::getPrintLink($label, $title, $class);
	}

	/**
	 * @deprecated (will be removed in 4.0) Use LinkHelper::getHelpLink()
	 */
	public function getHelpLink($label = null, $title = null, $class = null)
	{
		return LinkHelper::getHelpLink($label, $title, $class);
	}

	/**
	 * @deprecated (will be removed in 4.0) Use LinkHelper::getLegalNoticeLink()
	 */
	public function getLegalNoticeLink($label = null, $title = null, $class = null)
	{
		return LinkHelper::getLegalNoticeLink($label, $title, $class);
	}

	/**
	 * @deprecated (will be removed in 4.0) use website_UrlRewritingService::getInstance()
	 */
	public final function getUrlRewritingService()
	{
		return website_UrlRewritingService::getInstance();
	}
}