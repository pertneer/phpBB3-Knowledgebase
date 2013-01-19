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
	var $BLOCKQUOTE;
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
		$this->SPAN = 
		$this->BLOCKQUOTE = '';
		$this->LI = 0;
		$this->HREF = '';
	}
	function WriteHTML($html)
	{
		// HTML parser
		$html = str_replace("\n",' ',$html);
		$html = str_replace('<blockquote class="uncited"><div>','<blockquote>',$html);
		$html = str_replace('</div></blockquote>','</blockquote>',$html);
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
								$color = $this->hex2dec('#800040');
								$this->SetTextColor($color['R'], $color['G'], $color['B']);
							break;
							case $h2 :
								$this->SetFont('Arial','B',15);
								$color = $this->hex2dec('#0000BF');
								$this->SetTextColor($color['R'], $color['G'], $color['B']);
							break;
							case $h3:
								$this->SetFont('Arial','B',14);
								$color = $this->hex2dec('#008000');
								$this->SetTextColor($color['R'], $color['G'], $color['B']);
							break;
							case $h4:
								$this->SetFont('Arial','B',13);
								$color = $this->hex2dec('#408080');
								$this->SetTextColor($color['R'], $color['G'], $color['B']);
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
						$size = getimagesize($img_name);
						$this->Ln($this->px2mm($size[1]));//set an integer of the height of the image?
					}
					if($this->BLOCKQUOTE)
					{
						$this->SetX($this->GetX()+20);
						//$this->Write(40, print_r($this->BLOCKQUOTE));
						// background #EBEADD
						$color = $this->hex2dec('#EBEADD');
						$this->SetFillColor($color['R'], $color['G'], $color['B']);
						// border color #DBDBCE
						$color = $this->hex2dec('#DBDBCE');
						$this->SetDrawColor($color['R'], $color['G'], $color['B']);
						$this->SetTextColor(0,0,0);
						$this->Rect($this->GetX(), $this->GetY(), $this->GetStringWidth($value) + 3, 10 , 'DF');
						$this->Ln(3);
						$this->SetX($this->GetX()+20);
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
					/*$debug = '';
					foreach($attr as $stuff){
						$debug .= $stuff;
					}
					$this->Write(5, $tag . $debug);
					$this->Ln();
					*/
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
		if($tag == 'BLOCKQUOTE')
		{
			$this->BLOCKQUOTE = $tag;//$attr['BLOCKQUOTE']
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
		if($tag =='BLOCKQUOTE')
		{
			$this->BLOCKQUOTE = '';
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

	function hex2dec($couleur = "#000000")
	{
		$R = substr($couleur, 1, 2);
		$rouge = hexdec($R);
		$V = substr($couleur, 3, 2);
		$vert = hexdec($V);
		$B = substr($couleur, 5, 2);
		$bleu = hexdec($B);
		$tbl_couleur = array();
		$tbl_couleur['R']=$rouge;
		$tbl_couleur['G']=$vert;
		$tbl_couleur['B']=$bleu;
		return $tbl_couleur;
	}

	function px2mm($px)
	{
		return $px*25.4/72;
	}
}
?>