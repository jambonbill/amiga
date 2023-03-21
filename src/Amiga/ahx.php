<?php
/**
 * AHX File manipulation
 * Converted from https://github.com/bryc/ahx-web-player/blob/master/ahx.js
 * @author : jambonbill <https://github.com/jambonbill/>
 */

namespace AMIGA;


use Exception;

/**
 * Read/Save AHX File
 * Author: Jambonbill
 */
class AHX{

	// Converted from	
	// https://github.com/bryc/ahx-web-player/blob/master/ahx.js
	
	private $filename = ''; 
	private $filesize = 0; 
	private $Id = '';//(bytes 0-3) This is the ID header. 
	private $NamePtr = 0;
	private $Name = '';
	private $Restart = 0;
	private $PositionNr = 0;
	private $TrackLength = 0;
	private $TrackNr = 0;
	private $InstrumentNr = 0;
	private $SubsongNr = 0;
	private $Revision = 0;
	private $SpeedMultiplier = 0;
	private $Positions = [];
	private $Tracks = [];
	private $Instruments = [];
	private $Subsongs = [];

	

	public function loadSong(string $filename)
	{
		if(!is_readable($filename)){
			throw new Exception("Not readable", 1);
		}	
		
		$this->filename=$filename;
		$this->filesize=filesize($filename);
		$handle = fopen($filename, "r");
		
		$this->initSong($handle);
		fclose($handle);
	}

	
	/**
	 * Load and decode AHX JSON file
	 * @param  string $filename [description]
	 * @return [type]           [description]
	 */
	public function loadJson(string $filename)
	{
		if (!is_readable($filename)) {
			throw new Exception("Not readable", 1);
		}

		$data=json_decode(file_get_contents($filename));
		print_r($data);
		//TODO SET AHX DATA
	}

	

