<?php
/*
 * mime_parser.php
 *
 * @(#) $Id: mime_parser.php,v 1.20 2006/09/11 21:06:36 mlemos Exp $
 *
 */

define('MIME_PARSER_START',        1);
define('MIME_PARSER_HEADER',       2);
define('MIME_PARSER_HEADER_VALUE', 3);
define('MIME_PARSER_BODY',         4);
define('MIME_PARSER_BODY_START',   5);
define('MIME_PARSER_BODY_DATA',    6);
define('MIME_PARSER_BODY_DONE',    7);
define('MIME_PARSER_END',          8);

define('MIME_MESSAGE_START',            1);
define('MIME_MESSAGE_GET_HEADER_NAME',  2);
define('MIME_MESSAGE_GET_HEADER_VALUE', 3);
define('MIME_MESSAGE_GET_BODY',         4);
define('MIME_MESSAGE_GET_BODY_PART',    5);

/*
{metadocument}<?xml version="1.0" encoding="ISO-8859-1" ?>
<class>

	<package>net.manuellemos.mimeparser</package>

	<version>@(#) $Id: mime_parser.php,v 1.20 2006/09/11 21:06:36 mlemos Exp $</version>
	<copyright>Copyright � (C) Manuel Lemos 2006</copyright>
	<title>MIME parser</title>
	<author>Manuel Lemos</author>
	<authoraddress>mlemos-at-acm.org</authoraddress>

	<documentation>
		<idiom>en</idiom>
		<purpose>Parse MIME encapsulated e-mail message data compliant with
			the RFC 2822 or aggregated in mbox format.</purpose>
		<usage>Use the function <functionlink>Decode</functionlink> function
			to retrieve the structure of the messages to be parsed. Adjust its
			parameters to tell how to return the decoded body data.
			Use the <tt>SaveBody</tt> parameter to make the body parts be saved
			to files when the message is larger than the available memory. Use
			the <tt>SkipBody</tt> parameter to just retrieve the message
			structure without returning the body data.<paragraphbreak />
			If the message data is an archive that may contain multiple messages
			aggregated in the mbox format, set the variable
			<variablelink>mbox</variablelink> to <booleanvalue>1</booleanvalue>.</usage>
	</documentation>

{/metadocument}
*/

class mime_parser_class
{
/*
{metadocument}
	<variable>
		<name>error</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Store the message that is returned when an error
				occurs.</purpose>
			<usage>Check this variable to understand what happened when a call to
				any of the class functions has failed.<paragraphbreak />
				This class uses cumulative error handling. This means that if one
				class functions that may fail is called and this variable was
				already set to an error message due to a failure in a previous call
				to the same or other function, the function will also fail and does
				not do anything.<paragraphbreak />
				This allows programs using this class to safely call several
				functions that may fail and only check the failure condition after
				the last function call.<paragraphbreak />
				Just set this variable to an empty string to clear the error
				condition.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $error='';

/*
{metadocument}
	<variable>
		<name>error_position</name>
		<type>INTEGER</type>
		<value>-1</value>
		<documentation>
			<purpose>Point to the position of the message data or file that
				refers to the last error that occurred.</purpose>
			<usage>Check this variable to determine the relevant position of the
				message when a parsing error occurs.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $error_position = -1;

/*
{metadocument}
	<variable>
		<name>mbox</name>
		<type>BOOLEAN</type>
		<value>0</value>
		<documentation>
			<purpose>Specify whether the message data to parse is a single RFC
				2822 message or it is an archive that contain multiple messages in
				the mbox format.</purpose>
			<usage>Set this variable to <booleanvalue>1</booleanvalue> if it is
				it is intended to parse an mbox message archive.<br />
				mbox archives may contain multiple messages. Each message starts
				with the header <tt>From</tt>. Since all valid RFC 2822 headers
				must with a colon, the class will fail to parse a mbox archive if
				this variable is set to <booleanvalue>0</booleanvalue>.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $mbox = 0;

/*
{metadocument}
	<variable>
		<name>decode_headers</name>
		<type>BOOLEAN</type>
		<value>1</value>
		<documentation>
			<purpose>Specify whether the message headers should be decoded.</purpose>
			<usage>Set this variable to <booleanvalue>1</booleanvalue> if it is
				necessary to decode message headers that may have non-ASCII
				characters and use other character set encodings.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $decode_headers = 1;

/*
{metadocument}
	<variable>
		<name>decode_bodies</name>
		<type>BOOLEAN</type>
		<value>1</value>
		<documentation>
			<purpose>Specify whether the message body parts should be decoded.</purpose>
			<usage>Set this variable to <booleanvalue>1</booleanvalue> if it is
				necessary to parse the message bodies and extract its part
				structure.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $decode_bodies = 1;

	/* Private variables */
	var $state = MIME_PARSER_START;
	var $buffer = '';
	var $buffer_position = 0;
	var $offset = 0;
	var $parts = array();
	var $part_position = 0;
	var $headers = array();
	var $body_parser;
	var $body_parser_state = MIME_PARSER_BODY_DONE;
	var $body_buffer = '';
	var $body_buffer_position = 0;
	var $body_offset = 0;
	var $current_header = '';
	var $file;
	var $body_file;
	var $position = 0;
	var $body_part_number = 1;

