BayesianBundle
==============

Bayesian Classifier for Symfony2 Based on the [b8 Library](http://nasauber.de/opensource/b8/).

BayesianBundle is a classification tool intended to identify documents of interest. The library is exposed as a service so that it's available anywhere in your application and tells you whether a text is of value to you or not, using statistical analysis. 

The classifier is given a text and it returns a value between 0 and 1, saying it's ham ("valuable") when it's near 0 and saying it's spam ("worthless") when it's near 1. In order to do this, the classifier first has to learn what is valuable and what is not. Since the library is explicitly told what a text actually worth (valuable/not valuable) it is able to quickly learn and improve over time.