	public function initSong($handle)
	{
		//fread(resource $stream, int $length): string|false
		//fseek(resource $stream, int $offset, int $whence = SEEK_SET): int
		//unpack(string $format, string $string, int $offset = 0): array|false
		//https://www.php.net/manual/en/function.pack.php
	
		//fseek($handle,3);//stream.pos = 3;
		$this->Id=fread($handle,3);//(bytes 0-3)
		
		if ($this->Id!=='THX') {
			throw new Exception("Not a valid THX/AHX file", 1);
		}
		//fseek($handle,3);
		$this->Revision = unpack('C',fread($handle,1))[1];//stream.readByte();
		
		//var SBPtr = 14;
		
		// Header ////////////////////////////////////////////
		// Songname
		//var NamePtr = stream.readShort();//16bit signed integer
		$a=unpack('C',fread($handle,1))[1];
		$b=unpack('C',fread($handle,1))[1];
		$this->NamePtr=$b+($a*256);//song name pointer

		//Go To Strings !
		fseek($handle, $this->NamePtr);

		//read strings
		$str=fread($handle,256);
		$strings=explode(chr(0),$str);
		$this->Name=$strings[0];

		//print_r($strings);
		

		//this.Name = stream.readStringAt(NamePtr);
		
		//NamePtr += this.Name.length + 1;
		fseek($handle,6);
		/*
		NYBBLE (byte 6):  Take the top 4 bits of byte six (bits 7-4).
                  Bit 7 indicates if track 0 is saved. If it is 1, track 0
                  is included. If it is 0, track 0 was empty, and is
                  therefore not saved with the module, to save space.

                  Now, the remaining 3 bits (bits 6-4) make a number
                  (calculation: (byte 6)>>4 & %111), this number is always
                  0 if it is an AHX0 mod, but in AHX1 this number is 0-3
                  and is the CIA speed of the module. Let us name this value
                  "SPD". If SPD=0, the mod plays at 50Hz. SPD=1, 100Hz.
                  SPD=2, 150Hz. SPD=3, 200Hz (think of it as single/double/
                  triple/quadruple timing).
		*/

		//this.SpeedMultiplier = ((stream.readByteAt(6)>>5)&3)+1;
		$byte6=unpack('C',fread($handle,1))[1];
		$this->SpeedMultiplier=(($byte6>>5)&3)+1;
		

		/*WORD (bytes 6,7): After ANDing with $FFF to ignore the top nybble, this word
                  value is the variable "LEN", which is the length of the
                  position list. Valid values for LEN range from 1 to 999.
		*/

		//this.PositionNr = ((stream.readByteAt(6)&0xf)<<8) | stream.readByteAt(7);
		fseek($handle, 6);
		$a=unpack('C',fread($handle,1))[1];
		$b=unpack('C',fread($handle,1))[1];
		$this->PositionNr = (($a&0xf)<<8) | $b;
		
		
		/*
		WORD (bytes 8,9): This is the variable "RES", the automatic restart point
                  for the song after it ends. Valid values for RES range
                  from 0 to (LEN-1).
		 */
		//this.Restart = (stream.readByteAt(8)<<8) | stream.readByteAt(9);
		fseek($handle,8);
		$a=unpack('C',fread($handle,1))[1];
		$b=unpack('C',fread($handle,1))[1];
		$this->Restart=($a<<8) | $b;

		
		fseek($handle,10);//re-set pointer just in case we moved it
		//this.TrackLength = stream.readByteAt(10);
		$this->TrackLength=unpack('C',fread($handle,1))[1];
		
		//this.TrackNr = stream.readByteAt(11);
		$this->TrackNr=unpack('C',fread($handle,1))[1];
		
		//this.InstrumentNr = stream.readByteAt(12);
		$this->InstrumentNr=unpack('C',fread($handle,1))[1];
		
		//this.SubsongNr = stream.readByteAt(13);
		//fseek($handle, 13);
		$this->SubsongNr=unpack('C',fread($handle,1))[1];

		//Read subsongs
		fseek($handle,14);
		for($i=0;$i<$this->SubsongNr;$i++){
			//this.Subsongs.push((stream.readByteAt(SBPtr+0)<<8)|stream.readByteAt(SBPtr+1));
			$a=unpack('C',fread($handle,1))[1];
			$b=unpack('C',fread($handle,1))[1];
			$this->Subsongs[]=($a<<8)|$b;			
		}

		// Position List /////////////////////////////////////
		for($i = 0; $i < $this->PositionNr; $i++) {
			//var Pos = AHXPosition();
			$Pos=['Track'=>[],'Transpose'=>[] ];
			for($j = 0; $j < 4; $j++) {
				$Pos['Track'][]=unpack('C',fread($handle,1))[1];
				//var Transpose = stream.readByteAt(SBPtr++);
				//if(Transpose & 0x80) Transpose = (Transpose & 0x7f) - 0x80; // signed char
				$Pos['Transpose'][]=unpack('C',fread($handle,1))[1];
			}
			$this->Positions[]=$Pos;
		}

		// Tracks ////////////////////////////////////////////
		$MaxTrack = $this->TrackNr;
		//Song.Tracks = new AHXStep*[MaxTrack+1];
		for($i = 0; $i < $MaxTrack+1; $i++) {
			$Track = [];
			//if((stream.readByteAt(6)&0x80)==0x80 && $i==0) { // empty track
			if(false) { // lazy jambon
				for($j = 0; $j < $this->TrackLength; $j++)
					$Track[]=['Note'=>0,'Instrument'=>0,'FX'=>0,'FXParam'=>0];
			} else {
				for($j = 0; $j < $this->TrackLength; $j++) {
					$b0=unpack('C',fread($handle,1))[1];
					$b1=unpack('C',fread($handle,1))[1];
					$b2=unpack('C',fread($handle,1))[1];
					$Step = ['Note'=>0,'Instrument'=>0,'FX'=>0,'FXParam'=>0];
					$Step['Note'] = ($b0>>2)&0x3f;
					$Step['Instrument'] = (($b0&0x3)<<4) | ($b1>>4);
					$Step['FX'] = $b1&0xf;
					$Step['FXParam'] = $b2;
					$Track[]=$Step;
					//SBPtr += 3;
				}
			}
			$this->Tracks[]=$Track;
		}

		// Instruments ///////////////////////////////////////
		//Song.Instruments = new AHXInstrument[Song.InstrumentNr+1];
		//this.Instruments.push(AHXInstrument()); // empty instrument 0
		$this->Instruments=[];
		$this->Instruments[0]=null;
		for($i = 1; $i < $this->InstrumentNr+1; $i++) {
			//TODO !!!
			$Instrument=[];
			$Instrument['Name']='INST#'.$i;
			//Instrument.Volume = stream.readByteAt(SBPtr+0);
			$Instrument['Volume']=unpack('C',fread($handle,1))[1];
			
			$b=unpack('C',fread($handle,1))[1];
			//Instrument.FilterSpeed = ((stream.readByteAt(SBPtr+1)>>3)&0x1f) | ((stream.readByteAt(SBPtr+12)>>2)&0x20);
			$Instrument['FilterSpeed']=(($b>>3)&0x1f);
			//Instrument.WaveLength = stream.readByteAt(SBPtr+1)&0x7;
			$Instrument['WaveLength']=$b&0x7;
			
			$Instrument['Envelope']=[];
			//Instrument.Envelope.aFrames = stream.readByteAt(SBPtr+2);
			$Instrument['Envelope']['aFrames']=unpack('C',fread($handle,1))[1];
			//Instrument.Envelope.aVolume = stream.readByteAt(SBPtr+3);
			$Instrument['Envelope']['aVolume']=unpack('C',fread($handle,1))[1];
			//Instrument.Envelope.dFrames = stream.readByteAt(SBPtr+4); //4
			$Instrument['Envelope']['dFrames']=unpack('C',fread($handle,1))[1];
			//Instrument.Envelope.dVolume = stream.readByteAt(SBPtr+5);
			$Instrument['Envelope']['dVolume']=unpack('C',fread($handle,1))[1];
			//Instrument.Envelope.sFrames = stream.readByteAt(SBPtr+6);
			$Instrument['Envelope']['sFrames']=unpack('C',fread($handle,1))[1];
			//Instrument.Envelope.rFrames = stream.readByteAt(SBPtr+7); //7
			$Instrument['Envelope']['rFrames']=unpack('C',fread($handle,1))[1];
			//Instrument.Envelope.rVolume = stream.readByteAt(SBPtr+8);
			$Instrument['Envelope']['rVolume']=unpack('C',fread($handle,1))[1];
			
			fread($handle,1);//skip (undocumented)
			fread($handle,1);//skip (undocumented)
			fread($handle,1);//skip (undocumented)

			//Instrument.FilterLowerLimit = stream.readByteAt(SBPtr+12)&0x7f;
			$Instrument['FilterLowerLimit']=unpack('C',fread($handle,1))[1]&0x7f;
			//Instrument.VibratoDelay = stream.readByteAt(SBPtr+13); //13
			$Instrument['VibratoDelay']=unpack('C',fread($handle,1))[1];
			
			//Instrument.HardCutReleaseFrames = (stream.readByteAt(SBPtr+14)>>4)&7;
			$b14=unpack('C',fread($handle,1))[1];
			$Instrument['HardCutReleaseFrames']=($b14>>4)&7;
			//Instrument.HardCutRelease = stream.readByteAt(SBPtr+14)&0x80?1:0;
			$Instrument['HardCutRelease']=($b14)&0x80?1:0;
			//Instrument.VibratoDepth = stream.readByteAt(SBPtr+14)&0xf; //14
			$Instrument['VibratoDepth']=($b14)&0xf;
			//Instrument.VibratoSpeed = stream.readByteAt(SBPtr+15);
			$Instrument['VibratoSpeed']=unpack('C',fread($handle,1))[1];
			//Instrument.SquareLowerLimit = stream.readByteAt(SBPtr+16);
			$Instrument['SquareLowerLimit']=unpack('C',fread($handle,1))[1];
			//Instrument.SquareUpperLimit = stream.readByteAt(SBPtr+17); //17
			$Instrument['SquareUpperLimit']=unpack('C',fread($handle,1))[1];
			//Instrument.SquareSpeed = stream.readByteAt(SBPtr+18);
			$Instrument['SquareSpeed']=unpack('C',fread($handle,1))[1];
			//Instrument.FilterUpperLimit = stream.readByteAt(SBPtr+19)&0x3f; //19
			$Instrument['FilterUpperLimit']=unpack('C',fread($handle,1))[1];
			
			$Instrument['PList']=[];
			//Instrument.PList.Speed = stream.readByteAt(SBPtr+20);
			$Instrument['PList']['Speed']=unpack('C',fread($handle,1))[1];
			//Instrument.PList.Length= stream.readByteAt(SBPtr+21);
			$Instrument['PList']['Length']=unpack('C',fread($handle,1))[1];
			//SBPtr += 22;
			
			/*
			for(var j = 0; j < Instrument.PList.Length; j++) {
				var Entry = AHXPlistEntry();
				Entry.FX[0] = (stream.readByteAt(SBPtr+0)>>2)&7;
				Entry.FX[1] = (stream.readByteAt(SBPtr+0)>>5)&7;
				Entry.Waveform = ((stream.readByteAt(SBPtr+0)<<1)&6) | (stream.readByteAt(SBPtr+1)>>7);
				Entry.Fixed = (stream.readByteAt(SBPtr+1)>>6)&1;
				Entry.Note = stream.readByteAt(SBPtr+1)&0x3f;
				Entry.FXParam[0] = stream.readByteAt(SBPtr+2);
				Entry.FXParam[1] = stream.readByteAt(SBPtr+3);
				Instrument.PList.Entries.push(Entry);
				SBPtr += 4;
			}
			*/
			for($j = 0; $j < $Instrument['PList']['Length']; $j++) {
				//var Entry = AHXPlistEntry();
				$b0=unpack('C',fread($handle,1))[1];
				$b1=unpack('C',fread($handle,1))[1];
				$b2=unpack('C',fread($handle,1))[1];
				$b3=unpack('C',fread($handle,1))[1];
				$Entry=[];
				$Entry['FX']=[];
				$Entry['FX'][0] = ($b0>>2)&7;
				$Entry['FX'][1] = ($b0>>5)&7;
				$Entry['Waveform'] = ($b0<<1)&6 | ($b1>>7);
				$Entry['Fixed'] = ($b1>>6)&1;
				$Entry['Note'] = $b1&0x3f;
				$Entry['FXParam'][0] = $b2;
				$Entry['FXParam'][1] = $b3;
				$Instrument['PList']['Entries'][]=$Entry;
				//SBPtr += 4;
			}

			$this->Instruments[$i]=$Instrument;
		}
	}

