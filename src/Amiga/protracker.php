<?php
/**
 * Amiga Protracker File Reader
 * @author : jambonbill <jambonbill@gmail.com>
 * http://coppershade.org/articles/More!/Topics/Protracker_File_Format/
 */

namespace AMIGA;

use Exception;

/**
 * Read Protracker (.mod) file
 * Author: Jambonbill
 */
class Protracker{

	/**
	 * source filename
	 * @var string
	 */
	private $filename = ''; 
	
	/**
	 * source filesize
	 * @var integer
	 */
	private $filesize = 0; 
	
	/**
	 * Song name
	 * @var string
	 */
	private $title='';
	
	/**
	 * Protracker Signature. Should be 'M.K.' or 'M!K!','FLT4','FLT8' 
	 * @var string
	 */
	private $MK='';
	
	/**
	 * List of samples
	 * @var array
	 */
	private $samples=[];
	

	private $nsp=0;//Number of song positions
	
	/**
	 * Song pattern sequence
	 * @var array
	 */
	private $pattern_table=[];
	
	/**
	 * Song pattern data
	 * @var array
	 */
	private $patterns=[];
	

	/**
	 * Debug flag
	 * @var boolean
	 */
	private $debug=false;
	

	public function loadSong(string $filename)
	{
		if(!is_readable($filename)){
			throw new Exception("$filename Not readable", 1);
		}	
		
		$this->filename=$filename;
		$this->filesize=filesize($filename);
		$handle = fopen($filename, "r");
		
		$this->decode($handle);
		fclose($handle);
	}


	/**
	 * Check Protracker file is valid (contain signature M.K.)
	 * @param  string $filename [description]
	 * @return [type]           [description]
	 */
	public function is_protracker(string $filename)
	{
		//TODO !
	}


	
	/**
	 * Return sample data or something like that
	 * @return [type] [description]
	 */
	public function sampleData()
	{
		//TODO
	}


	public function decode($handle)
	{
		$this->title=fread($handle,20);//(bytes 0-3)
		
		//SAMPLES
		for ($i=0;$i<31;$i++) {
			$samplename = fread($handle,22);;//Sample's name, padded with null bytes.
			$this->samples[]=$samplename;
			

			// All 2 byte lengths are stored with the Hi-byte first, as is usual on the Amiga/Motorola (big-endian format).
			
			$sl=unpack('n',fread($handle,2))[1];//Sample length in words (1 word = 2 bytes).
			
			$a=unpack('C',fread($handle,1))[1];//Lowest four bits represent a signed nibble (-8..7) which is the finetune value for the sample. 
			
			$a=unpack('C',fread($handle,1))[1];//Volume of sample. Legal values are 0..64
			
			$a=unpack('n',fread($handle,2))[1];//Start of sample repeat offset in words.
			$sr=unpack('n',fread($handle,2))[1];//Length of sample repeat in words.
			//echo "sr=$sr\n";
		}


		$nsp=unpack('C',fread($handle,1))[1];//Number of song positions
		$this->nsp=$nsp;
		//echo "nsp=$nsp\n";

		$skip=unpack('C',fread($handle,1))[1];//Historically set to 127, but can be safely ignored.
		//echo "skip=$skip\n";
		
		for ($i=0;$i<128;$i++) {//Pattern table: patterns to play in each song position (0..127)
          	//Each byte has a legal value of 0..63 
			$pattern=unpack('C',fread($handle,1))[1];
			$this->pattern_table[]=$pattern;
		}

		//trim pattern table (0 at the end)
		while($this->pattern_table[count($this->pattern_table)-1]==0){
			array_pop($this->pattern_table);
		}

		//Get highest value
		$max=max($this->pattern_table);
		//echo "max=$max\n";

		//The four letters "M.K."
		$this->MK=fread($handle,4);
		
		if($this->MK!='M.K.'){
			throw new Exception("not a valid M.K.", 1);
		}
		//echo "MK=".$this->MK."\n";exit;
		
		//Pattern data
		for($pat=0; $pat<$max; $pat++) {
			for($row=0; $row<64; $row++) {
				for($track=0; $track<4; $track++) {				
						
					/*
					 _____byte 1_____   byte2_    _____byte 3_____   byte4_
					/                \ /      \  /                \ /      \
					0000          0000-00000000  0000          0000-00000000

					Upper four    12 bits for    Lower four    Effect command.
					bits of sam-  note period.   bits of sam-
					ple number.                  ple number.
					*/
					$p=ftell($handle);
					if($p>=$this->filesize){
						throw new Exception("Pointer out of range", 1);
						return;
					}

					$bytes=

					$b1=unpack('C',fread($handle,1))[1];//byte 1
					$hb1=$b1 >> 4;//upper 4bits
					$lb1=$b1 & 0x0f;//last 4
					$b2=unpack('C',fread($handle,1))[1];//byte 2 
					$b3=unpack('C',fread($handle,1))[1];//etc
					$hb3=$b3 >> 4;//upper 4bits
					$lb3=$b3 & 0x0f;//last 4
					$b4=unpack('C',fread($handle,1))[1];//...
					
					//Decode into Note/Effect/Sample num
					$period=$b2+$lb1*256;
					$note=$this->periodToNote($period);
					
					if($this->noteToString($note)=="B-0"){
						exit("note=$note");
					}
					
					$dat=['note'=>$note, 'str'=>$this->noteToString($note), 'command'=>$lb3, 'fx'=>$b4, 'samplenum'=>$hb3+$hb1*16];

					$this->patterns[$pat][$row][$track]=$dat;					
				}
			}
		}
	}

	
	