	/* Private functions */

	Function SetError($error)
	{
		$this->error = $error;
		return(0);
	}

	Function SetPositionedError($error, $position)
	{
		$this->error_position = $position;
		return($this->SetError($error));
	}

	Function SetPHPError($error, &$php_error_message)
	{
		if(IsSet($php_error_message)
		&& strlen($php_error_message))
			$error .= ': '.$php_error_message;
		return($this->SetError($error));
	}

	Function ParsePart($end, &$part, &$need_more_data)
	{
		$need_more_data = 0;
		switch($this->state)
		{
			case MIME_PARSER_START:
				$part=array(
					'Type'=>'MessageStart',
					'Position'=>$this->offset + $this->buffer_position
				);
				$this->state = MIME_PARSER_HEADER;
				break;
			case MIME_PARSER_HEADER:
				if(GetType($line_break=strpos($this->buffer, $break="\r\n", $this->buffer_position))=='integer'
				|| GetType($line_break=strpos($this->buffer, $break="\n", $this->buffer_position))=='integer'
				|| GetType($line_break=strpos($this->buffer, $break="\r", $this->buffer_position))=='integer')
				{
					$next = $line_break + strlen($break);
					if(!strcmp($break,"\r")
					&& strlen($this->buffer) == $next
					&& !$end)
					{
						$need_more_data = 1;
						break;
					}
					if($line_break==$this->buffer_position)
					{
						$part=array(
							'Type'=>'BodyStart',
							'Position'=>$this->offset + $this->buffer_position
						);
						$this->buffer_position = $next;
						$this->state = MIME_PARSER_BODY;
						break;
					}
				}
				if(GetType($colon=strpos($this->buffer, ':', $this->buffer_position))=='integer')
				{
					if(GetType($space=strpos(substr($this->buffer, $this->buffer_position, $colon - $this->buffer_position), ' '))=='integer')
					{
						if(!$this->mbox
						|| strcmp(strtolower(substr($this->buffer, $this->buffer_position, $space)), 'from'))
							return($this->SetPositionedError('invalid header name line', $this->buffer_position));
						$next = $this->buffer_position + $space + 1;
					}
					else
						$next = $colon+1;
				}
				else
				{
					$need_more_data = 1;
					break;
				}
				$part=array(
					'Type'=>'HeaderName',
					'Name'=>substr($this->buffer, $this->buffer_position, $next - $this->buffer_position),
					'Position'=>$this->offset + $this->buffer_position
				);
				$this->buffer_position = $next;
				$this->state = MIME_PARSER_HEADER_VALUE;
				break;
			case MIME_PARSER_HEADER_VALUE:
				$position = $this->buffer_position;
				$value = '';
				for(;;)
				{
					if(GetType($line_break=strpos($this->buffer, $break="\r\n", $position))=='integer'
					|| GetType($line_break=strpos($this->buffer, $break="\n", $position))=='integer'
					|| GetType($line_break=strpos($this->buffer, $break="\r", $position))=='integer')
					{
						$next = $line_break + strlen($break);
						$line = substr($this->buffer, $position, $line_break - $position);
						if(strlen($this->buffer) == $next)
						{
							if(!$end)
							{
								$need_more_data = 1;
								break 2;
							}
							$value .= $line;
							$part=array(
								'Type'=>'HeaderValue',
								'Value'=>$value,
								'Position'=>$this->offset + $this->buffer_position
							);
							$this->buffer_position = $next;
							$this->state = MIME_PARSER_END;
							break ;
						}
						else
						{
							$character = $this->buffer[$next];
							if(!strcmp($character, ' ')
							|| !strcmp($character, "\t"))
							{
								$value .= $line;
								$position = $next;
							}
							else
							{
								$value .= $line;
								$part=array(
									'Type'=>'HeaderValue',
									'Value'=>$value,
									'Position'=>$this->offset + $this->buffer_position
								);
								$this->buffer_position = $next;
								$this->state = MIME_PARSER_HEADER;
								break 2;
							}
						}
					}
					else
					{
						if(!$end)
						{
							$need_more_data = 1;
							break;
						}
						else
						{
							$value .= substr($this->buffer, $position);
							$part=array(
								'Type'=>'HeaderValue',
								'Value'=>$value,
								'Position'=>$this->offset + $this->buffer_position
							);
							$this->buffer_position = strlen($this->buffer);
							$this->state = MIME_PARSER_END;
							break;
						}
					}
				}
				break;
			case MIME_PARSER_BODY:
				if($this->mbox)
				{
					$add = 0;
					$append='';
					if(GetType($line_break=strpos($this->buffer, $break="\r\n", $this->buffer_position))=='integer'
					|| GetType($line_break=strpos($this->buffer, $break="\n", $this->buffer_position))=='integer'
					|| GetType($line_break=strpos($this->buffer, $break="\r", $this->buffer_position))=='integer')
					{
						$next = $line_break + strlen($break);
						$following = $next + strlen($break);
						if($following >= strlen($this->buffer)
						|| GetType($line=strpos($this->buffer, $break, $following))!='integer')
						{
							if(!$end)
							{
								$need_more_data = 1;
								break;
							}
						}
						$start = strtolower(substr($this->buffer, $next, strlen($break.'from ')));
						if(!strcmp($break.'from ', $start))
						{
							if($line_break == $this->buffer_position)
							{
								$part=array(
									'Type'=>'MessageEnd',
									'Position'=>$this->offset + $this->buffer_position
								);
								$this->buffer_position = $following;
								$this->state = MIME_PARSER_START;
								break;
							}
							else
								$add = strlen($break);
							$next = $line_break;
						}
						else
						{
							$start = strtolower(substr($this->buffer, $next, strlen('>from ')));
							if(!strcmp('>from ', $start))
							{
								$part=array(
									'Type'=>'BodyData',
									'Data'=>substr($this->buffer, $this->buffer_position, $next - $this->buffer_position),
									'Position'=>$this->offset + $this->buffer_position
								);
								$this->buffer_position = $next + 1;
								break;
							}
						}
					}
					else
					{
						if(!$end)
						{
							$need_more_data = 1;
							break;
						}
						$next = strlen($this->buffer);
						$append="\r\n";
					}
					if($next > $this->buffer_position)
					{
						$part=array(
							'Type'=>'BodyData',
							'Data'=>substr($this->buffer, $this->buffer_position, $next + $add - $this->buffer_position).$append,
							'Position'=>$this->offset + $this->buffer_position
						);
					}
					elseif($end)
					{
						$part=array(
							'Type'=>'MessageEnd',
							'Position'=>$this->offset + $this->buffer_position
						);
						$this->state = MIME_PARSER_END;
					}
					$this->buffer_position = $next;
				}
				else
				{
					if(strlen($this->buffer)-$this->buffer_position)
					{
						$data=substr($this->buffer, $this->buffer_position, strlen($this->buffer) - $this->buffer_position);
						if($end
						&& strcmp(substr($data,-1),"\n")
						&& strcmp(substr($data,-1),"\r"))
							$data.="\n";
						$part=array(
							'Type'=>'BodyData',
							'Data'=>$data,
							'Position'=>$this->offset + $this->buffer_position
						);
						$this->buffer_position = strlen($this->buffer);
						$need_more_data = !$end;
					}
					else
					{
						if($end)
						{
							$part=array(
								'Type'=>'MessageEnd',
								'Position'=>$this->offset + $this->buffer_position
							);
							$this->state = MIME_PARSER_END;
						}
						else
							$need_more_data = 1;
					}
				}
				break;
			default:
				return($this->SetPositionedError($this->state.' is not a valid parser state', $this->buffer_position));
		}
		return(1);
	}

