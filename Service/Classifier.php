<?php

namespace Meynell\BayesianBundle\Service;

use Exception;

class Classifier
{
    protected $min_dev;
    protected $rob_s;
    protected $rob_x;
    protected $use_relevant;

	private $lexer;
	private $degenerator;

	private $token_data = NULL;

	const SPAM    = 'spam';
	const HAM     = 'ham';
	const LEARN   = 'learn';
	const UNLEARN = 'unlearn';

	const INTERNALS_TEXTS     = 'b8*texts';

	const TRAINER_CATEGORY_FAIL = 'TRAINER_CATEGORY_FAIL';

    public function __construct($lexer, $degenerator)
    {
        $this->lexer = $lexer;
        $this->degenerator = $degenerator;
    }

    public function tokenize($text)
    {
		# Get all tokens we want to rate
		# If the lexer failed $tokens will be a lexer error code or empty array

		return (array)$this->lexer->getTokens($text);
    }

	/**
	 * Classifies a text by calculating the "smamminess" of all tokens.
	 *
	 * @access public
	 * @param array $tokens list of tokens (and their count) found in text
	 * @param array $token_data list tokens in the format array('tokens' => array(token => count), 'degenerates' => array(token => array(degenerate => count))).
	 * @param integer $nb_ham total number of ham texts so the spam probability can be calculated in relation to it
	 * @param integer $nb_spam total number of spam texts so the spam probability can be calculated in relation to it
	 * @return float The rating between 0 (ham) and 1 (spam)
	 */
	public function classify($tokens, $token_data, $nb_ham, $nb_spam)
	{
		# Fetch all available data for the token set from the database
		$this->token_data = $token_data;

		# Calculate the spamminess and importance for each token (or a degenerated form of it)

		$word_count = array();
		$rating     = array();
		$importance = array();
		
		foreach($tokens as $word => $count) {
			$word_count[$word] = $count;
			
			# Although we only call this function only here ... let's do the
			# calculation stuff in a function to make this a bit less confusing ;-)
			$rating[$word] = $this->getProbability($word, $nb_ham, $nb_spam);
			
			$importance[$word] = abs(0.5 - $rating[$word]);
		}
		
		# Order by importance
		arsort($importance);
		reset($importance);
		
		# Get the most interesting tokens (use all if we have less than the given number)
		
		$relevant = array();
		
		for($i = 0; $i < $this->use_relevant; $i++) {
		
			if($tmp = each($importance)) {
				
				# Important tokens remain
				
				# If the token's rating is relevant enough, use it
				
				if(abs(0.5 - $rating[$tmp['key']]) > $this->min_dev) {
				
					# Tokens that appear more than once also count more than once
					
					for($x = 0, $l = $word_count[$tmp['key']]; $x < $l; $x++)
						array_push($relevant, $rating[$tmp['key']]);
						
				}
				
			}
			
			else {
				# We have less words as we want to use, so we
				# already use what we have and can break here
				break;
			}
			
		}
		
		# Calculate the spamminess of the text (thanks to Mr. Robinson ;-)
		# We set both hamminess and spamminess to 1 for the first multiplying
		$hamminess  = 1;
		$spamminess = 1;
		
		# Consider all relevant ratings
		foreach($relevant as $value) {
			$hamminess  *= (1.0 - $value);
			$spamminess *= $value;
		}
		
		# If no token was good for calculation, we really don't know how
		# to rate this text; so we assume a spam and ham probability of 0.5
		
		if($hamminess === 1 and $spamminess === 1) {
			$hamminess = 0.5;
			$spamminess = 0.5;
			$n = 1;
		}
		else {
			# Get the number of relevant ratings
			$n = count($relevant);
		}
		
		# Calculate the combined rating

		# The actual hamminess and spamminess
		$hamminess  = 1 - pow($hamminess,  (1 / $n));
		$spamminess = 1 - pow($spamminess, (1 / $n));
		
		# Calculate the combined indicator
		$probability = ($hamminess - $spamminess) / ($hamminess + $spamminess);
		
		# We want a value between 0 and 1, not between -1 and +1, so ...
		$probability = (1 + $probability) / 2;

		# Alea iacta est
		return $probability;
    }

	/**
	 * Learn a reference text
	 *
	 * @access public
	 * @param string $text
	 * @param const $category Either b8::SPAM or b8::HAM
	 * @return void
	 */
	public function learn($text, $category, $nb_ham, $nb_spam)
    {
		return $this->processText($text, $category, self::LEARN, $nb_ham, $nb_spam);
    }

