<?php
class website_PageService extends f_persistentdocument_DocumentService
{

	/**
	 * @var website_PageService
	 */
	private static $instance;

	/**
	 * @return website_PageService
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
	 * @param String $templateName
	 * @return website_persistentdocument_page[]
	 */
	function getByTemplate($templateName)
	{
		return $this->createQuery()->add(Restrictions::eq("template", $templateName))->find();
	}

	/**
	 * @return website_persistentdocument_page
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_website/page');
	}


	/**
	 * Create a query based on 'modules_modules_website/page' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_website/page');
	}


	/**
	 * @param website_persistentdocument_page $page
	 */
	protected function generatePageCache($page)
	{
		$this->synchronizeReferences($page);
	}

	/**
	 * @param website_persistentdocument_page $page
	 */
	private function synchronizeReferences($page)
	{
		if ($page instanceof website_persistentdocument_page)
		{
			$query = $this->pp->createQuery('modules_website/pagereference')->add(Restrictions::eq('referenceofid', $page->getId()));
			$pagesReference = $query->find();

			$copyToVo = $page->getLang() == RequestContext::getInstance()->getLang();

			foreach ($pagesReference as $pageReference)
			{
				$isIndex = $pageReference->getIsIndexPage();
				$page->copyPropertiesTo($pageReference, $copyToVo);
				$pageReference->setIsIndexPage($isIndex);
				$pageReference->save();
			}
		}
	}

	/**
	 * @param website_persistentdocument_page $document
	 * @param String $oldPublicationStatus
	 * @param array $params
	 * @return void
	 */
	protected function publicationStatusChanged($document, $oldPublicationStatus, $params)
	{
		$parentDocument = TreeService::getInstance()->getParentDocument($document);
		if ($parentDocument instanceof website_persistentdocument_topic)
		{
			if ($parentDocument->isPublished() != $document->isPublished())
			{
				website_TopicService::getInstance()->publishDocumentIfPossible($parentDocument, array('childrenPublicationStatusChanged' => $document));
			}
		}
				
		if ("CORRECTION" == $oldPublicationStatus && isset($params["cause"]) && "activate" == $params["cause"])
		{
			$correction = DocumentHelper::getDocumentInstance($params["correctionId"]);
			$oldDom = $this->getDomFromPageContent($correction);
			$newDom = $this->getDomFromPageContent($document);
			$this->doBlockCallbacks($document, $oldDom, $newDom);
		}
		$this->generatePageCache($document);
	}


	/**
	 * @param website_persistentdocument_page $document
	 * @param integer $parentNodeId
	 */
	protected function preInsert($document, $parentNodeId = null)
	{
		if ($document->getNavigationtitle() !== null)
		{
			if ($document->getLabel() === null)
			{
				$document->setLabel($document->getNavigationtitle());
			}
			if ($document->getMetatitle() === null)
			{
				$document->setMetatitle($document->getNavigationtitle());
			}
		}

		if ($document->getContent() === null)
		{
			$this->initContent($document);
		}
		website_WebsiteModuleService::getInstance()->setWebsiteMetaFromParentId($document, $parentNodeId);
	}

	/**
	 * @param website_persistentdocument_page $document
	 * @param integer $parentNodeId
	 */
	protected function preUpdate($document, $parentNodeId = null)
	{
		if ($document->getContent() === null)
		{
			$document->setContent($document->getVoContent());
		}
	}

	/**
	 * @param website_persistentdocument_page $document
	 * @param Integer $parentNodeId
	 */
	protected function preSave($document, $parentNodeId = null)
	{
		$this->buildBlockMetaInfo($document);
	}

	/**
	 * Public for patch 304. Do not call ; private use.
	 * @param website_persistentdocument_page $document
	 */
	function buildBlockMetaInfo($document)
	{
		$lang = RequestContext::getInstance()->getLang();
		$content = $document->getContent();

		if (f_util_StringUtils::isEmpty($content))
		{
			return;
		}

		$contentDOM = new DOMDocument('1.0', 'UTF-8');
		if ($contentDOM->loadXML($content) === false)
		{
			throw new Exception("Unable to load page content");
		}

		$richtextCount = 0;
		$wordCount = 0;
		$blockCount = 0;
		// Process new page content
		$xpath = $this->getXPathInstance($contentDOM);
		foreach (website_TemplateService::getInstance()->getChangeContentIds($document->getTemplate()) as $id)
		{
			$blockNodes = $xpath->query('//change:content[@id="' . $id . '"]//change:block');
			foreach ($blockNodes as $blockNode)
			{
				$type = $blockNode->getAttribute("type");

				if ($type == "richtext")
				{
					$richtextCount++;
					$wordCount += count(explode(' ', f_util_StringUtils::htmlToText($blockNode->textContent, false, true)));
				}
				else
				{
					$blockCount++;
				}
			}
		}

		if ($document->hasMeta('blockInfos'))
		{
			$blockInfosMeta = $document->getMetaMultiple('blockInfos');
		}
		else
		{
			$blockInfosMeta = array();
		}

		if (!isset($blockInfosMeta[$lang]))
		{
			$blockInfosMeta[$lang] = array();
		}
		$blockInfosMeta[$lang]['dynamicBlockCount'] = $blockCount;
		$blockInfosMeta[$lang]['richtextBlockCount'] = $richtextCount;
		$blockInfosMeta[$lang]['wordCount'] = $wordCount;
		
		$document->setMetaMultiple('blockInfos', $blockInfosMeta);
	}
	