	/**
	 * Return pattern data
	 * @param  int    $n [description]
	 * @return [type]    [description]
	 */
	public function pattern(int $n)
	{
		if (!$this->patterns[$n]) {
			throw new Exception("no pattern like #$n", 1);
		}
		return $this->patterns[$n];
	}

	

	/**
	 * Return a pattern as a page of text
	 * @param  int    $n [description]
	 * @return [type]    [description]
	 */
	public function patternText(int $n)
	{
		
		function zeropad($num, $lim)
		{
		   return (strlen($num) >= $lim) ? $num : zeropad("0" . $num);
		}
		
		
		$data=$this->pattern($n);
		$str="Pattern #".$n."\n";

		for($i=0;$i<64;$i++){
			$step=$data[$i];
			$str.=sprintf("%02d",$i);
			$str.='| ';
			foreach($step as $cell){
				$str.=$cell['str'];	
				$str.=' ';	
				if($cell['samplenum']){
					$str.=sprintf("%02d",dechex($cell['samplenum']));
				}else{
					$str.='  ';
				}
				$str.=' ';		
				$str.=strtoupper(dechex($cell['command']));
				$str.=sprintf("%02d",dechex($cell['fx']));
				$str.=' | ';
			}
			$str.="\n";
			
		}

		return $str;
	}

	
	public function debug(){
		$dat=[];
		$dat['filename']=$this->filename;
		$dat['title']=$this->title;
		//$dat['samples']=$this->samples;
		//$dat['nsp']=$this->nsp;
		$dat['pattern_table']=$this->pattern_table;
		$dat['pattern_count']=count($this->patterns);
		$dat['MK']=$this->MK;
		return $dat;
	}



