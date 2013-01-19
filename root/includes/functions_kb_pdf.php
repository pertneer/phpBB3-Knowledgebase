<?php
/**
*
* @package phpBB phpBB3-Knowledgebase Mod (KB)
* @version $Id: functions_kb_pdf.php $
* @copyright (c) 2013 Pertneer
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

//this was a copy paste and edit from the fpdf samples to start with then modified to use with this mod
class PDF extends FPDF
{
	var $B;
	var $I;
	var $U;
	var $SPAN;
	var $IMG;
	var $LI;
	var $HREF;

	function PDF($orientation='P', $unit='mm', $size='A4')
	{
		// Call parent constructor
		$this->FPDF($orientation,$unit,$size);
		// Initialization
		$this->B = 0;
		$this->I = 0;
		$this->U = 0;
		$this->IMG = '';
		$this->SPAN = '';
		$this->LI = 0;
		$this->HREF = '';
	}
	function WriteHTML($html)
	{
		// HTML parser
		$html = str_replace("\n",' ',$html);
		$a = preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
		foreach($a as $key=>$value)
		{
			if($key%2==0)
			{
				// Text
				if($this->HREF)
				{
					$this->PutLink($this->HREF,$value);
				}
				else
				{
					if($this->SPAN)
					{
						$h1 = 'color:_#800040;_font-size:_180%;_font-weight:_bold;';
						$h2 = 'color:_#0000BF;_font-size:_160%;_font-weight:_bold;';
						$h3 = 'color:_#008000;_font-size:_145%;_font-weight:_bold;';
						$h4 = 'color:_#408080;_font-size:_135%;_font-weight:_bold;';
						//$this->Write(40, print_r($this->SPAN));
						switch($this->SPAN)
						{
							case $h1:
								$this->SetFont('Arial','B',16);
								$this->SetTextColor(128,0,64);
							break;
							case $h2 :
								$this->SetFont('Arial','B',15);
								$this->SetTextColor(0,0,191);
							break;
							case $h3:
								$this->SetFont('Arial','B',14);
								$this->SetTextColor(0,128,0);
							break;
							case $h4:
								$this->SetFont('Arial','B',13);
								$this->SetTextColor(64,128,128);
							break;
						}
					}
					if($this->IMG)
					{
						//http://www.domain.tld/url/to/image/lid.JPG
						$img_name = $this->IMG;
						//$this->Write(40, print_r($this->IMG));
						$this->Image($img_name,$this->GetX(),$this->GetY());
						$this->IMG = '';
					}
					$this->Write(5,$value);
					$this->SetTextColor(0,0,0);
				}
			}
			else
			{
				// Tag
				if($value[0]=='/')
				{
					$this->CloseTag(strtoupper(substr($value,1)));
				}
				else
				{
					// Extract attributes
					$value = str_replace(': ', ':_', $value);
					$value = str_replace('; ', ';_', $value);
					$a2 = explode(' ',$value);
					$tag = strtoupper(array_shift($a2));
					$attr = array();
					foreach($a2 as $v)
					{
						if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3))
						{
							$attr[strtoupper($a3[1])] = $a3[2];
						}
					}
					$this->OpenTag($tag,$attr);
				}
			}
		}
	}

	function OpenTag($tag, $attr)
	{
		// Opening tag
		$tags = array('B', 'I', 'U');


		if($tag == 'SPAN')
		{
			$this->SPAN = $attr['STYLE'];
		}
		if($tag == 'IMG')
		{
			$this->IMG = $attr['SRC'];
		}

		if(in_array($tag, $tags))
		{
			$this->SetStyle($tag,true);
		}
		if($tag=='A')
		{
			$this->HREF = $attr['HREF'];
		}
		if($tag=='BR' || $tag == 'LI' || $tag == 'DD')
		{
			$this->Ln(5);
		}
	}

	function CloseTag($tag)
	{
		// Closing tag
		if($tag == 'SPAN')
		{
			$this->SPAN = '';
		}
		if($tag=='B' || $tag=='I' || $tag=='U')
		{
			$this->SetStyle($tag,false);
		}
		if($tag=='A')
		{
			$this->HREF = '';
		}
	}

	function SetStyle($tag, $enable)
	{
		// Modify style and select corresponding font
		$this->$tag += ($enable ? 1 : -1);
		$style = '';
		foreach(array('B', 'I', 'U') as $s)
		{
			if($this->$s>0)
			{
				$style .= $s;
			}
		}
		$this->SetFont('',$style);
	}

	function PutLink($URL, $txt)
	{
		// Put a hyperlink
		$this->SetTextColor(0,0,255);
		$this->SetStyle('U',true);
		$this->Write(5,$txt,$URL);
		$this->SetStyle('U',false);
		$this->SetTextColor(0);
	}
}
?>