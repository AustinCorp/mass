<?php
# Copyright (c) 2012, Kevin Sandom under the BSD License. See LICENSE for full details.

# Manipulate output

class Manipulator extends Module
{
	private $dataDir=null;
	
	function __construct()
	{
		parent::__construct('Manipulator');
	}
	
	function event($event)
	{
		switch ($event)
		{
			case 'init':
				$this->core->registerFeature($this, array('toString'), 'toString', 'Convert array of arrays into an array of strings. eg --toString="blah file=%hostName% ip=%externalIP%"');
				$this->core->registerFeature($this, array('f', 'flatten'), 'flatten', 'Flatten an array or arrays into a keyed array of values. --flatten[=limit]. Note that "limit" specifies how far to go into the nesting before simply returning what ever is below.');
				$this->core->registerFeature($this, array('requireEach'), 'requireEach', 'Require each entry to match this regular expression. --requireEach=regex');
				$this->core->registerFeature($this, array('requireEntry'), 'requireEntry', 'Require a named entry in each of the root entries. A regular expression can be supplied to provide a more precise match. --requireEntry=entryKey[,regex]');
				break;
			case 'followup':
				break;
			case 'last':
				break;
			case 'requireEach':
				return $this->requireEach($this->core->getSharedMemory(), $this->core->get('Global', 'requireEach'));
				break;
			case 'requireEntry':
				return $this->requireEntry($this->core->getSharedMemory(), $this->core->get('Global', 'requireEntry'));
				break;
			case 'toString':
				return $this->toString($this->core->getSharedMemory(), $this->core->get('Global', 'toString'));
				break;
			case 'flatten':
				return $this->flatten($this->core->getSharedMemory(), $this->core->get('Global', 'flatten'));
				break;
			default:
				$this->core->complain($this, 'Unknown event', $event);
				break;
		}
	}
	
	function replace($input, $search, $replace)
	{
		return implode($replace, explode($search, $input));
	}
	
	function toString($input, $template)
	{
		$output=array();
		
		foreach ($input as $line)
		{
			if (is_array($line))
			{
				$outputLine=$template;
				foreach ($line as $key=>$value)
				{
					$outputLine=$this->replace($outputLine, "%$key%", $value);
				}
				$output[]=$outputLine;
			}
			else
			{
				$output[]=$this->replace($template, '%value%', $line);
			}
		}
		
		return $output;
	}
	
	function flatten($input, $limit, $nesting=0)
	{
		$output=array();
		$clashes=array();
		$this->getArrayNodes($output, $input, $clashes, $limit, $nesting);
		return $output;
	}
	
	private function getArrayNodes(&$output, $input, &$clashes, $limit, $nesting)
	{
		echo "$limit, $nesting\n";
		foreach ($input as $key=>$value)
		{
			if (is_array($value) and !(is_numeric($limit) and ($nesting>=$limit)))
			{
				$this->getArrayNodes($output, $value, $clashes, $limit, $nesting+1);
			}
			else
			{
				if (is_numeric($key)) $output[]=$value;
				else
				{
					if (!isset($output[$key])) $output[$key]=$value;
					else
					{
						# work out new key based on clashes
						$clashes[$key]=(isset($clashes[$key]))?$clashes[$key]+1:1;
						$newKey="$key{$clashes[$key]}";
						echo "Chose key $newKey\n";
						$output[$newKey]=$value;
					}
					
				}
			}
		}
	}
	
	private function requireEach($input, $search)
	{
		$output=array();
		foreach ($input as $line)
		{
			if (is_string($line))
			{
				if (preg_match('/'.$search.'/', $line))
				{
					$output[]=$line;
				}
			}
		}
		
		return $output;
	}
	
	private function requireEntry($input, $search)
	{
		$output=array();
		$searchParts=explode(',', $search);
		$neededKey=$searchParts[0];
		$neededRegex=$searchParts[1];
		
		foreach ($input as $line)
		{
			if ($neededKey)
			{
				if (isset($line[$neededKey]))
				{
					if ($neededRegex)
					{
						if (preg_match('/'.$search.'/', $line[$neededKey])) $output[]=$line;
					}
					else $output[]=$line;
				}
			}
			else
			{
				if (is_array($line))
				{
					if (count($this->requireEach($line, $neededRegex))) $output[]=$line;
				}
			}
		}
		
		print_r($output);
		return $output;
	}
}

$core=core::assert();
$core->registerModule(new Manipulator());
 
?>