<?php
class website_WebsiteScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return f_persistentdocument_PersistentDocument
	 */
	protected function initPersistentDocument()
	{
		// Deprecated attribute tagname. Use byTag generic attribute.
		if (isset($this->attributes['tagname']))
		{
			$documents = TagService::getInstance()->getDocumentsByTag($this->attributes['tagname']);
			if (isset($documents[0]))
			{
				return $documents[0];
			}
		}
		if (isset($this->attributes['documentid']))
		{
			return DocumentHelper::getDocumentInstance($this->attributes['documentid']);
		}
		return website_WebsiteService::getInstance()->getNewDocumentInstance();
	}
	
	protected function getDocumentProperties()
	{
		$properties = parent::getDocumentProperties();
		if (isset($properties['tagname']))
		{
			unset($properties['tagname']);
		}
		if (isset($properties['template']))
		{
			unset($properties['template']);
		}
		
		if (isset($properties['documentid']))
		{
			unset($properties['documentid']);
		}
		
		if (!isset($properties['url']))
		{
			$properties['url'] = Framework::getUIBaseUrl();
		}
		if (!isset($properties['domain']))
		{
			$properties['domain'] = Framework::getUIDefaultHost();
		}
		return $properties;
	}
	
	public function endProcess()
	{
		$document = $this->getPersistentDocument();		
		foreach ($this->script->getChildren($this) as $child)
		{
			if ($child instanceof users_PermissionsScriptDocumentElement)
			{
				$child->setPermissions($document);
			}
		}
	}
}