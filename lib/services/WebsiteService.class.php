<?php
class website_WebsiteService extends f_persistentdocument_DocumentService
{
	/**
	 * @var website_WebsiteService
	 */
	private static $instance;

	/**
	 * @return website_WebsiteService
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
	 * @return website_persistentdocument_website
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_website/website');
	}

	/**
	 * Create a query based on 'modules_website/website' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_website/website');
	}
	
	/**
	 * @param website_persistentdocument_website $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal).
	 * @return void
	 */
    protected function preSave($document, $parentNodeId = null)
    {       
        $protocol = $document->getProtocol() . '://';
        
        if ($document->getLocalizebypath() && !$document->isNew())
        {
            $rq = RequestContext::getInstance(); 
            $domaine = $document->getVoDomain();
            
            if ($document->getLang() != $rq->getLang())
            {
                $document->setDomain($domaine);
                $document->setUrl($protocol.$domaine);
            }
            else
            {
                foreach ($rq->getSupportedLanguages() as $supportedLanguage) 
                {
                	if ($supportedLanguage != $document->getLang() && $document->isLangAvailable($supportedLanguage))
                	{
                	     $rq->beginI18nWork($supportedLanguage); 
                         $document->setDomain($domaine);
                         $document->setUrl($protocol.$domaine);
                	     $rq->endI18nWork();
                	}
                } 
            }
        }
        else
        {
            $document->setUrl($protocol.$document->getDomain());
        }
    }
	
	
	/**
	 * @param website_persistentdocument_website $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal).
	 * @return void
	 */
	protected function preInsert($document, $parentNodeId = null)
	{
		$rootFolderId = ModuleService::getInstance()->getRootFolderId('website');
		if (!is_null($parentNodeId) && $parentNodeId != $rootFolderId)
		{
			throw new Exception('Cannot insert a website into another website.');
		}
	}


	/**
	 * @param website_persistentdocument_website $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal).
	 * @return void
	 */
	protected function postInsert($document, $parentNodeId = null)
	{
		$query = $this->createQuery()->add(Restrictions::hasTag(WebsiteConstants::TAG_DEFAULT_WEBSITE));

		// If we are creating the first website document, set it as the default
		// website.
		if (is_null($query->findUnique()) )
		{
			website_WebsiteModuleService::getInstance()->setDefaultWebsite($document);
		}

		// Create the menus folder where the website's menus will be stored.
		$menuFolder = website_MenufolderService::getInstance()->getNewDocumentInstance();
		$menuFolder->setLabel('&modules.website.bo.general.Menu-folder-label;');
		$menuFolder->save($document->getId());

		// Create the markers folder where the website's markers will be stored.
		$markerFolder = website_MarkerfolderService::getInstance()->getNewDocumentInstance();
		$markerFolder->setLabel('&modules.website.bo.general.Marker-folder-label;');
		$markerFolder->save($document->getId());
	}

	/**
	 * Handle Website deletion: deletes the folder that holds the menus.
	 *
	 * @param website_persistentdocument_website $document
	 */
	protected function preDelete($document)
	{
		$query = $this->pp->createQuery('modules_website/menufolder')->add(Restrictions::childOf($document->getId()));
		$menuFolder = $query->findUnique();
		$menuFolder->delete();
	}


	/**
	 * @param website_persistentdocument_website $website
	 * @return website_persistentdocument_menufolder
	 */
	public function getMenuFolder($website)
	{
		$nodeArray = TreeService::getInstance()->getInstanceByDocument($website)->getChildren('modules_website/menufolder');
		if (count($nodeArray) == 1)
		{
			return $nodeArray[0]->getPersistentDocument();
		}
		return null;
	}
	
	/**
	 * @param website_persistentdocument_website $website
	 * @param website_persistentdocument_page $newHomePage
	 */
	public function setHomePage($website, $newHomePage)
	{
	    if (!$website instanceof website_persistentdocument_website) 
	    {
	    	throw new IllegalArgumentException('website', 'website_persistentdocument_website');
	    }  
		try
		{
		    $this->tm->beginTransaction();
		    
            $oldPage = $website->getIndexPage();
      
            if ($oldPage !== null)
            {
               $oldPage->getDocumentService()->setIsHomePage($oldPage, false);
            }
            if ($newHomePage !== null)
            {
               $newHomePage->getDocumentService()->setIsHomePage($newHomePage, true);
            }
            
            $website->setIndexPage($newHomePage);
            $requestContext = RequestContext::getInstance();
			try 
            {
               	$requestContext->beginI18nWork($website->getLang());
               	$website->save();
               	$requestContext->endI18nWork();
            }
            catch (Exception $e)
            {
              	$requestContext->endI18nWork($e);
            }
            $this->tm->commit();
	    }
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
		}          
	}
	
	/**
	 * @return Array<website_persistentdocument_website>
	 */
	public function getAll()
	{
		return $this->createQuery()->find();
	}
	
	/**
	 * @param Integer $descendentId
	 * @return website_persistentdocument_website
	 */
	public function getByDescendentId($descendentId)
	{
		return $this->createQuery()->add(Restrictions::ancestorOf($descendentId))->findUnique();
	}
	
	/**
	 * @param website_persistentdocument_website $document
	 * @param string $forModuleName
	 * @return array
	 */
	public function getResume($document, $forModuleName)
	{
		$data = parent::getResume($document, $forModuleName);
		$rc = RequestContext::getInstance();
		$contextlang = $rc->getLang();
		$usecontextlang = $document->isLangAvailable($contextlang);
		$lang = $usecontextlang ? $contextlang : $document->getLang();
			
		try 
		{
			$rc->beginI18nWork($lang);
			if ($document->getLocalizebypath())
			{
				$data['urlrewriting']['currenturl'] = $document->getUrl() . '/' . $lang . '/'; 
			}
			else
			{
				$data['urlrewriting']['currenturl'] = $document->getUrl(). '/';
			}			
			$rc->endI18nWork();
		}
		catch (Exception $e)
		{
			$rc->endI18nWork($e);
		}			
		return $data;
	}
}