	/**
	 * Unlearn a reference text
	 *
	 * @access public
	 * @param string $text
	 * @param const $category Either b8::SPAM or b8::HAM
	 * @return void
	 */
	public function unlearn($text, $category)
    {
		return $this->processText($text, $category, self::UNLEARN);
    }

    /*
	 * @param array $token_data list of tokens in storage in the format array('token' => array(count_ham => count, 'count_spam' => count)).
     */
    public function getMissingTokens($tokens, $token_data)
    {
		$missing_tokens = array();

		foreach($tokens as $token) {
			if(!isset($token_data[$token]))
				$missing_tokens[] = $token;
		}

        return $missing_tokens;
    }

    public function getDegenerates($missing_tokens)
    {
        $degenerates_list = array();

        # Generate a list of degenerated tokens for the missing tokens ...
        $degenerates = $this->degenerator->degenerate($missing_tokens);

        # ... and look them up
        foreach($degenerates as $token => $token_degenerates) {
            $degenerates_list = array_merge($degenerates_list, $token_degenerates);
        }

        return $degenerates_list;
    }

	/**
	 * Calculate the spamminess of a single token also considering "degenerated" versions
	 *
	 * @access private
	 * @param string $word
	 * @param string $texts_ham
	 * @param string $texts_spam
	 * @return void
	 */
	
	private function getProbability($word, $texts_ham, $texts_spam)
	{
		# Let's see what we have!
		
		if(isset($this->token_data['tokens'][$word]) === TRUE) {
			# The token is in the database, so we can use it's data as-is
			# and calculate the spamminess of this token directly
			return $this->calcProbability($this->token_data['tokens'][$word], $texts_ham, $texts_spam);
		}
		
		# The token was not found, so do we at least have similar words?
		
		if(isset($this->token_data['degenerates'][$word]) === TRUE) {
		
			# We found similar words, so calculate the spamminess for each one
			# and choose the most important one for the further calculation
			
			# The default rating is 0.5 simply saying nothing
			$rating = 0.5;
			
			foreach($this->token_data['degenerates'][$word] as $degenerate => $count) {
			
				# Calculate the rating of the current degenerated token
				$rating_tmp = $this->calcProbability($count, $texts_ham, $texts_spam);

				# Is it more important than the rating of another degenerated version?
				if(abs(0.5 - $rating_tmp) > abs(0.5 - $rating))
					$rating = $rating_tmp;
				
			}
			
			return $rating;
			
		}
		
		else {
			# The token is really unknown, so choose the default rating
			# for completely unknown tokens. This strips down to the
			# robX parameter so we can cheap out the freaky math ;-)
			return $this->rob_x;
		}
	}

	/**
	 * Do the actual spamminess calculation of a single token
	 *
	 * @access private
	 * @param array $data
	 * @param string $texts_ham
	 * @param string $texts_spam
	 * @return void
	 */
	
	private function calcProbability($data, $texts_ham, $texts_spam)
	{
		# Calculate the basic probability as proposed by Mr. Graham
		
		# But: consider the number of ham and spam texts saved instead of the
		# number of entries where the token appeared to calculate a relative
		# spamminess because we count tokens appearing multiple times not just
		# once but as often as they appear in the learned texts.
		
		$rel_ham = $data['count_ham'];
		$rel_spam = $data['count_spam'];
		
		if($texts_ham > 0)
			$rel_ham = $data['count_ham'] / $texts_ham;
			
		if($texts_spam > 0)
			$rel_spam = $data['count_spam'] / $texts_spam;
			
        $rating = 0;
        $rel_ham_rel_spam = $rel_ham + $rel_spam;
        if ($rel_ham_rel_spam > 0) {
		    $rating = $rel_spam / $rel_ham_rel_spam;
        }

		# Calculate the better probability proposed by Mr. Robinson
		$all = $data['count_ham'] + $data['count_spam'];
		return (($this->rob_s * $this->rob_x) + ($all * $rating)) / ($this->rob_s + $all);
		
	}

	/**
	 * Does the actual interaction with the storage backend for learning or unlearning texts
	 *
	 * @access private
	 * @param string $text
	 * @param const $category Either b8::SPAM or b8::HAM
	 * @param const $action Either b8::LEARN or b8::UNLEARN
	 * @return void
	 */
	