	/**
	 * Convert a PT period into a note number
	 * @param  int    $period [description]
	 * @return [type]         [description]
	 */
	private function periodToNote(int $period)
	{
		// Periodtable for Tuning 0, Normal
	  	// C-0 to B-0 : 1712,1616,1525,1440,1357,1281,1209,1141,1077,1017, 961, 907
	  	// C-1 to B-1 : 856,808,762,720,678,640,604,570,538,508,480,453
	  	// C-2 to B-2 : 428,404,381,360,339,320,302,285,269,254,240,226
	  	// C-3 to B-3 : 214,202,190,180,170,160,151,143,135,127,120,113
	  	// C-4 to B-4 : 107, 101,  95,  90,  85,  80,  76,  71,  67,  64,  60,  57
	  	//
		
		$table=[];
		$table[0]=-1;//---
		$table[1]=-1;//---
		$table[1295]=-1;//C-0
		$table[1712]=0;//C-0
		$table[1616]=1;
		$table[1525]=2;
		$table[1440]=3;
		$table[1357]=4;
		$table[1281]=5;
		$table[1209]=6;
		$table[1141]=7;
		$table[1077]=8;
		$table[1017]=9;
		$table[961]=10;
		$table[907]=11;
		$table[856]=12;//C-1
		$table[808]=13;
		$table[762]=14;
		$table[720]=15;
		$table[678]=16;
		$table[640]=17;
		$table[604]=18;
		$table[570]=19;
		$table[538]=20;
		$table[508]=21;
		$table[480]=22;
		$table[453]=23;
		$table[428]=24;//C-2
		$table[404]=25;
		$table[381]=26;
		$table[360]=27;
		$table[339]=28;
		$table[320]=29;
		$table[302]=30;
		$table[285]=31;
		$table[269]=32;
		$table[254]=33;
		$table[240]=34;
		$table[226]=35;
		$table[214]=36;//C-3
		$table[202]=37;
		$table[190]=38;
		$table[180]=39;
		$table[170]=40;
		$table[160]=41;
		$table[151]=42;
		$table[143]=43;
		$table[135]=44;
		$table[127]=45;
		$table[120]=46;
		$table[113]=47;
		// C-4 to B-4 : 107, 101,  95,  90,  85,  80,  76,  71,  67,  64,  60,  57
		if(isset($table[$period])){
			return $table[$period];	
		}
		return 0;
	}


	
	/**
	 * Convert a note number to a note String, like 'A#3'
	 * @param  int    $note [description]
	 * @return [type]       [description]
	 */
	private function noteToString(int $note)
	{
		if($note<=0){
			return "---";
		}

    	$not=$note%12;
    	$notes=['C-','C#','D-','D#','E-','F-','F#','G-','G#','A-','A#','B-'];
    	$oct=floor($note/12);
    	return $notes[$not].$oct;
	}
		
}

/*
Module Format:
# Bytes   Description
-------   -----------
20        The module's title, padded with null (\0) bytes. Original
          Protracker wrote letters only in uppercase.

(Data repeated for each sample 1-15 or 1-31)
22        Sample's name, padded with null bytes. If a name begins with a
          '#', it is assumed not to be an instrument name, and is
          probably a message.
2         Sample length in words (1 word = 2 bytes). The first word of the sample is overwritten by the tracker, so a length of 1
          still means an empty sample. See below for sample format.
1         Lowest four bits represent a signed nibble (-8..7) which is
          the finetune value for the sample. Each finetune step changes
          the note 1/8th of a semitone. Implemented by switching to a
          different table of period-values for each finetune value.
1         Volume of sample. Legal values are 0..64. Volume is the linear
          difference between sound intensities. 64 is full volume, and
          the change in decibels can be calculated with 20*log10(Vol/64)
2         Start of sample repeat offset in words. Once the sample has
          been played all of the way through, it will loop if the repeat
          length is greater than one. It repeats by jumping to this
          position in the sample and playing for the repeat length, then
          jumping back to this position, and playing for the repeat
          length, etc.
2         Length of sample repeat in words. Only loop if greater than 1.
(End of this sample's data.. each sample uses the same format and they
 are stored sequentially)

N.B. All 2 byte lengths are stored with the Hi-byte first, as is usual on the Amiga (big-endian format).

1         Number of song positions (ie. number of patterns played
          throughout the song). Legal values are 1..128.
1         Historically set to 127, but can be safely ignored.
          Noisetracker uses this byte to indicate restart position -
          this has been made redundant by the 'Position Jump' effect.
128       Pattern table: patterns to play in each song position (0..127)
          Each byte has a legal value of 0..63 (note the Protracker
          exception below). The highest value in this table is the
          highest pattern stored, no patterns above this value are
          stored.
(4)       The four letters "M.K." These are the initials of
          Unknown/D.O.C. who changed the format so it could handle 31
          samples (sorry.. they were not inserted by Mahoney & Kaktus).
          Startrekker puts "FLT4" or "FLT8" here to indicate the # of
          channels. If there are more than 64 patterns, Protracker will
          put "M!K!" here. You might also find: "6CHN" or "8CHN" which
          indicate 6 or 8 channels respectively. If no letters are here,
          then this is the start of the pattern data, and only 15
          samples were present.
 */