	Function QueueBodyParts()
	{
		for(;;)
		{
			if(!$this->body_parser->GetPart($part,$end))
				return($this->SetError($this->body_parser->error));
			if($end)
				return(1);
			if(!IsSet($part['Part']))
				$part['Part']=$this->headers['Boundary'];
			$this->parts[]=$part;
		}
	}

	Function DecodePart($part)
	{
		switch($part['Type'])
		{
			case 'MessageStart':
				$this->headers=array();
				break;
			case 'HeaderName':
				if($this->decode_bodies)
					$this->current_header = strtolower($part['Name']);
				break;
			case 'HeaderValue':
				if($this->decode_headers)
				{
					$value = $part['Value'];
					$error = '';
					for($decoded_header = array(), $position = 0; $position<strlen($value); )
					{
						if(GetType($encoded=strpos($value,'=?', $position))!='integer')
						{
							if($position<strlen($value))
							{
								if(count($decoded_header))
									$decoded_header[count($decoded_header)-1]['Value'].=substr($value, $position);
								else
								{
									$decoded_header[]=array(
										'Value'=>substr($value, $position),
										'Encoding'=>'ASCII'
									);
								}
							}
							break;
						}
						$set = $encoded + 2;
						if(GetType($method=strpos($value,'?', $set))!='integer')
						{
							$error = 'invalid header encoding syntax '.$part['Value'];
							$error_position = $part['Position'] + $set;
							break;
						}
						$encoding=strtoupper(substr($value, $set, $method - $set));
						$method += 1;
						if(GetType($data=strpos($value,'?', $method))!='integer')
						{
							$error = 'invalid header encoding syntax '.$part['Value'];
							$error_position = $part['Position'] + $set;
							break;
						}
						$start = $data + 1;
						if(GetType($end=strpos($value,'?=', $start))!='integer')
						{
							$error = 'invalid header encoding syntax '.$part['Value'];
							$error_position = $part['Position'] + $start;
							break;
						}
						if($encoded > $position)
						{
							if(count($decoded_header))
								$decoded_header[count($decoded_header)-1]['Value'].=substr($value, $position, $encoded - $position);
							else
							{
								$decoded_header[]=array(
									'Value'=>substr($value, $position, $encoded - $position),
									'Encoding'=>'ASCII'
								);
							}
						}
						switch(strtolower(substr($value, $method, $data - $method)))
						{
							case 'q':
								if($end>$start)
								{
									for($decoded = '', $position = $start; $position < $end ; )
									{
										switch($value[$position])
										{
											case '=':
												if($end - $position < 3
												|| !($r=sscanf(strtolower(substr($value, $position+1, 2)), '%x', $code)))
												{
													$error = 'the header specified an invalid encoded character';
													$error_position = $part['Position'] + $position + 1;
													break 4;
												}
												$decoded .= Chr($code);
												$position += 3;
												break;
											case '_':
												$decoded .= ' ';
												$position++;
												break;
											default:
												$decoded .= $value[$position];
												$position++;
												break;
										}
									}
									if(count($decoded_header)
									&& (!strcmp($decoded_header[$last = count($decoded_header)-1]['Encoding'], 'ASCII'))
									|| !strcmp($decoded_header[$last]['Encoding'], $encoding))
									{
										$decoded_header[$last]['Value'].= $decoded;
										$decoded_header[$last]['Encoding']= $encoding;
									}
									else
									{
										$decoded_header[]=array(
											'Value'=>$decoded,
											'Encoding'=>$encoding
										);
									}
								}
								break;
							case 'b':
								if($end>$start)
								{
									$decoded =& SRA_File::base64Decode($tmp = substr($value, $start, $end - $start));
									if(count($decoded_header)
									&& (!strcmp($decoded_header[$last = count($decoded_header)-1]['Encoding'], 'ASCII'))
									|| !strcmp($decoded_header[$last]['Encoding'], $encoding))
									{
										$decoded_header[$last]['Value'].= $decoded;
										$decoded_header[$last]['Encoding']= $encoding;
									}
									else
									{
										$decoded_header[]=array(
											'Value'=>$decoded,
											'Encoding'=>$encoding
										);
									}
								}
								break;
							default:
								$error = 'the header specified an unsupported encoding method';
								$error_position = $part['Position'] + $method;
								break 2;
						}
						$position = $end + 2;
					}
					if(strlen($error)==0)
						$part['Decoded']=$decoded_header;
				}
				if($this->decode_bodies
				|| $this->decode_headers)
				{
					switch($this->current_header)
					{
						case 'content-type:':
							$value = $part['Value'];
							$type = strtolower(trim(strtok($value, ';')));
							$parameters = trim(strtok(''));
							$this->headers['Type'] = $type;
							if($this->decode_headers)
							{
								$part['MainValue'] = $type;
								$part['Parameters'] = array();
							}
							if(!strcmp(strtok($type, '/'), 'multipart'))
							{
								$this->headers['Multipart'] = 1;
								while(strlen($parameters))
								{
									$parameter = strtolower(strtok($parameters, '='));
									$value = trim(strtok(';'));
									if(!strcmp($value[0], '"')
									&& !strcmp($value[strlen($value) - 1], '"'))
										$value = substr($value, 1, strlen($value) - 2);
									if($this->decode_headers)
										$part['Parameters'][$parameter] = $value;
									if(!strcmp($parameter, 'boundary'))
										$this->headers['Boundary'] = $value;
									$parameters = trim(strtok(''));
								}
								if(!IsSet($this->headers['Boundary']))
									return($this->SetPositionedError('multipart content-type header does not specify the boundary parameter', $part['Position']));
							}
							break;
						case 'content-transfer-encoding:':
							switch($this->headers['Encoding']=strtolower(trim($part['Value'])))
							{
								case 'quoted-printable':
									$this->headers['QuotedPrintable'] = 1;
									break;
								case '7bit':
								case '8bit':
									break;
								case 'base64':
									$this->headers['Base64']=1;
									break;
								default:
									return($this->SetPositionedError('decoding '.$this->headers['Encoding'].' encoded bodies is not yet supported', $part['Position']));
							}
							break;
					}
				}
				break;
			case 'BodyStart':
				if($this->decode_bodies
				&& IsSet($this->headers['Multipart']))
				{
					$this->body_parser_state = MIME_PARSER_BODY_START;
					$this->body_buffer = '';
					$this->body_buffer_position = 0;
				}
				break;
			case 'MessageEnd':
				if($this->decode_bodies
				&& IsSet($this->headers['Multipart'])
				&& $this->body_parser_state != MIME_PARSER_BODY_DONE)
					return($this->SetPositionedError('incomplete message body part', $part['Position']));
				break;
			case 'BodyData':
				if($this->decode_bodies)
				{
					if(strlen($this->body_buffer)==0)
					{
						$this->body_buffer = $part['Data'];
						$this->body_offset = $part['Position'];
					}
					else
						$this->body_buffer .= $part['Data'];
					if(IsSet($this->headers['Multipart']))
					{
						$boundary = '--'.$this->headers['Boundary'];
						switch($this->body_parser_state)
						{
							case MIME_PARSER_BODY_START:
								for($position = $this->body_buffer_position; ;)
								{
									if(GetType($line_break=strpos($this->body_buffer, $break="\r\n", $position))!='integer'
									&& GetType($line_break=strpos($this->body_buffer, $break="\n", $position))!='integer'
									&& GetType($line_break=strpos($this->body_buffer, $break="\r", $position))!='integer')
										return(1);
									$next = $line_break + strlen($break);
									if(!strcmp(substr($this->body_buffer, $position, $line_break - $position), $boundary))
									{
										$part=array(
											'Type'=>'StartPart',
											'Part'=>$this->headers['Boundary'],
											'Position'=>$this->body_offset + $next
										);
										$this->parts[]=$part;
										UnSet($this->body_parser);
										$this->body_parser = new mime_parser_class;
										$this->body_parser->decode_bodies = 1;
										$this->body_parser->decode_headers = $this->decode_headers;
										$this->body_parser->mbox = 0;
										$this->body_parser_state = MIME_PARSER_BODY_DATA;
										$this->body_buffer = substr($this->body_buffer, $next);
										$this->body_offset += $next;
										$this->body_buffer_position = 0;
										break;
									}
									else
										$position = $next;
								}
							case MIME_PARSER_BODY_DATA:
								for($position = $this->body_buffer_position; ;)
								{
									if(GetType($line_break=strpos($this->body_buffer, $break="\r\n", $position))!='integer'
									&& GetType($line_break=strpos($this->body_buffer, $break="\n", $position))!='integer'
									&& GetType($line_break=strpos($this->body_buffer, $break="\r", $position))!='integer')
									{
										if($position > 0)
										{
											if(!$this->body_parser->Parse(substr($this->body_buffer, 0, $position), 0))
												return($this->SetError($this->body_parser->error));
											if(!$this->QueueBodyParts())
												return(0);
										}
										$this->body_buffer = substr($this->body_buffer, $position);
										$this->body_buffer_position = 0;
										$this->body_offset += $position;
										return(1);
									}
									$next = $line_break + strlen($break);
									$line = substr($this->body_buffer, $position, $line_break - $position);
									if(!strcmp($line, $boundary))
									{
										if(!$this->body_parser->Parse(substr($this->body_buffer, 0, $position), 1))
											return($this->SetError($this->body_parser->error));
										if(!$this->QueueBodyParts())
											return(0);
										$part=array(
											'Type'=>'EndPart',
											'Part'=>$this->headers['Boundary'],
											'Position'=>$this->body_offset + $position
										);
										$this->parts[] = $part;
										$part=array(
											'Type'=>'StartPart',
											'Part'=>$this->headers['Boundary'],
											'Position'=>$this->body_offset + $next
										);
										$this->parts[] = $part;
										UnSet($this->body_parser);
										$this->body_parser = new mime_parser_class;
										$this->body_parser->decode_bodies = 1;
										$this->body_parser->decode_headers = $this->decode_headers;
										$this->body_parser->mbox = 0;
										$this->body_buffer = substr($this->body_buffer, $next);
										$this->body_buffer_position = 0;
										$this->body_offset += $next;
										$position=0;
										continue;
									}
									elseif(!strcmp($line, $boundary.'--'))
									{
										if(!$this->body_parser->Parse(substr($this->body_buffer, 0, $position), 1))
											return($this->SetError($this->body_parser->error));
										if(!$this->QueueBodyParts())
											return(0);
										$part=array(
											'Type'=>'EndPart',
											'Part'=>$this->headers['Boundary'],
											'Position'=>$this->body_offset + $position
										);
										$this->body_buffer = substr($this->body_buffer, $next);
										$this->body_buffer_position = 0;
										$this->body_offset += $next;
										$this->body_parser_state = MIME_PARSER_BODY_DONE;
										break 2;
									}
									$position = $next;
								}
								break;
							case MIME_PARSER_BODY_DONE:
								return(1);
							default:
								return($this->SetPositionedError($this->state.' is not a valid body parser state', $this->body_buffer_position));
						}
					}
					elseif(IsSet($this->headers['QuotedPrintable']))
					{
						for($end = strlen($this->body_buffer), $decoded = '', $position = $this->body_buffer_position; $position < $end; )
						{
							if(GetType($equal = strpos($this->body_buffer, '=', $position))!='integer')
							{
								$decoded .= substr($this->body_buffer, $position);
								$position = $end;
								break;
							}
							$next = $equal + 1;
							switch($end - $equal)
							{
								case 1:
									$decoded .= substr($this->body_buffer, $position, $equal - $position);
									$position = $equal;
									break 2;
								case 2:
									$decoded .= substr($this->body_buffer, $position, $equal - $position);
									if(!strcmp($this->body_buffer[$next],"\n"))
										$position = $end;
									else
										$position = $equal;
									break 2;
							}
							if(!strcmp(substr($this->body_buffer, $next, 2), $break="\r\n")
							|| !strcmp($this->body_buffer[$next], $break="\n")
							|| !strcmp($this->body_buffer[$next], $break="\r"))
							{
								$decoded .= substr($this->body_buffer, $position, $equal - $position);
								$position = $next + strlen($break);
								continue;
							}
							$decoded .= substr($this->body_buffer, $position, $equal - $position);
							if(!($r=sscanf(strtolower(substr($this->body_buffer, $next, 2)), '%x', $code)))
								return($this->SetPositionedError('the body specified an invalid quoted-printable encoded character', $this->body_offset + $next));
							$decoded .= Chr($code);
							$position = $equal + 3;
						}
						if(strlen($decoded)==0)
						{
							$this->body_buffer_position = $position;
							return(1);
						}
						$part['Data'] = $decoded;
						$this->body_buffer = substr($this->body_buffer, $position);
						$this->body_buffer_position = 0;
						$this->body_offset += $position;
					}
					elseif(IsSet($this->headers['Base64']))
					{
            $_decode = $this->body_buffer_position ? substr($this->body_buffer,$this->body_buffer_position) : $this->body_buffer;
						$part['Data'] =& SRA_File::base64Decode($_decode);
						$this->body_offset += strlen($this->body_buffer) - $this->body_buffer_position;
						$this->body_buffer_position = 0;
						$this->body_buffer = '';
					}
					else
					{
						$part['Data'] = substr($this->body_buffer, $this->body_buffer_position);
						$this->body_buffer_position = 0;
						$this->body_buffer = '';
					}
				}
				break;
		}
		$this->parts[]=$part;
		return(1);
	}