	/*
	function AHXStep() {
		return ['Note'=>0,'Instrument'=>0,'FX'=>0,'FXParam'=>0];
	}
	*/

	public function saveSong(string $filename)
	{
		//todo
	}


	public function debug()
	{
		$dat=[];
		$dat['filename']=$this->filename;
		$dat['filesize']=$this->filesize;
		$dat['Id']=$this->Id;
		$dat['Revision']=$this->Revision;

		$dat['NamePtr']=$this->NamePtr;
		$dat['Name']=$this->Name;
		$dat['Restart']=$this->Restart;
		$dat['PositionNr']=$this->PositionNr;
		$dat['TrackLength']=$this->TrackLength;
		$dat['TrackNr']=$this->TrackNr;
		$dat['InstrumentNr']=$this->InstrumentNr;
		$dat['SubsongNr']=$this->SubsongNr;
		
		$dat['SpeedMultiplier']=$this->SpeedMultiplier;
		//$dat['Positions']=$this->Positions;
		//$dat['Tracks']=$this->Tracks;
		//$dat['Instruments']=$this->Instruments;
		//$dat['Subsongs']=$this->Subsongs;
		return $dat;
	}

	public function Positions()
	{
		return $this->Positions;	
	}

	public function Tracks()
	{
		return $this->Tracks;
	}

	public function Instruments()
	{
		return $this->Instruments;
	}
}

/*
function _bin16dec($bin) {
    // converts 16bit binary number string to integer using two's complement
    $num = bindec($bin) & 0xFFFF; // only use bottom 16 bits
    if (0x8000 & $num) {
        $num = - (0x010000 - $num);
    }
    return $num;
}
*/