	/**
	 * @param website_persistentdocument_page $document
	 */
	public function getBlockMetaInfos($document)
	{
		$metasAvailable = array("title" => array(), "description" => array(), "keywords" => array());
		$lang = RequestContext::getInstance()->getLang();
		$content = $document->getContent();

		if (f_util_StringUtils::isEmpty($content))
		{
			return $metasAvailable;
		}

		$contentDOM = new DOMDocument('1.0', 'UTF-8');
		if ($contentDOM->loadXML($content) === false)
		{
			throw new Exception("Unable to load page content");
		}

		// Process new page content
		$xpath = $this->getXPathInstance($contentDOM);
		foreach (website_TemplateService::getInstance()->getChangeContentIds($document->getTemplate()) as $id)
		{
			$blockNodes = $xpath->query('//change:content[@id="' . $id . '"]//change:block');
			foreach ($blockNodes as $blockNode)
			{
				$type = $blockNode->getAttribute("type");

				if ($type != "richtext")
				{
					$blockInfoArray = $this->buildBlockInfo($type, $this->parseBlockParameters($blockNode), $blockNode->getAttribute('lang'), $blockNode->getAttribute('blockwidth'), $blockNode->getAttribute('editable') != 'false', $blockNode);
					$className = $this->getBlockClassNameForSpecs($blockInfoArray);
					if (!f_util_ClassUtils::classExists($className))
					{
						continue;
					}
					$blockActionClass = new ReflectionClass($className);
					if ($blockActionClass->isSubclassOf('website_BlockAction'))
					{
						$blockAction = $blockActionClass->newInstance();
						if (isset($blockInfoArray['lang']))
						{
							$blockAction->setLang($blockInfoArray['lang']);
						}

						foreach ($blockInfoArray['parameters'] as $name => $value)
						{
							$blockAction->setConfigurationParameter($name, $value);
						}
						$blockConfig = $blockAction->getConfiguration();
						$blockInfo = block_BlockService::getInstance()->getBlockInfo($blockInfoArray["package"]."_".$blockInfoArray["name"]);
						if ($blockInfo === null)
						{
							Framework::warn(__METHOD__ . " This block has no block info. You should declare it in the blocks.xml config file and hide it if you need to.");
						}
						else if ($blockInfo->hasMeta() && $blockConfig->getEnablemetas())
						{
							list($dummy, $moduleName) = explode('_', $blockInfoArray["package"]);
							$metaPrefix = $moduleName."_".$blockInfoArray["name"].".";
							
							$newMetas = array();
							foreach ($blockInfo->getTitleMetas() as $meta)
							{
								$newMetas[] = $metaPrefix . $meta;
							}
							$metasAvailable["title"] = array_merge($metasAvailable["title"], $newMetas);
							
							$newMetas = array();
							foreach ($blockInfo->getDescriptionMetas() as $meta)
							{
								$newMetas[] = $metaPrefix . $meta;
							}
							$metasAvailable["description"] = array_merge($metasAvailable["description"], $newMetas);
							
							$newMetas = array();
							foreach ($blockInfo->getKeywordsMetas() as $meta)
							{
								$newMetas[] = $metaPrefix . $meta;
							}
							$metasAvailable["keywords"] = array_merge($metasAvailable["keywords"], $newMetas);
						}
					}
				}
			}
		}
		
		return $metasAvailable;
	}

	/**
	 * @param website_persistentdocument_page $document
	 * @param Integer $parentNodeId
	 */
	protected function postSave($document, $parentNodeId = null)
	{
		$this->generatePageCache($document);
	}

	/**
	 * Returns TRUE if the given Page is publishable.
	 *
	 * @param website_persistentdocument_page $page
	 * @return boolean
	 */
	public function isPublishable($page)
	{
		return !f_util_StringUtils::isEmpty($page->getContent()) && parent::isPublishable($page);
	}

	
	/**
	 * Returns the full name of the page's template.
	 *
	 * @param website_persistentdocument_page $document
	 * @return string
	 */
	public function getTemplateName($document)
	{
		$ts = website_TemplateService::getInstance();

		if ($ts->isDynamicTemplate($document->getTemplate()))
		{
			$template = $ts->getDynamicTemplate($document->getTemplate());

			return $template->getLabel();
		} else
		{
			$pathWhereToFindDisplays = FileResolver::getInstance()->setPackageName('modules_website')->setDirectory('config')->getPath('display.xml');

			if ($pathWhereToFindDisplays)
			{
				$displayConfig = f_object_XmlObject::getInstanceFromFile($pathWhereToFindDisplays)->getRootElement();

				foreach ($displayConfig->display as $display)
				{
					if ((string)$display['file'] == $document->getTemplate())
					{
						return (string)$display['label'];
					}
				}
			}
		}

		return $document->getTemplate();
	}

	/**
	 * @see f_persistentdocument_DocumentService::getWebsiteId()
	 *
	 * @param website_persistentdocument_page $document
	 * @return integer
	 */
	public function getWebsiteId($document)
	{
		return $document->getMeta("websiteId");
	}


	/**
	 * @param website_persistentdocument_page $page
	 * @return website_persistentdocument_page
	 */
	public function getVersionOf($page)
	{
		if ($page instanceof website_persistentdocument_page)
		{
			return $page;
		}

		throw new Exception('Invalid ancestor Id for pageversion' . $page->getId());
	}

	/**
	 * @param website_persistentdocument_page $page
	 * @param Boolean $isIndexPage
	 * @param Boolean $userSetting
	 */
	public function setIsIndexPage($page, $isIndexPage, $userSetting = false)
	{
		try
		{
			$this->tm->beginTransaction();
			$page->setIsIndexPage($isIndexPage);
			if ($page->isModified())
			{
				$this->pp->updateDocument($page);
				if ($page instanceof website_persistentdocument_pagegroup)
				{
					$versions = $page->getChildrenVersions();
					$pvs = website_PageversionService::getInstance();
					foreach ($versions as $version)
					{
						$pvs->setIsIndexPage($version, $isIndexPage, false);
					}
				}

				$prs = website_PagereferenceService::getInstance();

				$pagesReference = $prs->getPagesReferenceByPage($page);
				if (count($pagesReference) > 0)
				{
					$ts = website_TopicService::getInstance();
					foreach ($pagesReference as $pageReference)
					{
						$topic = $prs->getParentOf($pageReference);
						if ($isIndexPage)
						{
							$ts->setIndexPage($topic, $pageReference, false);
						} else if (DocumentHelper::equals($pageReference, $topic->getIndexPage()))
						{
							$ts->setIndexPage($topic, null, false);
						}
					}
				}
			}
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
		}
	}

	/**
	 * @param website_persistentdocument_page $page
	 * @param Boolean $isHomePage
	 */
	public function setIsHomePage($page, $isHomePage)
	{
		try
		{
			$this->tm->beginTransaction();
			$page->setIsHomePage($isHomePage);
			if ($page->isModified())
			{
				$this->pp->updateDocument($page);
				if ($page instanceof website_persistentdocument_pagegroup)
				{
					$versions = $page->getChildrenVersions();
					$pvs = website_PageversionService::getInstance();
					foreach ($versions as $version)
					{
						$pvs->setIsHomePage($version, $isHomePage);
					}
				}
			}
			$this->tm->commit();
		} catch (Exception $e)
		{
			$this->tm->rollBack($e);
		}
	}

	/**
	 * @param website_persistentdocument_page $document The document to move.
	 * @param integer $destId ID of the destination node.
	 */
	public function onMoveToStart($document, $destId)
	{
		$status = $document->getPublicationstatus();
		if ($status == 'CORRECTION' || $status == 'WORKFLOW')
		{
			throw new BaseException('Unable to move this document in this state', 'modules.website.errors.unable-to-move-document');
		}

		$currentParent = $this->getParentOf($document);
		if ($currentParent !== null && $currentParent->getId() === $destId)
		{
			// If the parent doesn't change there's nothing to do...
			return;
		}

		// Remove index page
		if ($document instanceof website_persistentdocument_page && $document->getIsIndexPage())
		{
			// TODO: document the precise use of the second argument of removeIndexPage ???
			website_WebsiteModuleService::getInstance()->removeIndexPage($document, true);
		}

		$ts = TagService::getInstance();
		foreach ($ts->getTags($document) as $tag)
		{
			if ($ts->isFunctionalTag($tag))
			{
				$ts->removeTag($document, $tag);
			}
		}
	}

