<?php
class website_PageversionService extends website_PageService
{
	/**
	 * @var website_PageversionService
	 */
	private static $instance;

	/**
	 * @return website_PageversionService
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
	 * @return website_persistentdocument_pageversion
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_website/pageversion');
	}

	/**
	 * Create a query based on 'modules_modules_website/pageversion' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_website/pageversion');
	}

	/**
	 * @param website_persistentdocument_pageversion $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function preInsert($document, $parentNodeId = null)
	{
		$page = $this->getDocumentInstance($parentNodeId);
		if (!($page instanceof website_persistentdocument_pagegroup))
		{
			throw new Exception('Invalide parent type '. $page->getDocumentModelName() .' for pageversion');
		}
		$document->setVersionofid($parentNodeId);
		website_WebsiteModuleService::getInstance()->setWebsiteMetaFromParentId($document, $parentNodeId);
	}


	/**
	 * @param website_persistentdocument_pageversion $document
	 * @param Integer $parentNodeId
	 */
	protected function postSave($document, $parentNodeId = null)
	{
		parent::postSave($document, $parentNodeId);
		if ($document instanceof website_persistentdocument_pageversion)
		{
			$pagegroup = $this->getPageGroupByPageVersion($document);
			website_PagegroupService::getInstance()->setCurrentVersion($pagegroup);
		}
	}

