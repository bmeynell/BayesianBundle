<?php

namespace Meynell\BayesianBundle\Service\Classifier\Lexer;

use Exception;

class StringLexer
{
	const LEXER_TEXT_NOT_STRING = 'LEXER_TEXT_NOT_STRING';
	const LEXER_TEXT_EMPTY      = 'LEXER_TEXT_EMPTY';

    protected $min_size;
    protected $max_size;
    protected $allow_numbers;
    protected $get_uris;
    protected $get_html;
    protected $get_bbcode;

	private $_tokens         = NULL;
	private $_processed_text = NULL;

	# The regular expressions we use to split the text to tokens
	public $regexp = array(
		'raw_split' => '/[\s,\.\/"\:;\|<>\-_\[\]{}\+=\)\(\*\&\^%]+/',
		'ip'        => '/([A-Za-z0-9\_\-\.]+)/',
		'uris'      => '/([A-Za-z0-9\_\-]*\.[A-Za-z0-9\_\-\.]+)/',
		'html'      => '/(<.+?>)/',
		'bbcode'    => '/(\[.+?\])/',
		'tagname'   => '/(.+?)\s/',
		'numbers'   => '/^[0-9]+$/'
	);

	/**
	 * Splits a text to tokens.
	 *
	 * @access public
	 * @param string $text
	 * @return mixed Returns a list of tokens or an error code
	 */
	
	public function getTokens($text)
	{
		# Check if we actually have a string ...
		if(is_string($text) === FALSE) {
			return self::LEXER_TEXT_NOT_STRING;
        }
		
		# ... and if it's empty
		if(empty($text) === TRUE) {
			return self::LEXER_TEXT_EMPTY;
        }
		
		# Re-convert the text to the original characters coded in UTF-8, as
		# they have been coded in html entities during the post process
		$this->_processed_text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		
		# Reset the token list
		$this->_tokens = array();
		
		if($this->get_uris === TRUE) {
			# Get URIs
			$this->_get_uris($this->_processed_text);
		}
		
		if($this->get_html === TRUE) {
			# Get HTML
			$this->_get_markup($this->_processed_text, $this->regexp['html']);
		}
		
		if($this->get_bbcode === TRUE) {
			# Get BBCode
			$this->_get_markup($this->_processed_text, $this->regexp['bbcode']);
		}
		
		# We always want to do a raw split of the (remaining) text, so:
		$this->_raw_split($this->_processed_text);
		
		# Be sure not to return an empty array
		if(count($this->_tokens) == 0)
			$this->_tokens['b8*no_tokens'] = 1;
		
		# Return a list of all found tokens
		return $this->_tokens;
    }

	/**
	 * Validates a token.
	 *
	 * @access private
	 * @param string $token The token string.
	 * @return boolean Returns TRUE if the token is valid, otherwise returns FALSE.
	 */
	
	private function _is_valid($token)
	{
	
		# Just to be sure that the token's name won't collide with b8's internal variables
		if(substr($token, 0, 3) == 'b8*')
			return FALSE;
			
		# Validate the size of the token
		
		$len = strlen($token);
		
		if($len < $this->min_size or $len > $this->max_size)
			return FALSE;
		
		# We may want to exclude pure numbers
		if($this->allow_numbers === FALSE) {
			if(preg_match($this->regexp['numbers'], $token) > 0)
				return FALSE;
		}
		
		# Token is okay
		return TRUE;
		
	}
	
	/**
	 * Checks the validity of a token and adds it to the token list if it's valid.
	 *
	 * @access private
	 * @param string $token
	 * @param bool $remove When set to TRUE, the string given in $word_to_remove is removed from the text passed to the lexer.
	 * @param string $word_to_remove
	 * @return void
	 */
	
	private function _add_token($token, $remove, $word_to_remove)
	{
	
		# Check the validity of the token
		if($this->_is_valid($token) === FALSE)
			return;
		
		# Add it to the list or increase it's counter
		if(isset($this->_tokens[$token]) === FALSE)
			$this->_tokens[$token] = 1;
		else
			$this->_tokens[$token] += 1;
			
		# If requested, remove the word or it's original version from the text
		if($remove === TRUE)
			$this->_processed_text = str_replace($word_to_remove, '', $this->_processed_text);
			
	}
	
	/**
	 * Gets URIs.
	 *
	 * @access private
	 * @param string $text
	 * @return void
	 */
	
	private function _get_uris($text)
	{
	
		# Find URIs
		preg_match_all($this->regexp['uris'], $text, $raw_tokens);
		
		foreach($raw_tokens[1] as $word) {
		
			# Remove a possible trailing dot
			$word = rtrim($word, '.');
			
			# Try to add the found tokens to the list
			$this->_add_token($word, TRUE, $word);
			
			# Also process the parts of the found URIs
			$this->_raw_split($word);
			
		}
		
	}
	
	/**
	 * Gets HTML or BBCode markup, depending on the regexp used.
	 *
	 * @access private
	 * @param string $text
	 * @param string $regexp
	 * @return void
	 */
	
	private function _get_markup($text, $regexp)
	{
	
		# Search for the markup
		preg_match_all($regexp, $text, $raw_tokens);
		
		foreach($raw_tokens[1] as $word) {
		
			$actual_word = $word;
			
			# If the tag has parameters, just use the tag itself
			if(strpos($word, ' ') !== FALSE) {
				preg_match($this->regexp['tagname'], $word, $match);
				$actual_word = $match[1];
				$word = "$actual_word..." . substr($word, -1);
			}
			
			# Try to add the found tokens to the list
			$this->_add_token($word, TRUE, $actual_word);
			
		}
		
	}
	
	/**
	 * Does a raw split.
	 *
	 * @access private
	 * @param string $text
	 * @return void
	 */
	
	private function _raw_split($text)
	{
		foreach(preg_split($this->regexp['raw_split'], $text) as $word) {
			# Check the word and add it to the token list if it's valid
			$this->_add_token($word, FALSE, NULL);
		}
	}

    public function setMinSize($min_size)
    {
        $this->min_size = (int)$min_size;
    }

    public function getMinSize()
    {
        return $this->min_size;
    }

    public function setMaxSize($max_size)
    {
        $this->max_size = (int)$max_size;
    }

    public function getMaxSize()
    {
        return $this->max_size;
    }

    public function setAllowNumbers($allow_numbers)
    {
        $this->allow_numbers = (bool)$allow_numbers;
    }

    public function getAllowNumbers()
    {
        return $this->allow_numbers;
    }

    public function setGetUris($get_uris)
    {
        $this->get_uris = (bool)$get_uris;
    }

    public function getGetUris()
    {
        return $this->get_uris;
    }

    public function setGetHtml($get_html)
    {
        $this->get_html = (bool)$get_html;
    }

    public function getGetHtml()
    {
        return $this->get_html;
    }

    public function setGetBbcode($get_bbcode)
    {
        $this->get_bbcode = (bool)$get_bbcode;
    }

    public function getGetBbcode()
    {
        return $this->get_bbcode;
    }
}