	Function DecodeStream($parameters, &$end_of_message, &$decoded)
	{
		$end_of_message = 1;
		$state = MIME_MESSAGE_START;
		for(;;)
		{
			if(!$this->GetPart($part, $end))
				return(0);
			if($end)
			{
				if(IsSet($parameters['File']))
				{
					$end_of_data = feof($this->file);
					if($end_of_data)
						break;
					$data = @fread($this->file, 8000);
					if(GetType($data)!='string')
						return($this->SetPHPError('could not read the message file', $php_errormsg));
					$end_of_data = feof($this->file);
				}
				else
				{
					$end_of_data=($this->position>=strlen($parameters['Data']));
					if($end_of_data)
						break;
					$data = substr($parameters['Data'], $this->position);
					$end_of_data = 1;
					$this->position = strlen($parameters['Data']);
				}
				if(!$this->Parse($data, $end_of_data))
					return(0);
				continue;
			}
			$type = $part['Type'];
			switch($state)
			{
				case MIME_MESSAGE_START:
					switch($type)
					{
						case 'MessageStart':
							$decoded=array(
								'Headers'=>array(),
								'Parts'=>array()
							);
							$end_of_message = 0;
							$state = MIME_MESSAGE_GET_HEADER_NAME;
							continue 3;
					}
					break;

				case MIME_MESSAGE_GET_HEADER_NAME:
					switch($type)
					{
						case 'HeaderName':
							$header = strtolower($part['Name']);
							$state = MIME_MESSAGE_GET_HEADER_VALUE;
							continue 3;
						case 'BodyStart':
							$state = MIME_MESSAGE_GET_BODY;
							$part_number = 0;
							continue 3;
					}
					break;

				case MIME_MESSAGE_GET_HEADER_VALUE:
					switch($type)
					{
						case 'HeaderValue':
							$value = trim($part['Value']);
							if(!IsSet($decoded['Headers'][$header]))
							{
								$h = 0;
								$decoded['Headers'][$header]=$value;
							}
							elseif(GetType($decoded['Headers'][$header])=='string')
							{
								$h = 1;
								$decoded['Headers'][$header]=array($decoded['Headers'][$header], $value);
							}
							else
							{
								$h = count($decoded['Headers'][$header]);
								$decoded['Headers'][$header][]=$value;
							}
							if(IsSet($part['Decoded'])
							&& (count($part['Decoded'])>1
							|| strcmp($part['Decoded'][0]['Encoding'],'ASCII')
							|| strcmp($value, trim($part['Decoded'][0]['Value']))))
							{
								$p=$part['Decoded'];
								$p[0]['Value']=ltrim($p[0]['Value']);
								$last=count($p)-1;
								$p[$last]['Value']=rtrim($p[$last]['Value']);
								$decoded['DecodedHeaders'][$header][$h]=$p;
							}
							$state = MIME_MESSAGE_GET_HEADER_NAME;
							continue 3;
					}
					break;

				case MIME_MESSAGE_GET_BODY:
					switch($type)
					{
						case 'BodyData':
							if(IsSet($parameters['SaveBody']))
							{
								if(!IsSet($decoded['BodyFile']))
								{
									$directory_separator=(defined('DIRECTORY_SEPARATOR') ? DIRECTORY_SEPARATOR : '/');
									$path = (strlen($parameters['SaveBody']) ? ($parameters['SaveBody'].(strcmp($parameters['SaveBody'][strlen($parameters['SaveBody'])-1], $directory_separator) ? $directory_separator : '')) : '').strval($this->body_part_number);
									if(!($this->body_file = fopen($path, 'wb')))
										return($this->SetPHPError('could not create file '.$path.' to save the message body part', $php_errormsg));
									$decoded['BodyFile'] = $path;
									$decoded['BodyPart'] = $this->body_part_number;
									$decoded['BodyLength'] = 0;
									$this->body_part_number++;
								}
								if(strlen($part['Data'])
								&& !fwrite($this->body_file, $part['Data']))
								{
									$this->SetPHPError('could not save the message body part to file '.$decoded['BodyFile'], $php_errormsg);
									fclose($this->body_file);
									@unlink($decoded['BodyFile']);
									return(0);
								}
							}
							elseif(IsSet($parameters['SkipBody']))
							{
								if(!IsSet($decoded['BodyPart']))
								{
									$decoded['BodyPart'] = $this->body_part_number;
									$decoded['BodyLength'] = 0;
									$this->body_part_number++;
								}
							}
							else
							{
								if(IsSet($decoded['Body']))
									$decoded['Body'].=$part['Data'];
								else
								{
									$decoded['Body']=$part['Data'];
									$decoded['BodyPart'] = $this->body_part_number;
									$decoded['BodyLength'] = 0;
									$this->body_part_number++;
								}
							}
							$decoded['BodyLength'] += strlen($part['Data']);
							continue 3;
						case 'StartPart':
							if(!$this->DecodeStream($parameters, $end_of_part, $decoded_part))
								return(0);
							$decoded['Parts'][$part_number]=$decoded_part;
							$part_number++;
							$state = MIME_MESSAGE_GET_BODY_PART;
							continue 3;
						case 'MessageEnd':
							if(IsSet($decoded['BodyFile']))
								fclose($this->body_file);
							return(1);
					}
					break;

				case MIME_MESSAGE_GET_BODY_PART:
					switch($type)
					{
						case 'EndPart':
							$state = MIME_MESSAGE_GET_BODY;
							continue 3;
					}
					break;
			}
			return($this->SetError('unexpected decoded message part type '.$type.' in state '.$state));
		}
		return(1);
	}