	/**
	 * @param website_persistentdocument_pageversion $document
	 * @return void
	 */
	protected function postDelete($document)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . ' $document :' . $document->__toString());
		}	
		parent::postDelete($document);
		
		if ($document->getPersistentModel()->useCorrection() && $document->getCorrectionofid())
		{
			if (Framework::isDebugEnabled())
			{
				Framework::debug(__METHOD__ . ' IS CORRECTION, IGNORED');
			}
			return;
		}
		$pagegrpsrv = website_PagegroupService::getInstance();	
		$pagegroup = $this->getPageGroupByPageVersion($document);
		
		$versions = $pagegroup->getChildrenVersions();
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . ' version count :' . count($versions));
		}
		switch (count($versions))
		{
			case 0:
				$pagegrpsrv->removeCurrentVersion($pagegroup);
				break;
			case 1:
				$pagegrpsrv->setCurrentVersion($pagegroup);
				$this->delete($versions[0]);
				break;
			default:
				$pagegrpsrv->setCurrentVersion($pagegroup);
				break;
		}

	}
	
	/**
	 * @param website_persistentdocument_pageversion $document
	 * @return website_persistentdocument_pagegroup
	 */
	private function getPageGroupByPageVersion($document)
	{
		return $this->getDocumentInstance($document->getVersionofid(), 'modules_website/pagegroup');
	}

	/**
	 * @param website_persistentdocument_pageversion $document
	 * @param String $oldPublicationStatus
	 * @param array $params
	 * @return void
	 */
	protected function publicationStatusChanged($document, $oldPublicationStatus, $params)
	{
		parent::publicationStatusChanged($document, $oldPublicationStatus, $params);
		if ($document instanceof website_persistentdocument_pageversion)
		{
			$pagegroup = $this->getPageGroupByPageVersion($document);
			website_PagegroupService::getInstance()->setCurrentVersion($pagegroup);
		}
	}


	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @return f_persistentdocument_PersistentDocument or null if no parent exists
	 */
	public function getParentOf($document)
	{
		return parent::getParentOf($this->getVersionOf($document));
	}

	/**
	 * @param website_persistentdocument_pageversion $pageversion
	 * @return website_persistentdocument_page
	 */
	public function getVersionOf($pageversion)
	{
		if ($pageversion instanceof website_persistentdocument_pageversion)
		{
			return $this->getDocumentInstance($pageversion->getVersionofid());
		}
		return parent::getVersionOf($pageversion);
	}

	/**
	 * @param website_persistentdocument_page $originalPage
	 * @param website_persistentdocument_pageversion $duplicatedPage
	 */
	public function duplicatePageContent($originalPage, $duplicatedPage)
	{
		$duplicatedPage->setTemplate($originalPage->getTemplate());
        $duplicatedPage->setContent($originalPage->getContent());
	}


	/**
	 * @param website_persistentdocument_pageversion $version
	 * @param Integer $parentId
	 * @return website_persistentdocument_page versionOf
	 */
	public function addNewVersion($version, $parentId)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ .'('. $version->__toString().", $parentId)");
		}
		$parent = DocumentHelper::getByCorrectionId($parentId);
		$page = $this->getVersionOf($parent);
		if (!($page instanceof website_persistentdocument_pagegroup)) 
		{
			$page = $this->createFirstVersion($page);
		}
		$version->setPublicationstatus($version->getPersistentModel()->getDefaultNewInstanceStatus());
		$version->save($page->getId());
		return $page;
	}

	/**
	 * @param website_persistentdocument_page $page
	 * @return website_persistentdocument_pagegroup
	 */
	private function createFirstVersion($page)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ .'('. $page->__toString().')');
		}
		try
		{
			$this->tm->beginTransaction();
			$pagegroup = $this->transform($page, 'modules_website/pagegroup');
		
			$version = $this->getNewDocumentInstance();
			$rc = RequestContext::getInstance();
			$useCorrection = $pagegroup->getPersistentModel()->useCorrection();
			$correctionIds = array();
			foreach ($rc->getSupportedLanguages() as $lang)
			{
				try
				{
					$rc->beginI18nWork($lang);
					if ($pagegroup->isLangAvailable($lang))
					{
						$pagegroup->copyPropertiesTo($version, $lang == $pagegroup->getLang());
						if ($useCorrection)
						{
						    $correctionId = $pagegroup->getCorrectionid();
						    if ($correctionId > 0)
						    {
						        $correctionIds[] = $correctionId;
						        $pagegroup->setCorrectionid(null);
						    }
						}
					}
					$rc->endI18nWork();
				}
				catch (Exception $e)
				{
					$rc->endI18nWork($e);
				}
			}
			
			$version->setVersionofid($pagegroup->getId());

			$ts = TreeService::getInstance();
            $pageNode = $ts->getInstanceByDocument($pagegroup);
			$this->pp->insertDocument($version);
			$ts->newLastChildForNode($pageNode, $version->getId());
						
            if (count($correctionIds) > 0)
            {
                $this->pp->updateDocument($pagegroup);
                $this->mutateCorrections($version, $correctionIds);			
            }
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
		}
		
		f_event_EventManager::dispatchEvent('persistentDocumentCreated', $this, array("document" => $version));
		
		return $pagegroup;
	}
	
	/**
	 * @param website_persistentdocument_pageversion $version
	 * @param array<Integer> $correctionIds
	 */
	private function mutateCorrections($version, $correctionIds)
	{
	    $rc = RequestContext::getInstance();
	    
        foreach ($correctionIds as $correctionId) 
        {        
            if (Framework::isDebugEnabled())
            {
                Framework::debug(__METHOD__ . '(' . $version->__toString() . ', '. $correctionId);
            }
            
        	$correction = DocumentHelper::getDocumentInstance($correctionId);
        	try 
        	{
         	    $rc->beginI18nWork($correction->getLang());
        	    $mutatedCorrection = $this->transform($correction, $version->getDocumentModelName());
        	    
        	    $mutatedCorrection->setCorrectionofid($version->getId());
        	    $mutatedCorrection->setVersionofid($version->getVersionofid());
        	    $this->pp->updateDocument($mutatedCorrection);
        	    
        	    $rc->endI18nWork();
        	}
        	catch (Exception $e)
        	{
        	    $rc->endI18nWork($e);
        	}
        }
	    $this->pp->updateDocument($version);
	}

	/**
	 * @param website_persistentdocument_pageversion $newDocument
	 * @param website_persistentdocument_pageversion $originalDocument
	 * @param Integer $parentNodeId
	 */
	protected function preDuplicate($newDocument, $originalDocument, $parentNodeId)
	{
		throw new IllegalOperationException('This document cannot be duplicated.');
	}

	/**
	 * @param website_persistentdocument_pageversion $document
	 * @param string $moduleName
	 * @param string $treeType
	 * @param array<string, string> $nodeAttributes
	 */
	public function addTreeAttributes($document, $moduleName, $treeType, &$nodeAttributes)
	{
		parent::addTreeAttributes($document, $moduleName, $treeType, $nodeAttributes);
		$nodeAttributes['inGroup'] = 'inGroup';
		$versionOfPage = DocumentHelper::getDocumentInstance($document->getVersionofid());
		if ($document->getId() != $versionOfPage->getCurrentversionid())
		{
			$nodeAttributes['pu'] = 0;
		}
	}
}