<?php
/**
 * Should be in website module
 */
class commands_CompileBlocks extends c_ChangescriptCommand
{
	/**
	 * @return string
	 */
	function getUsage()
	{
		return "";
	}

	function getAlias()
	{
		return "cb";
	}

	/**
	 * @return string
	 */
	function getDescription()
	{
		return "compile blocks";
	}

	/**
	 * @see c_ChangescriptCommand::getEvents()
	 */
	public function getEvents()
	{
		return array(
			array('target' => 'compile-all'),
		);
	}
	
	/**
	 * @param integer $completeParamCount the parameters that are already complete in the command line
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @return string[] or null
	 */
	function getParameters($completeParamCount, $params, $options, $current)
	{
		return null;
	}

	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Compile blocks ==");
		$this->loadFramework();
		$bs = block_BlockService::getInstance();

		$bs->compileBlocks(array($this, 'showCurrentModule'));
		if ($this->hasError())
		{
			return $this->quitError("All blocks could not be compiled: ".$this->errorCount." errors");
		}
		$this->executeCommand("clear-webapp-cache");
		return $this->quitOk("All blocks compiled successfully.");
	}

	/**
	 * @param string $moduleName
	 * @param Exception $exception
	 */
	function showCurrentModule($moduleName, $exception = null)
	{
		if ($exception !== null)
		{
			$this->errorMessage("$moduleName: failure\n".$exception->getMessage());
			$this->debugMessage($exception->getTraceAsString());
		}
		else
		{
			$this->message("$moduleName: success.");
		}
	}
}