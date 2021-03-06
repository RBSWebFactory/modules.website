<?php
/**
 * website_persistentdocument_page
 * @package website
 */
class website_persistentdocument_page extends website_persistentdocument_pagebase implements website_PublishableElement, indexer_IndexableDocument
{
	/**
	 * transient property, setted by PageService on insert success
	 * @var website_persistentdocument_topic
	 */
	private $topic;

	/**
	 * Get the indexable document
	 *
	 * @return indexer_IndexedDocument
	 */
	public function getIndexedDocument()
	{
		if ($this->getIndexingstatus() == false)
		{
			return null;
		}
		$indexedDoc = new indexer_IndexedDocument();
		$indexedDoc->setId($this->getId());
		$indexedDoc->setDocumentModel('modules_website/page');
		$indexedDoc->setLabel($this->getNavigationLabel());
		$indexedDoc->setLang(RequestContext::getInstance()->getLang());
		$indexedDoc->setText($this->getTextContent());

		$indexedDoc->setDocumentAccessors($this->getFrontendAccessorIds());
		$parentTopic = $this->getParentTopic();
		if (!is_null($parentTopic))
		{
			$indexedDoc->setIntegerField('parentTopicId', $parentTopic->getId());
		}
		return $indexedDoc;
	}

	/**
	 * @return website_persistentdocument_topic
	 */
	function getTopic()
	{
		if ($this->topic !== null)
		{
			return $this->topic;
		}
		$parent = website_PageService::getInstance()->getParentOf($this);
		if ($parent instanceof website_persistentdocument_topic)
		{
			return $parent;
		}
		return null;
	}

	/**
	 * transient property, setted by PageService on insert success
	 * @param website_persistentdocument_topic $topic
	 */
	function setTopic($topic)
	{
		$this->topic = $topic;
	}
	
	
	function setDefaultContent($contentName)
	{
	   $parts = explode('::', $contentName);
	   $this->setTemplate($parts[0]);
	   if (count($parts) == 2)
	   {
	   		$template = DocumentHelper::getDocumentInstance($parts[1], 'modules_website/template');
	   		$this->setContent($template->getContent());
	   }	
	}

	// private methods

	/**
	 * @return String
	 */
	private function getTextContent()
	{
		return website_PageService::getInstance()->getFullTextContent($this);
	}

	/**
	 * @return website_persistentdocument_topic
	 */
	private function getParentTopic()
	{
		$parent = website_PageService::getInstance()->getParentOf($this);
		if ($parent instanceof website_persistentdocument_topic)
		{
			return $parent;
		}
		return null;
	}
	
	/**
	 * @see website_persistentdocument_pagebase::getBackofficeIndexedDocument()
	 *
	 * @return indexer_IndexedDocument
	 */
	public function getBackofficeIndexedDocument()
	{
		return parent::getBackofficeIndexedDocument();
	}

	/**
	 * @return Integer[]
	 */
	private function getFrontendAccessorIds()
	{
		$ps = f_permission_PermissionService::getInstance();
		$users = $ps->getAccessorIdsForRoleByDocumentId('modules_website.AuthenticatedFrontUser', $this->getId());
		if (count($users) == 0)
		{
			$users[] = indexer_IndexService::PUBLIC_DOCUMENT_ACCESSOR_ID;
		}
		return $users;
	}
	
	/**
	 * @var String
	 */
	private $templateUserAgent = "all.all";
	
	/**
	 * Transient property (which template did I match ?)
	 *
	 * @param String $fullUserAgent
	 */
	public final function setTemplateUserAgent($fullUserAgent)
	{
		$this->checkLoaded();
		$this->templateUserAgent = $fullUserAgent;
	}
	
	/**
	 * @return String
	 */
	public final function getTemplateUserAgent()
	{
		$this->checkLoaded();
		return $this->templateUserAgent;
	}
	
	/**
	 * @return Integer
	 */
	public final function getSkinId()
	{
		return $this->getDocumentService()->getSkinId($this);
	}
		
	/**
	 * @var string
	 */
	private $fromlang = null;
		
	/**
	 * @param string $fromlang
	 */
	public function setFromlang($fromlang)
	{
		$this->fromlang = $fromlang;
	}
	
	/**
	 * @return string
	 */
	public function getFromlang()
	{
		return $this->fromlang;
	}
	
	//DEPRECATED
	
	/**
	 * @deprecated
	 */
	public function getNavigationURL()
	{
		return LinkHelper::getDocumentUrl($this);
	}
}