	/* Public functions */

	Function Parse($data, $end)
	{
		if(strlen($this->error))
			return(0);
		if($this->state==MIME_PARSER_END)
			return($this->SetError('the parser already reached the end'));
		$this->buffer .= $data;
		do
		{
			Unset($part);
			if(!$this->ParsePart($end, $part, $need_more_data))
				return(0);
			if(IsSet($part)
			&& !$this->DecodePart($part))
				return(0);
		}
		while(!$need_more_data
		&& $this->state!=MIME_PARSER_END);
		if($end
		&& $this->state!=MIME_PARSER_END)
			return($this->SetError('reached a premature end of data'));
		if($this->buffer_position>0)
		{
			$this->offset += $this->buffer_position;
			$this->buffer = substr($this->buffer, $this->buffer_position);
			$this->buffer_position = 0;
		}
		return(1);
	}

	Function ParseFile($file)
	{
		if(strlen($this->error))
			return(0);
		if(!($stream = @fopen($file, 'r')))
			return($this->SetPHPError('Could not open the file '.$file, $php_errormsg));
		for($end = 0;!$end;)
		{
			if(!($data = @fread($stream, 8000)))
			{
				$this->SetPHPError('Could not open the file '.$file, $php_errormsg);
				fclose($stream);
				return(0);
			}
			$end=feof($stream);
			if(!$this->Parse($data, $end))
			{
				fclose($stream);
				return(0);
			}
		}
		fclose($stream);
		return(1);
	}