	private function processText($text, $category, $action, $nb_ham, $nb_spam)
	{
		# Look if the request is okay
		if($this->checkCategory($category) === FALSE)
			return self::TRAINER_CATEGORY_FAIL;

		# Get all tokens from $text
		$tokens = $this->lexer->getTokens($text);
		
		# Check if the lexer failed
		# (if so, $tokens will be a lexer error code, if not, $tokens will be an array)
		if(!is_array($tokens)) {
			return $tokens;
        }
		
		# Pass the tokens and what to do with it to the storage backend
		return $this->process_text($tokens, $category, $action, $nb_ham, $nb_spam);
	}

	/**
	 * Check the validity of the category of a request
	 *
	 * @access private
	 * @param string $category
	 * @return void
	 */
	
	private function checkCategory($category)
	{
		return $category === self::HAM or $category === self::SPAM;
	}

    private function get_internals()
    {
        return array();
    }

	/**
	 * Stores or deletes a list of tokens from the given category.
	 *
	 * @access public
	 * @param array $tokens
	 * @param const $category Either b8::HAM or b8::SPAM
	 * @param const $action Either b8::LEARN or b8::UNLEARN
	 * @return void
	 */
	
	public function process_text($tokens, $token_data, $category, $action)
	{
		# Process all tokens to learn/unlearn
		
		foreach($tokens as $token => $count) {
		
			if(isset($token_data[$token])) {
			
				# We already have this token, so update it's data
				
				# Get the existing data
				$count_ham  = $token_data[$token]['count_ham'];
				$count_spam = $token_data[$token]['count_spam'];
				
				# Increase or decrease the right counter
				
				if($action === self::LEARN) {
					if($category === self::HAM) {
						$count_ham += $count;
                    }
					elseif($category === self::SPAM) {
						$count_spam += $count;
                    }
				}
				
				elseif($action == self::UNLEARN) {
					if($category === self::HAM)
						$count_ham -= $count;
					elseif($category === self::SPAM)
						$count_spam -= $count;
				}
				
				# We don't want to have negative values
				
				if($count_ham < 0) {
					$count_ham = 0;
                }
				
				if($count_spam < 0) {
					$count_spam = 0;
                }

				$token_data[$token]['count_ham'] = $count_ham;
				$token_data[$token]['count_spam'] = $count_spam;
			}

			else {
			
				# We don't have the token. If we unlearn a text, we can't delete it
				# as we don't have it anyway, so just do something if we learn a text
				
				if($action === self::LEARN) {

					if($category === self::HAM) {
                        $token_data[$token]['count_ham'] = $count;
                        $token_data[$token]['count_spam'] = 0;
                    }
					elseif($category === self::SPAM) {
                        $token_data[$token]['count_ham'] = 0;
                        $token_data[$token]['count_spam'] = $count;
                    }
				}
			}
		}
		
		# Now, all token have been processed, so let's update the right text
		
        $nb_ham = isset($token_data[self::INTERNALS_TEXTS]['count_ham']) ? $token_data[self::INTERNALS_TEXTS]['count_ham'] : 0;
        $nb_spam = isset($token_data[self::INTERNALS_TEXTS]['count_spam']) ? $token_data[self::INTERNALS_TEXTS]['count_spam'] : 0;

		if($action === self::LEARN) {
			if($category === self::HAM)
				$nb_ham++;
			elseif($category === self::SPAM)
				$nb_spam++;
		}
		elseif($action == self::UNLEARN) {
			if($category === self::HAM) {
				if($nb_ham > 0)
					$nb_ham--;
			}
			elseif($category === self::SPAM) {
				if($nb_spam > 0)
					$nb_spam--;
			}
		}

        $token_data[self::INTERNALS_TEXTS]['count_ham'] = $nb_ham;
        $token_data[self::INTERNALS_TEXTS]['count_spam'] = $nb_spam;

        return $token_data;
	}

    public function setMinDev($min_dev)
    {
        $this->min_dev = (float)$min_dev;
    }

    public function getMinDev()
    {
        return $this->min_dev;
    }

    public function setRobS($rob_s)
    {
        $this->rob_s = (float)$rob_s;
    }

    public function getRobS()
    {
        return $this->rob_s;
    }

    public function setRobX($rob_x)
    {
        $this->rob_x = (float)$rob_x;
    }

    public function getRobX()
    {
        return $this->rob_x;
    }

    public function setUseRelevant($use_relevant)
    {
        $this->use_relevant = (int)$use_relevant;
    }

    public function getUseRelevant()
    {
        return $this->use_relevant;
    }
}
