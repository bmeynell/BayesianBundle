BayesianBundle
==============

Bayesian Classifier for Symfony2 Based on the [b8 Library](http://nasauber.de/opensource/b8/).

BayesianBundle is a classification tool intended to identify documents of interest. The library is exposed as a service so that it's available anywhere in your application and tells you whether a text is of value to you or not, using statistical analysis. 

The classifier is given a text and it returns a value between 0 and 1, saying it's ham ("valuable") when it's near 0 and saying it's spam ("worthless") when it's near 1. In order to do this, the classifier first has to learn what is valuable and what is not. Since the library is explicitly told what a text actually worth (valuable/not valuable) it is able to quickly learn and improve over time.

#### Configuration

In you're applications `app/config/config.yml` file, setup a sensible default configuration:

```yaml
meynell_bayesian:                                                                  
    use_relevant: 15                                                               
    min_dev: 0.2                                                                   
    rob_s: 0.3                                                                     
    rob_x: 0.5                                                                     
    lexer:                                                                         
        min_size: 3                                                                
        max_size: 30                                                               
        allow_numbers: false                                                       
        get_uris: true                                                             
        get_html: false                                                            
        get_bbcode: false                                                          
    degenerator:                                                                   
        multibyte: true                                                            
        encoding: 'UTF-8'
```

#### Services

The bundle exposes the following services:

```bash
meynell_bayesian.classifier                     container Meynell\BayesianBundle\Service\Classifier
meynell_bayesian.string_degenerator             container Meynell\BayesianBundle\Service\Classifier\Degenerator\StringDegenerator
meynell_bayesian.string_lexer                   container Meynell\BayesianBundle\Service\Classifier\Lexer\StringLexer
```

The most useful of which is meynell_bayesian.classifier which contains the following public methods:

  * tokenize($text)
  * classify($tokens, $token_data, $nb_ham, $nb_spam)
  * learn()
  * unlearn()

#### Workflow

$tokens_in_text = $this->tokenize($text);
$token_data = /// return persisted tokens that exist in db and are in text
$missing_tokens = $this->getMissingTokens($tokens_in_text, $token_data); // returns tokens you do not yet have persisted
if (count($missing_tokens)) {
    $degenerates = $this->getDegenerates($missing_tokens);
    $degenerates_list = // lookup $degenerates in db ...
    $token_data = array_merge($token_data, $degenerates_list);
}
$token_data = $bc->get($tokens, $token_data);
$score = $bc->classify($tokens_in_text, $token_data, $nb_ham, $nb_spam);

#### Notes

  * It's up to you to store the total number of documents marked ham or spam.
  * It's up to you to create your own persistence layer. This bundle only performs the needed computations.