	/**
	 * @param website_persistentdocument_page $document
	 * @param Integer $destId
	 */
	protected function onDocumentMoved($document, $destId)
	{
		// update websiteId meta if needed
		$destination = DocumentHelper::getDocumentInstance($destId);
		if ($destination instanceof website_persistentdocument_website)
		{
			$newWebsiteId = $destination->getId();
		}
		else
		{
			$newWebsiteId = $destination->getMeta("websiteId");
		}
		if ($document->getMeta("websiteId") != $newWebsiteId)
		{
			$document->setMeta("websiteId", $newWebsiteId);
			$document->saveMeta();
		}

		// When a page is moved from a topic to another, reindex it.
		$is = indexer_IndexService::getInstance();
		if (!is_null($is))
		{
			$is->update($document);
		}
		// Regenerate the page cache
		$this->generatePageCache($document);
	}

	/**
	 * @param website_persistentdocument_page $page
	 */
	private function createFunctionalPage($page)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . '(' . $page->__toString() . ')');
		}

		if ($page instanceof website_persistentdocument_pagereference)
		{
			$basePage = DocumentHelper::getDocumentInstance($page->getReferenceofid());
		} else
		{
			$basePage = $page;
		}

		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . '(' . $page->__toString() . ') -> original page :' . $basePage->__toString());
		}

		$pageTreeNode = TreeService::getInstance()->getInstanceByDocument($page);
		$parentTreeNode = $pageTreeNode->getParent();

		$query = $this->pp->createQuery('modules_website/topic')->add(Restrictions::descendentOf($parentTreeNode->getId()));
		$topics = $query->find();
		foreach ($topics as $topic)
		{
			$this->setPageReferenceInTopics($topic, $basePage);
		}
	}

	/**
	 * @param website_persistentdocument_topic $topic
	 * @param website_persistentdocument_page $page
	 */
	public function createPageReference($topic, $page)
	{
		if ($page instanceof website_persistentdocument_pagereference)
		{
			$basePage = $this->getDocumentInstance($page->getReferenceofid());
		} else
		{
			$basePage = $page;
		}
		$this->setPageReferenceInTopics($topic, $basePage);
	}

	/**
	 * @param website_persistentdocument_topic $topic
	 * @param website_persistentdocument_page $page
	 */
	private function setPageReferenceInTopics($topic, $page)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . '(' . $topic->__toString() . ', ' . $page->__toString() . ')');
		}

		$query = $this->pp->createQuery('modules_website/pagereference')->add(Restrictions::childOf($topic->getId()))->add(Restrictions::eq('referenceofid', $page->getId()));

		$pageReference = $query->findUnique();
		if (is_null($pageReference))
		{
			$pageReference = website_PagereferenceService::getInstance()->getNewDocumentInstance();
		}
		website_PagereferenceService::getInstance()->updatePageReference($pageReference, $page, $topic->getId());
		$pageReference->save($topic->getId());
	}

	/**
	 * @param website_persistentdocument_page $page
	 */
	private function removeFunctionalPage($page)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . '(' . $page->__toString() . ')');
		}

		$tags = TagService::getInstance()->getTags($page);
		if (count($tags) == 0)
		{
			$pageTreeNode = TreeService::getInstance()->getInstanceByDocument($page);
			if (is_null($pageTreeNode))
			{
				Framework::debug(__METHOD__ . '(' . $page->__toString() . ') -> Canceled not in tree');
				return;
			}

			if ($page instanceof website_persistentdocument_pagereference)
			{
				$pageId = $page->getReferenceofid();
				$deletePage = true;
			} else
			{
				$pageId = $page->getId();
				$deletePage = false;
			}

			if (Framework::isDebugEnabled())
			{
				Framework::debug(__METHOD__ . '(' . $page->__toString() . ') -> original page :' . $pageId);
			}

			$parentTreeNode = $pageTreeNode->getParent();
			$query = $this->pp->createQuery('modules_website/pagereference')->add(Restrictions::eq('referenceofid', $pageId));

			//Tag deplacer d'une page reference on ne prend que les descendants de rubrique
			if ($deletePage)
			{
				if (Framework::isDebugEnabled())
				{
					Framework::debug(__METHOD__ . ' -> Delete descendent only.');
				}
				$query->add(Restrictions::descendentOf($parentTreeNode->getId()));
			}

			$pagesReference = $query->find();
			$pgrefService = website_PagereferenceService::getInstance();

			foreach ($pagesReference as $pageReference)
			{
				$pgrefService->deleteAll($pageReference);
			}

			if ($deletePage)
			{
				$pgrefService->deleteAll($page);
			}
		} else
		{
			if (Framework::isDebugEnabled())
			{
				Framework::debug(__METHOD__ . '(' . $page->__toString() . ') has ' . count($tags) . ' tags.');
			}
		}
	}

	/**
	 * @param website_persistentdocument_page $document
	 * @param String $tag
	 * @return void
	 */
	public function tagAdded($document, $tag)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . '(' . $document->__toString() . ',' . $tag . ')');
		}

		if (TagService::getInstance()->isFunctionalTag($tag))
		{
			$this->createFunctionalPage($document);
		}
	}

	/**
	 * @param website_persistentdocument_page $document
	 * @param String $tag
	 * @return void
	 */
	public function tagRemoved($document, $tag)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . '(' . $document->__toString() . ',' . $tag . ')');
		}
		$tagService = TagService::getInstance();

		if ($tagService->isFunctionalTag($tag))
		{
			$this->removeFunctionalPage($document);
			if (Framework::isDebugEnabled())
			{
				Framework::debug('FUNCTIONAL TAG');
			}

			$pageNode = TreeService::getInstance()->getInstanceByDocument($document);
			if (is_null($pageNode))
			{
				return;
			}

			$ancestors = $pageNode->getAncestors();
			if (Framework::isDebugEnabled())
			{
				Framework::debug('COUNT ANCESTORS : ' . count($ancestors));
			}

			$topic = array_pop($ancestors)->getPersistentDocument();
			if (Framework::isDebugEnabled())
			{
				Framework::debug('TOPIC ' . $topic->__toString());
			}

			if (!$topic instanceof website_persistentdocument_topic)
			{
				return;
			}

			$pageRefs = $this->pp->createQuery('modules_website/pagereference')->add(Restrictions::eq('referenceofid', $document->getId()))->find();


			foreach ($pageRefs as $pageRef)
			{
				if (Framework::isDebugEnabled())
				{
					Framework::debug('REMOVE TAG ON ' . $pageRef->__toString());
				}
				$tagService->removeTag($pageRef, $tag);
			}

			$parentTopic = array_pop($ancestors)->getPersistentDocument();
			if (Framework::isDebugEnabled())
			{
				Framework::debug('PARENTTOPIC ' . $parentTopic->__toString());
			}

			if (!$parentTopic instanceof website_persistentdocument_topic)
			{
				return;
			}

			$query = $this->pp->createQuery('modules_website/page')->add(Restrictions::descendentOf($parentTopic->getId(), 1))->add(Restrictions::hasTag($tag));
			$page = $query->findUnique();

			if (Framework::isDebugEnabled())
			{
				Framework::debug('PAGE ' . $page);
			}

			if (is_null($page))
			{
				return;
			}

			if ($page instanceof website_persistentdocument_pagereference)
			{
				$page = DocumentHelper::getDocumentInstance($page->getReferenceofid());
			}

			$this->setPageReferenceInTopics($topic, $page);

			$query = $this->pp->createQuery('modules_website/topic')->add(Restrictions::descendentOf($topic->getId()));

			$topics = $query->find();
			foreach ($topics as $topic)
			{
				$this->setPageReferenceInTopics($topic, $page);
			}
		}
	}

	/**
	 * @param website_persistentdocument_page $fromDocument
	 * @param website_persistentdocument_page $toDocument
	 * @param String $tag
	 * @return void
	 */
	public function tagMovedFrom($fromDocument, $toDocument, $tag)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . '(' . $fromDocument->__toString() . ',' . $tag . ')');
		}

		if (TagService::getInstance()->isFunctionalTag($tag))
		{
			$this->removeFunctionalPage($fromDocument);
		}
	}

	/**
	 * @param website_persistentdocument_page $fromDocument
	 * @param website_persistentdocument_page $toDocument
	 * @param String $tag
	 * @return void
	 */
	public function tagMovedTo($fromDocument, $toDocument, $tag)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . '(' . $toDocument->__toString() . ',' . $tag . ')');
		}

		if (TagService::getInstance()->isFunctionalTag($tag))
		{
			$this->createFunctionalPage($toDocument);
		}
	}

	/**
	 * @param Integer $pageCount
	 * @return array<website_persistentdocument_page>
	 */
	public function getLastModified($pageCount = 5)
	{
		$query = $this->createQuery()
		->add(Restrictions::ne('model', 'modules_website/pagereference'))
		->add(Restrictions::ne('model', 'modules_website/pagegroup'))
		->add(Restrictions::ne('publicationstatus', 'DEPRECATED'))
		->addOrder(Order::desc('document_modificationdate'))
		->setMaxResults($pageCount);
		$pageModel = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_website/page');
		if ($pageModel->useCorrection())
		{
			$query->add(Restrictions::isNull('correctionofid'));
		}
		return $query->find();
	}

	/**
	 * @param website_persistentdocument_page $newDocument
	 * @param website_persistentdocument_page $originalDocument
	 * @param Integer $parentNodeId
	 */
	protected function preDuplicate($newDocument, $originalDocument, $parentNodeId)
	{
		$newDocument->setIsIndexPage(false);
		$newDocument->setIsHomePage(false);
	}

	/**
	 * @param website_persistentdocument_page $page
	 */
	private function initContent($page)
	{
		$templateService = website_TemplateService::getInstance();
		if ($templateService->isDynamicTemplate($page->getTemplate()))
		{
			$template = $templateService->getDynamicTemplate($page->getTemplate());
			$page->setContent($template->getContent());
			$page->setTemplate($template->getTemplate());
		} else
		{
			$page->setContent('<change:contents xmlns:change="' . self::CHANGE_PAGE_EDITOR_NS . '" />');
		}
	}

	/**
	 * @param website_persistentdocument_page $page
	 */
	public function setDefaultContent($page)
	{
		$this->initContent($page);
		$this->save($page);
	}

	const CHANGE_PAGE_EDITOR_NS = "http://www.rbs.fr/change/1.0/schema";
	const CHANGE_TEMPLATE_TYPE_HTML = "html";
	const CHANGE_TEMPLATE_TYPE_XUL = "xul";

	/**
	 * Extract the page's "full text" , that is the static richtexts
	 * inside the XML's page content.
	 *
	 * @param website_persistentdocument_page $page
	 * @return string
	 */
	public function getFullTextContent($page)
	{
		$result = "";
		$pageContent = $page->getContent();
		if ($pageContent === null) { return $result; }
		
		$contentDOM = new DOMDocument('1.0', 'UTF-8');
		if ($contentDOM->loadXML($pageContent) == false)
		{
			Framework::warn(__METHOD__ . ': page content is not a valid XML. Full text content can not be extracted');
			return $result;
		}
		// Process new page content
		$xpath = $this->getXPathInstance($contentDOM);
		foreach (website_TemplateService::getInstance()->getChangeContentIds($page->getTemplate()) as $id)
		{
			$newRichtextNodes = $xpath->query('//change:content[@id="'. $id .'"]//change:richtextcontent');
			foreach ($newRichtextNodes as $richtTextNode)
			{
				$result .= ' ' . $richtTextNode->nodeValue;
			}
		}
		return f_util_StringUtils::htmlToText($result, false);
	}

	/**
	 * Update the content of the page
	 *
	 * @param website_persistentdocument_page $page
	 * @param string $content
	 */
	public function updatePageContent($page, $content)
	{
		$newContentDOM = new DOMDocument('1.0', 'UTF-8');
		$newContentDOM->loadXML($content);
		$this->cleanRichTextContent($newContentDOM);

		//change:richtextcontent
		$existingContent = $page->getContent();
		if (f_util_StringUtils::isEmpty($existingContent))
		{
			$page->setContent($content);
			return;
		}
		// Load the existing content
		$existingContentDOM = $this->getDomFromPageContent($page);
		$existingContentXPath = $this->getXPathInstance($existingContentDOM);

		$this->doBlockCallbacks($page, $existingContentDOM, $newContentDOM);

		$contentNodes = $newContentDOM->getElementsByTagNameNS(self::CHANGE_PAGE_EDITOR_NS, 'content');
		foreach ($contentNodes as $contentNode)
		{
			$contentId = $contentNode->getAttribute("id");
			$matchingPlaceHolders = $existingContentXPath->query(".//change:content[@id=\"$contentId\"]");
			if ($matchingPlaceHolders->length == 1)
			{
				$placeHolder = $matchingPlaceHolders->item(0);
				$importedNode = $existingContentDOM->importNode($contentNode, true);
				$placeHolder->parentNode->insertBefore($importedNode, $placeHolder);
				$placeHolder->parentNode->removeChild($placeHolder);
			}
			else
			{
				$importedNode = $existingContentDOM->importNode($contentNode, true);
				$existingContentDOM->documentElement->appendChild($importedNode);
			}
		}

		$page->setContent($existingContentDOM->saveXML());
	}

	/**
	 * @param website_persistentdocument_page $page
	 * @return DOMDocument | null
	 */
	private function getDomFromPageContent($page)
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$content = $page->getContent();
		if ($content !== null)
		{
			$doc->loadXML($content);
		}
		return $doc;
	}

	/**
	 * @param website_persistentdocument_page $page
	 * @param DOMDocument $oldPageContentDom
	 * @param DOMDocument $newPageContentDom
	 */
	private function doBlockCallbacks($page, $oldPageContentDom, $newPageContentDom)
	{
		$oldBlocks = $this->getBlocksFromDom($oldPageContentDom);
		$newBlocks = $this->getBlocksFromDom($newPageContentDom);
		foreach ($newBlocks as $type => $newBlock)
		{
			if (!isset($oldBlocks[$type]))
			{
				$newBlock->onPageInsertion($page);
			}
		}
		foreach ($oldBlocks as $type => $oldBlock)
		{
			if (!isset($newBlocks[$type]))
			{
				$oldBlock->onPageRemoval($page);
			}
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return array<String, website_BlockAction>
	 */
	private function getBlocksFromDom($dom)
	{
		$blocks = array();
		if ($dom->documentElement)
		{
			$blockElems = $dom->getElementsByTagNameNS(self::CHANGE_PAGE_EDITOR_NS, 'block');
			foreach ($blockElems as $blockElem)
			{
				$type = $blockElem->getAttribute("type");
				if (!isset($blocks[$type]))
				{
					$blockClassName = $this->getBlockClassNameFromType($type);
					if ($blockClassName !== null)
					{
						$class = new ReflectionClass($blockClassName);
						if ($class->implementsInterface("website_PageBlock"))
						{
							$block = $class->newInstance();
							$blockInfo = $this->buildBlockInfo($type,
								$this->parseBlockParameters($blockElem),
								$blockElem->getAttribute('lang'),
								$blockElem->getAttribute('blockwidth'),
								$blockElem->getAttribute('editable') != 'false',
								$blockElem);
							
							if (isset($blockInfo['lang']))
							{
								$block->setLang($blockInfo['lang']);
							}
			
							foreach ($blockInfo['parameters'] as $name => $value)
							{
								$block->setConfigurationParameter($name, $value);
							}
							
							$blocks[$type] = $block;
						}
					}
				}
			}
		}
		return $blocks;
	}

	private function getBlockClassNameFromType($type)
	{
		$typeInfo = explode("_", $type);
		if (count($typeInfo) == 3)
		{
			$className = $typeInfo[1].'_Block'.ucfirst($typeInfo[2]).'Action';
			if (f_util_ClassUtils::classExists($className))
			{
				return $className;
			}
			else
			{
				Framework::warn(__METHOD__ . " : class [$className] not found");
			}
		}
		return null;
	}

	/**
	 * @param String $textContent
	 * @return String
	 */
	public function getCleanContent($textContent)
	{
		$contentDOM = new DOMDocument('1.0', 'UTF-8');
		$contentDOM->loadXML($textContent);
		$this->cleanRichTextContent($contentDOM);
		return $contentDOM->saveXML();
	}

	/**
	 * @param DOMDocument $domContent
	 */
	private function cleanRichTextContent($domContent)
	{
		$richtextContentNodes = $domContent->getElementsByTagNameNS(self::CHANGE_PAGE_EDITOR_NS, 'richtextcontent');
		foreach ($richtextContentNodes as $contentNode)
		{
			if ($contentNode->childNodes->length == 1)
			{
				$cdata = $contentNode->firstChild;
				$content = $cdata->data;
				$cdata->data = website_XHTMLCleanerHelper::clean($content);
			}
		}
	}

	/**
	 * @param DOMDocument $DOMDocument
	 * @return DOMXPath
	 */
	private function getXPathInstance($DOMDocument)
	{
		$resultXPath = new DOMXPath($DOMDocument);
		$resultXPath->registerNameSpace('change', self::CHANGE_PAGE_EDITOR_NS);
		return $resultXPath;
	}

	/**
	 * @param DOMXPath $templateXPath
	 * @param DOMXPath $contentXPath
	 * @param DOMNode $templateNode
	 */
	private function mergeTemplateAndContent(&$templateXPath, &$contentXPath, &$templateNode)
	{
		$contentNodes = $contentXPath->query('//change:content');
		foreach ($contentNodes as $contentNode)
		{
			$contentId = $contentNode->getAttribute("id");
			$matchingPlaceHolders = $templateXPath->query(".//change:content[@id=\"$contentId\"]", $templateNode);
			if ($matchingPlaceHolders->length == 1)
			{
				$importedNode = $templateXPath->document->importNode($contentNode, true);
				$placeHolder = $matchingPlaceHolders->item(0);
				$placeHolder->parentNode->insertBefore($importedNode, $placeHolder);
				$placeHolder->parentNode->removeChild($placeHolder);
			}
		}
	}

	/**
	 * @param String $type
	 * @param DOMXPath $templateXpath
	 * @return unknown
	 */
	private function getChangeTemplateByContentType($type, &$templateXpath)
	{
		$templates = $templateXpath->query("//change:template[@content-type=\"$type\"]");
		if ($templates->length == 0)
		{
			$templates = $templateXpath->query('//change:template');
		}
		return $templates->item(0);
	}




	public final function getPendingTasksForCurrentUser()
	{
		$pageModel = f_persistentdocument_PersistentDocumentModel::getInstance('website', 'page');
		if (!$pageModel->hasWorkflow())
		{
			return array();
		}
		$query = f_persistentdocument_PersistentProvider::getInstance()->createQuery('modules_task/usertask');
		$query->add(Restrictions::eq('user', users_UserService::getInstance()->getCurrentUser()->getId()));
		$query->add(Restrictions::published());
		$query->add(Restrictions::eq('workitem.transition.taskid', $pageModel->getWorkflowStartTask()));
		$query->addOrder(Order::desc('document_creationdate'));
		$query->setMaxResults(50);
		return $query->find();
	}

	// Orphan pages related methods
	/**
	 * @return website_persistentdocument_page[]
	 */
	public final function getOrphanPages()
	{
		$query = $this->createQuery()
		->add(Restrictions::published())
		->add(Restrictions::eq('isorphan',true))
		->addOrder(Order::desc('document_modificationdate'))
		->setMaxResults(50);
		return $query->find();

	}

	/**
	 * @return website_persistentdocument_page[]
	 */
	public final function getOrphanPagesForWebsiteId($websiteId)
	{
		$query = $this->createQuery()
		->add(Restrictions::published())
		->add(Restrictions::eq('isorphan',true))
		->addOrder(Order::desc('document_modificationdate'))
		->add(Restrictions::descendentOf($websiteId))->setMaxResults(50);
		return $query->find();

	}

	/**
	 * @return Integer
	 */
	public final function getOrphanPagesCount()
	{
		$query = $this->createQuery()
		->add(Restrictions::published())
		->add(Restrictions::eq('isorphan',true))
		->addOrder(Order::desc('document_creationdate'))
		->setProjection(Projections::rowCount('count'));
		$result = $query->find();
		return $result[0]['count'];

	}

	/**
	 * @return Integer
	 */
	public final function getOrphanPagesCountForWebsiteId($websiteId)
	{
		$query = $this->createQuery()
		->add(Restrictions::published())
		->add(Restrictions::eq('isorphan',true))
		->addOrder(Order::desc('document_creationdate'))
		->add(Restrictions::descendentOf($websiteId))
		->setProjection(Projections::rowCount('count'));
		$result = $query->find();
		return $result[0]['count'];

	}

	/**
	 * @return Integer[]
	 */
	public function getTaggedPageIds()
	{
		$query = $this->createQuery()
		->add(Restrictions::published())
		->add(Restrictions::isTagged())
		->add(Restrictions::eq('model', 'modules_website/page'))
		->setProjection(Projections::property('id', 'id'));
		$result = array();
		foreach ($query->find() as $row)
		{
			$result[] = intval($row['id']);
		}
		return $result;
	}

	/**
	 * Add custom log informations
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string $actionName
	 * @param array $info
	 */
	public function addActionLogInfo($document, $actionName, &$info)
	{
		$pageNode = TreeService::getInstance()->getInstanceByDocument($document);
		if ($pageNode === null)
		{
			$info['path'] = '';
			return;
		}
		$path = array();
		foreach ($pageNode->getAncestors() as $node)
		{
			$doc = $node->getPersistentDocument();
			if ($doc instanceof website_persistentdocument_website || $doc instanceof website_persistentdocument_topic)
			{
				$path[] = $doc->getLabel();
			}
		}
		$info['path'] = implode(' / ', $path);
	}

	/**
	 * @param website_persistentdocument_page $page
	 * @return Integer
	 */
	public function getSkinId($page)
	{
		$pageSkin = $page->getSkin();
		if ($pageSkin !== null)
		{
			return $pageSkin->getId();
		}
		$ancestors = array_reverse($this->getAncestorsOf($page));
		foreach ($ancestors as $ancestor)
		{
			if (($ancestor instanceof website_persistentdocument_website) || ($ancestor instanceof website_persistentdocument_topic))
			{
				$skin = $ancestor->getSkin();
				if ($skin !== null)
				{
					return $skin->getId();
				}
			}
		}
		return null;
	}

	/**
	 * @param website_persistentdocument_page $document
	 * @param string $forModuleName
	 * @return array
	 */
	public function getResume($document, $forModuleName)
	{
		$data = parent::getResume($document, $forModuleName);

		if ($document->isContextLangAvailable())
		{
			$lang = RequestContext::getInstance()->getLang();
		}
		else
		{
			$lang = $document->getLang();
		}
		$blockCount = $richtextCount = $wordCount = 0;
		
		if ($document->hasMeta('blockInfos'))
		{
			$blockInfos = $document->getMetaMultiple('blockInfos');
			if (isset($blockInfos[$lang]))
			{
				$blockCount = $blockInfos[$lang]['dynamicBlockCount'];
				$richtextCount = $blockInfos[$lang]['richtextBlockCount'];
				$wordCount = $blockInfos[$lang]['wordCount'];
			}	
		}
		
		$contentData = array(
			'pagecomposition' => f_Locale::translateUI('&modules.website.bo.doceditor.Current-page-composition;', array("blockCount" => $blockCount, "richtextCount" => $richtextCount))
		);
		
		if ($wordCount == 0)
		{
			$contentData['freecontent'] = f_Locale::translateUI('&modules.website.bo.doceditor.Current-word-count-empty;');
		}
		else if ($wordCount == 1)
		{
			$contentData['freecontent'] = f_Locale::translateUI('&modules.website.bo.doceditor.Current-word-count-singular;');
		}
		else
		{
			$contentData['freecontent'] = f_Locale::translateUI('&modules.website.bo.doceditor.Current-word-count;', array('wordCount' => $wordCount));
		}
		$data['content'] = $contentData;
		return $data;
	}

	/**
	 * Returns the content of the page ready to be use by the backoffice editor.
	 *
	 * @param website_persistentdocument_page $page
	 * @return String
	 */
	public function getContentForEdition($page)
	{
		$pageContent = $page->getContent();
		$templateDOM = website_PageRessourceService::getInstance()->getBackpagetemplateAsDOMDocument($page);
		$templateXpath = $this->getXPathInstance($templateDOM);
		$xulTemplate = $this->getChangeTemplateByContentType(self::CHANGE_TEMPLATE_TYPE_XUL, $templateXpath);
		$pageDOM = new DOMDocument('1.0', 'UTF-8');
		$pageDOM->loadXML($pageContent);
		$pageXpath = new DOMXPath($pageDOM);
		$this->mergeTemplateAndContent($templateXpath, $pageXpath, $xulTemplate);
		$xsl = new DOMDocument('1.0', 'UTF-8');
		$xsl->load(FileResolver::getInstance()->setPackageName('modules_website')->setDirectory('lib')->getPath('pageEditContentTransform.xsl'));


		$domTemplate = new DOMDocument('1.0', 'UTF-8');
		$domTemplate->preserveWhiteSpace = false;
		$domTemplate->appendChild($domTemplate->importNode($xulTemplate, true));
		$xulTemplate = null; $templateDOM = null;
		if (!$domTemplate->documentElement->hasAttribute('id'))
		{
			$domTemplate->documentElement->setAttribute('id', $page->getTemplate());
		}
		$xslt = new XSLTProcessor();
		$xslt->importStylesheet($xsl);
		$pageContent = $xslt->transformToDoc($domTemplate);
		$blocks = $this->generateBlocks($pageContent);
		$this->buildBlockContainerForBackOffice($pageContent, $blocks);
		$pageContent->preserveWhiteSpace = false;
		$xulContent = $pageContent->saveXML($pageContent->documentElement);

		$controller = website_BlockController::getInstance();
		$controller->setPage($page);
		$controller->getContext()->setAttribute(website_BlockAction::BLOCK_BO_MODE_ATTRIBUTE, true);
		$this->populateHTMLBlocks($controller, $blocks);

		foreach ($blocks as $blockId => $block)
		{
			$html = $block['html'];
			$tmpDoc = new DOMDocument('1.0', 'UTF-8');
			if ($block['name'] === 'staticrichtext')
			{
				$tmpDoc->loadXML($html);
			}
			else
			{
				if (f_util_StringUtils::isEmpty($html))
				{
					$html = "<strong>" . $this->getBlockLabelFromBlockType($block['type']) . "</strong>";
				}
				else
				{
					$html = f_util_HtmlUtils::cleanHtmlForBackofficeEdition($html);
				}
				$class = str_replace('_', '-', $block['type'] . ' ' . $block['package']);
				$tmpDoc->loadXML('<div xmlns="http://www.w3.org/1999/xhtml" anonid="contentBlock"><div style="'.$this->buildInlineStyle($block['blockwidth']).'"><div class="'.$class.'">' . $html . '</div></div></div>');
			}
			if ($tmpDoc->documentElement)
			{
				$xmlContent = $tmpDoc->saveXML($tmpDoc->documentElement);
			}
			else
			{
				$xmlContent = '<div xmlns="http://www.w3.org/1999/xhtml" anonid="contentBlock"><div style="'.$this->buildInlineStyle($block['blockwidth']).'"><div class="'.$class.'"><strong style="color:red;">' . $this->getBlockLabelFromBlockType($block['type'])  . ' : Invalid XML</strong></div></div></div>';
			}
			$xulContent = str_replace('<htmlblock_'.$blockId.'/>', $xmlContent , $xulContent);
		}
		return $xulContent;
	}
	
	private function getBlockLabelFromBlockType($blockType)
	{
		try
		{
			return f_Locale::translateUI(block_BlockService::getInstance()->getBlockLabelFromBlockName($blockType));
		}
		catch (Exception $e)
		{
			Framework::exception($e);
		}
		return $blockType;
	}

	/**
	 * @param website_persistentdocument_page $page
	 * @param array $blockInfo
	 * @return String
	 */
	public function getBlockContentForEdition($page, $blockInfo)
	{
		$blocks = array(1 => $blockInfo);
		$controller = website_BlockController::getInstance();
		$controller->setPage($page);
		$controller->getContext()->setAttribute(website_BlockAction::BLOCK_BO_MODE_ATTRIBUTE, true);
		$this->populateHTMLBlocks($controller, $blocks);

		$block = $blocks[1];
		$html = $block['html'];
		$tmpDoc = new DOMDocument('1.0', 'UTF-8');
		if ($block['name'] === 'staticrichtext')
		{
			$tmpDoc->loadXML($html);
		}
		else
		{
			if (f_util_StringUtils::isEmpty($html))
			{
				$html = "<strong>" . $this->getBlockLabelFromBlockType($block['type']) . "</strong>";
			}
			else
			{
				$html = f_util_HtmlUtils::cleanHtmlForBackofficeEdition($html);
			}
			$class = str_replace('_', '-', $block['type'] . ' ' . $block['package']);
			$tmpDoc->loadXML('<div xmlns="http://www.w3.org/1999/xhtml" class="'.$class.'">' . $html . '</div>');
		}
		if ($tmpDoc->documentElement)
		{
			return $tmpDoc->saveXML($tmpDoc->documentElement);
		}
		else 
		{
			return '<div xmlns="http://www.w3.org/1999/xhtml" class="'.$class.'"><strong style="color:red;">' . $this->getBlockLabelFromBlockType($block['type']) . ' : Invalid XML</strong></div>';
		}
	}

	private $benchTimes = null;
	
	private function addBenchTime($key)
	{
		if ($this->benchTimes !== null)
		{
			$current = microtime(true);
			if (isset($this->benchTimes[$key]))
			{
				$this->benchTimes[$key] += ($current - $this->benchTimes['c']);
			}
			else
			{
				$this->benchTimes[$key] = ($current - $this->benchTimes['c']);
			}
			$this->benchTimes['c'] = $current;
		}
	}
	
	/**
	 * @param website_persistentdocument_page $page
	 */
	public function render($page)
	{
		if (Framework::inDevelopmentMode() && Framework::isDebugEnabled())
		{
			$current = microtime(true);
			$this->benchTimes = array('renderStart' => $current, 'c' => $current);
		}
		
		$templateDOM = website_PageRessourceService::getInstance()->getPagetemplateAsDOMDocument($page);
		$templateXpath = $this->getXPathInstance($templateDOM);
		$htmlTemplate = $this->getChangeTemplateByContentType(self::CHANGE_TEMPLATE_TYPE_HTML, $templateXpath);

		$contentDOM = new DOMDocument('1.0', 'UTF-8');
		$contentDOM->loadXML($page->getContent());
		$contentXpath = $this->getXPathInstance($contentDOM);
		$this->mergeTemplateAndContent($templateXpath, $contentXpath, $htmlTemplate);

		$domTemplate = new DOMDocument('1.0', 'UTF-8');
		$domTemplate->preserveWhiteSpace = false;
		$domTemplate->appendChild($domTemplate->importNode($htmlTemplate, true));
		$htmlTemplate = null; $templateDOM = null;

		if (!$domTemplate->documentElement->hasAttribute('id'))
		{
			$domTemplate->documentElement->setAttribute('id', $page->getTemplate());
		}
		
		$this->addBenchTime('templateLoading');

		$xsl = new DOMDocument('1.0', 'UTF-8');
		$xsl->load(FileResolver::getInstance()->setPackageName('modules_website')
			->setDirectory('lib')->getPath('pageRenderContentTransform.xsl'));
		$xslt = new XSLTProcessor();
		$xslt->importStylesheet($xsl);
		$pageContent = $xslt->transformToDoc($domTemplate);
		$this->addBenchTime('templateFill');
		
		$blocks = $this->generateBlocks($pageContent);
		$this->addBenchTime('blocksParsing');
		
		$this->buildBlockContainerForFrontOffice($pageContent, $blocks);
		$this->addBenchTime('blocksContainerGenerating');
		$pageContent->preserveWhiteSpace = false;
		$htmlBody = $pageContent->saveXML($pageContent->documentElement);

		$controller = website_BlockController::getInstance();
		$controller->setPage($page);
				
		$pageContext = $controller->getContext();
		$this->addFavIconInfo($pageContext);
		$pageContext->addContainerStylesheet();
			
		$this->addBenchTime('pageContextInitialize');
		$this->populateHTMLBlocks($controller, $blocks);
		$this->addBenchTime('blocksGenerating');
			
		$htmlBody = preg_replace('/<a([^>]+)\/>/i', '<a$1></a>', $htmlBody);
		$htmlBody = preg_replace('/\s*<div([^>]+)\/>\s*/i', '<div$1>&#160;</div>', $htmlBody);

		foreach ($blocks as $blockId => $block)
		{
			$htmlBody = str_replace('<htmlblock_'.$blockId.'/>', $block['html'], $htmlBody);
		}
		$this->addBenchTime('htmlGenerating');
		$pageContext->benchTimes = $this->benchTimes;		
		$pageContext->renderHTMLBody($htmlBody, website_PageRessourceService::getInstance()->getGlobalTemplate());
		
	}
	
	/**
	 * @param website_Page $pageContext
	 */
	private function addFavIconInfo($pageContext)
	{
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		if ($website && $website->getFavicon())
		{
			$favicon = $website->getFavicon();
			if ($favicon->isContextLangAvailable())
			{
				$info = $favicon->getInfo();
				$type = $info['extension'] == 'ico' ? 'image/x-icon' : $favicon->getMimetype(); 
				$url = $favicon->getDocumentService()->generateAbsoluteUrl($favicon, null, array());
			}
			else
			{
				RequestContext::getInstance()->beginI18nWork($favicon->getLang());
				$info = $favicon->getInfo();
				$type = $info['extension'] == 'ico' ? 'image/x-icon' : $favicon->getMimetype(); 
				$url = $favicon->getDocumentService()->generateAbsoluteUrl($favicon, null, array());
				RequestContext::getInstance()->endI18nWork();						
			}
			
			$pageContext->addLink('icon', $type, $url);
			$pageContext->addLink('shortcut icon', $type, $url);
		}		
	}

	/**
	 * Generate the compiled blocks for the given page.
	 * @param DOMDocument $DOMDocument
	 * @return array
	 */
	private function generateBlocks($DOMDocument)
	{
		$result = array();
		$blocks = $DOMDocument->getElementsByTagName('changeblock');
		$blockIndex = 0;
		foreach ($blocks as $block)
		{
			$blockIndex ++;
			if (! $block->hasAttribute('type')) {continue;}
			$type = $block->getAttribute('type');
			$result[$blockIndex] = $this->buildBlockInfo($type,
			$this->parseBlockParameters($block),
			$block->getAttribute('lang'),
			$block->getAttribute('blockwidth'),
			$block->getAttribute('editable') != 'false',
			$block);
		}
		return $result;
	}

	public function buildBlockInfo($type, $parameters = array(), $lang = null, $blockwidth = null, $editable = true, $DomNode = null)
	{
		$blockInfos = array('type' => $type);
		$package = explode('_', $type);
		$packageName = $package[0] . '_' . $package[1];
		if ($lang) {$blockInfos['lang'] = $lang;}
		$blockInfos['package'] = $packageName;
		$blockInfos['name'] = $package[2];
		$blockInfos['editable'] = $editable;
		$blockInfos['blockwidth'] = $blockwidth;
		$blockInfos['parameters'] = $parameters;
		$class = str_replace('_', '-', $type);
		if (isset($blockInfos['parameters']['class']) && $class !== $blockInfos['parameters']['class'])
		{
			$class .= ' ' . $blockInfos['parameters']['class'];
		}
		$blockInfos['class'] = $class . ' ' . str_replace('_', '-', $packageName);
		$blockInfos['DomNode'] = $DomNode;

		return $blockInfos;
	}

	/**
	 * @param DOMElement $block
	 * @return array
	 */
	private function parseBlockParameters($block)
	{
		$parameters = array();
		foreach ($block->attributes as $attrName => $attrNode)
		{
			if (substr($attrName, 0 ,2) === '__')
			{
				$parameters[substr($attrName, 2)] = $attrNode->nodeValue;
			}
		}
		$content = $block->textContent;
		if ($content)
		{
			$parameters['content'] = $content;
			while ($block->childNodes->item(0) !== null)
			{
				$block->removeChild($block->childNodes->item(0));
			}
		}
		if ($block->hasAttributeNS("*", "blockId"))
		{
			$parameters['blockId'] = $block->getAttributeNS("*", "blockId");
		}
		return $parameters;
	}

	/**
	 * @param website_BlockController $controller
	 * @param unknown_type $blocks
	 */
	private function populateHTMLBlocks($controller, &$blocks)
	{
		$blockPriorities = array();
		$bench = $this->benchTimes !== null;
		
		foreach ($blocks as $blockId => $block)
		{
			$className = $this->getBlockClassNameForSpecs($block);
			if (!f_util_ClassUtils::classExists($className))
			{
				$originalClassName = $className;
				$className = 'website_BlockMissingAction';
			}
			$reflectionClass = new ReflectionClass($className);
			if ($reflectionClass->isSubclassOf('website_BlockAction'))
			{
				$classInstance = $reflectionClass->newInstance();
				if (isset($block['lang']))
				{
					$classInstance->setLang($block['lang']);
				}
				if ($className == 'website_BlockMissingAction')
				{
					$classInstance->setOriginalClassName($originalClassName);
				}

				foreach ($block['parameters'] as $name => $value)
				{
					$classInstance->setConfigurationParameter($name, $value);
				}

				$blocks[$blockId]['blockaction'] = $classInstance;
				$blockPriorities[$blockId] = $classInstance->getOrder();
			}
			else
			{
				$classInstance = block_BlockHandler::getNewInstance($blockId);
				$classInstance->setSpecificationsArray($block);
				$classInstance->initialize($controller);
				$blocks[$blockId]['blockaction'] = $classInstance;
				$blockPriorities[$blockId] = $classInstance->getOrder();
			}
		}
		
		asort($blockPriorities);
		$blockPriorities = array_reverse($blockPriorities, true);
		$httpRequest = f_mvc_HTTPRequest::getInstance();
		foreach (array_keys($blockPriorities) as $blockId)
		{
			if ($bench) {$start = microtime(true);}
			$blockData = $blocks[$blockId];
			$blockInstance = $blockData['blockaction'];
			if ($blockInstance instanceof website_BlockAction)
			{
				// Begin capturing. TODO: make a dedicated method instead of write()
				$controller->getResponse()->getWriter()->write("");
				$controller->process($blockInstance, $httpRequest);
				$blocks[$blockId]['html'] = $controller->getResponse()->getWriter()->getContent();
			}
			else
			{
				$blockCache = new block_BlockCache($blockInstance);
				$blockCache->doAction();
				$blocks[$blockId]['html'] = $blockCache->doView();
			}
			
			if ($bench) {$this->benchTimes['b_'.$blockId]['rendering'] = microtime(true) - $start;}
			
			unset($blocks[$blockId]['blockaction']);
		}
	}

	/**
	 * @param DOMDocument $pageContent
	 * @param array $blocks
	 */
	private function buildBlockContainerForFrontOffice($pageContent, &$blocks)
	{
		foreach ($blocks as $blockId => $blockData)
		{
			$node = $blockData['DomNode'];
			$div = $pageContent->createElement('div');
			if (isset($blockData['parameters']['style']))
			{
				$div->setAttribute('style', $blocks[$blockId]['parameters']['style']);
			}
			$div->setAttribute('class', $blockData['class']);
			$div->setAttribute('id', 'b_'. $blockId);
			$div->appendChild($pageContent->createElement('htmlblock_' . $blockId));
			$node->parentNode->replaceChild($div, $node);
			unset($blocks[$blockId]['DomNode']);
		}
	}

	/**
	 * @param DOMDocument $pageContent
	 * @param array $blocks
	 */
	private function buildBlockContainerForBackOffice($pageContent, &$blocks)
	{
		foreach ($blocks as $blockId => $blockData)
		{
			$static = ($blockData['name'] === 'staticrichtext');
			$node = $blockData['DomNode'];

			if (!$blockData['editable'])
			{
				$element = $pageContent->createElement('cfixedblock');
				$element->setAttribute('editable', 'false');
				$element->setAttribute('type', $blockData['type']);
			}
			else if ($static)
			{
				$element = $pageContent->createElement('crichtextblock');
				$element->setAttribute('type', 'richtext');
			}
			else
			{
				$element = $pageContent->createElement('cblock');
				$element->setAttribute('type', $blockData['type']);
			}

			if ($blockData['blockwidth'])
			{
				$element->setAttribute('blockwidth', $blockData['blockwidth']);
			}

			foreach ($blockData['parameters'] as $name => $value)
			{
				if ($static && $name === 'content') {continue;}
				$element->setAttribute('__' . $name, $value);
			}

			$element->appendChild($pageContent->createElement('htmlblock_' . $blockId));
			$node->parentNode->replaceChild($element, $node);

			unset($blocks[$blockId]['DomNode']);
		}
	}


	/**
	 * @param array $specs
	 * @return String
	 */
	private function getBlockClassNameForSpecs($specs)
	{
		return substr($specs['package'], 8) . '_Block'.ucfirst($specs['name']).'Action';
	}

	/**
	 * @param String $widthInPx
	 * @return String
	 */
	private function buildInlineStyle($widthInPx)
	{
		if (f_util_StringUtils::isEmpty($widthInPx))
		{
			return "";
		}
		return "min-width:$widthInPx;max-width:$widthInPx;width:$widthInPx";
	}
}
