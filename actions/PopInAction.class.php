<?php
class website_PopInAction extends f_action_BaseAction
{
	protected function getDocumentIdArrayFromRequest($request)
	{
		$pageIds = array();
		// Page ID could come in (too) many flavours :
		if ($request->hasParameter('pageref'))
		{
			$pageIds[] = $request->getParameter('pageref');
		}
		else if ($request->hasModuleParameter('website', 'id'))
		{
			$pageIds[] = $request->getModuleParameter('website', 'id');
		}
		else if ($request->hasModuleParameter('website', 'cmpref'))
		{
			$pageIds[] = $request->getModuleParameter('website', 'cmpref');
		}
		else
		{
			$pageIds = parent::getDocumentIdArrayFromRequest($request);
		}
		return $pageIds;
	}
	
	/**
	 * S'assurer d'inclure CSS -> modules.website.jquery-ui.south-street
	 *  et JS -> modules.website.lib.js.jquery-ui-dialog
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{	
		controller_ChangeController::setNoCache();
		$this->setContentType('text/html');
		$pageId = $this->getDocumentIdFromRequest($request);
		
		$fromURL = $request->getParameter('fromURL', (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null);
		RequestContext::getInstance()->setAjaxMode(true, $fromURL);
		
		try
		{
			$page = website_persistentdocument_page::getInstanceById($pageId);		
			if (!$page->isPublished())
			{
				throw new PageException($pageId, PageException::PAGE_NOT_AVAILABLE);
			}
			website_WebsiteModuleService::getInstance()->setCurrentPageId($page->getId());
			$wprs = website_PageRessourceService::getInstance();
			$wprs->setGlobalTemplateName('PopIn-ContentBasis');
			$wprs->setUseMarkers(false);
			ob_start();
			website_PageService::getInstance()->render($page);
			$result = ob_get_clean();
			
			echo str_replace(array('<body ', '</body>'), array('<div ', '</div>'), $result);
			return View::NONE;
		}
		catch (PageException $e)
		{
			Framework::exception($e);
		}
		return View::NONE;
	}

	/**
	 * @return boolean Always false.
	 */
	public function isSecure()
	{
		return false;
	}
	
	/**
	 * Traitement absence de permission
	 *
	 * @param String $login
	 * @param String $permission
	 * @param Integer $nodeId
	 */
	protected function onMissingPermission($login, $permission, $nodeId)
	{
		return View::NONE;
	}
	
	/**
	 * @return Boolean
	 */
	protected function isDocumentAction()
	{
		return false;
	}
	
	public function getRequestMethods()
	{
		return Request::GET | Request::POST;
	}
}