	Function GetPart(&$part, &$end)
	{
		$end = ($this->part_position >= count($this->parts));
		if($end)
		{
			if($this->part_position)
			{
				$this->part_position = 0;
				$this->parts = array();
			}
		}
		else
		{
			$part = $this->parts[$this->part_position];
			$this->part_position ++;
		}
		return(1);
	}

/*
{metadocument}
	<function>
		<name>Decode</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Parse and decode message data and retrieve its structure.</purpose>
			<usage>Pass an array to the <argumentlink>
					<function>Decode</function>
					<argument>parameters</argument>
				</argumentlink>
				parameter to define whether the message data should be read and
				parsed from a file or a data string, as well additional parsing
				options. The <argumentlink>
					<function>Decode</function>
					<argument>decoded</argument>
				</argumentlink> returns the
				data structure of the parsed messages.</usage>
			<returnvalue>This function returns <booleanvalue>1</booleanvalue> if
				the specified message data is parsed successfully. Otherwise,
				check the variables <variablelink>error</variablelink> and
				<variablelink>error_position</variablelink> to determine what
				error occurred and the relevant message position.</returnvalue>
		</documentation>
		<argument>
			<name>parameters</name>
			<type>HASH</type>
			<documentation>
				<purpose>Associative array to specify parameters for the message
					data parsing and decoding operation. Here follows the list of
					supported parameters that should be used as indexes of the
					array:<paragraphbreak />
					<tt>File</tt><paragraphbreak />
					Name of the file from which the message data will be read. It
					may be the name of a file stream or a remote URL, as long as
					your PHP installation is configured to allow accessing remote
					files with the <tt>fopen()</tt> function.<paragraphbreak />
					<tt>Data</tt><paragraphbreak />
					String that specifies the message data. This should be used
					as alternative data source for passing data available in memory,
					like for instance messages stored in a database that was queried
					dynamically and the message data was fetched into a string
					variable.<paragraphbreak />
					<tt>SaveBody</tt><paragraphbreak />
					If this parameter is specified, the message body parts are saved
					to files. The path of the directory where the files are saved is
					defined by this parameter value. The information about the
					message body part structure is returned by the <argumentlink>
						<function>Decode</function>
						<argument>decoded</argument>
					</argumentlink> argument, but it just returns the body data part
					file name instead of the actual body data. It is recommended for
					retrieving messages larger than the available memory. The names
					of the body part files are numbers starting from
					<stringvalue>1</stringvalue>.<paragraphbreak />
					<tt>SkipBody</tt><paragraphbreak />
					If this parameter is specified, the message body parts are
					skipped. This means the information about the message body part
					structure is returned by the <argumentlink>
						<function>Decode</function>
						<argument>decoded</argument>
					</argumentlink> but it does not return any body data. It is
					recommended just for parsing messages without the need to
					retrieve the message body part data.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>decoded</name>
			<type>ARRAY</type>
			<out />
			<documentation>
				<purpose>Retrieve the structure of the parsed message headers and
					body data.<paragraphbreak />
					The argument is used to return by reference an array of message
					structure definitions. Each array entry refers to the structure
					of each message that is found and parsed successfully.<paragraphbreak />
					Each message entry consists of an associative array with several
					entries that describe the message structure. Here follows the
					list of message structure entries names and the meaning of the
					respective values:<paragraphbreak />
					<tt>Headers</tt><paragraphbreak />
					Associative array that returns the list of all the message
					headers. The array entries are the header names mapped to
					lower case, including the end colon. The array values are the
					respective header raw values without any start or trailing white
					spaces. Long header values split between multiple message lines
					are gathered in single string without line breaks. If an header
					with the same name appears more than once in the message, the
					respective value is an array with the values of all of the
					header occurrences.<paragraphbreak />
					<tt>DecodedHeaders</tt><paragraphbreak />
					Associative array that returns the list of all the encoded
					message headers when the
					<variablelink>decode_headers</variablelink> variable is set. The
					array entries are the header names mapped to lower case,
					including the end colon. The array values are also arrays that
					list only the occurrences of the header that originally were
					encoded. Each entry of the decoded header array contains more
					associative arrays that describe each part of the decoded
					header. Each of those associative arrays have an entry named
					<tt>Value</tt> that contains the decoded header part value, and
					another entry named <tt>Encoding</tt> that specifies the
					character set encoding of the value in upper case.<paragraphbreak />
					<tt>Parts</tt><paragraphbreak />
					If this message content type is multipart, this entry is an
					array that describes each of the parts contained in the message
					body. Each message part is described by an associative array
					with the same structure of a complete message
					definition.<paragraphbreak />
					<tt>Body</tt><paragraphbreak />
					String with the decoded data contained in the message body. If
					the <tt>SaveBody</tt> or <tt>SkipBody</tt> parameters are
					defined, the <tt>Body</tt> entry is not set.<paragraphbreak />
					<tt>BodyFile</tt><paragraphbreak />
					Name of the file to which the message body data was saved when
					the <tt>SaveBody</tt> parameter is defined.<paragraphbreak />
					<tt>BodyLength</tt><paragraphbreak />
					Length of the current decoded body part.<paragraphbreak />
					<tt>BodyPart</tt><paragraphbreak />
					Number of the current message body part.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function Decode($parameters, &$decoded)
	{
		if(IsSet($parameters['File']))
		{
			if(!($this->file = @fopen($parameters['File'], 'r')))
				return($this->SetPHPError('could not open the message file to decode '.$parameters['File'], $php_errormsg));
		}
		elseif(IsSet($parameters['Data']))
			$this->position = 0;
		else
			return($this->SetError('it was not specified a valid message to decode'));
		$decoded = array();
		for($message = 0; ($success = $this->DecodeStream($parameters, $end_of_message, $decoded_message)) && !$end_of_message; $message++)
			$decoded[$message]=$decoded_message;
		if(IsSet($parameters['File']))
			fclose($this->file);
		return($success);
	}
/*
{metadocument}
		</do>
	</function>
{/metadocument}
*/

};

/*

{metadocument}
</class>
{/metadocument}

*/

?>