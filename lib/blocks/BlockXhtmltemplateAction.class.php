<?php
/**
 * website_BlockXhtmltemplateAction
 * @package modules.website.lib.blocks
 */
class website_BlockXhtmltemplateAction extends website_BlockAction
{
	
	/**
	 * @see f_mvc_Action::getCacheDependencies()
	 *
	 * @return array
	 */
	public function getCacheDependencies()
	{
		if ($this->getConfiguration()->getUsecache() == false)
		{
			return null;
		}
		return array('modules_website/page');
	}
	
	/**
	 * @see website_BlockAction::getCacheKeyParameters()
	 *
	 * @param website_BlockActionRequest $request
	 * @return unknown
	 */
	public function getCacheKeyParameters($request)
	{
		return array('pageId' => $this->getPage()->getId(), 'package' => $this->getConfiguration()->getPackage(), 'template' => $this->getConfiguration()->getTemplate());
	}

	/**
	 * @see website_BlockAction::execute()
	 *
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function execute($request, $response)
	{
		$templateName = $this->getConfiguration()->getTemplate();
		if (f_util_StringUtils::isEmpty($templateName))
		{
			return website_BlockView::NONE;
		}
		$request->setAttribute('page', $this->getPage());
		if ($this->isInBackoffice())
		{
			return ucfirst($templateName . 'Dummy');
		}
		return ucfirst($templateName);
	}
}