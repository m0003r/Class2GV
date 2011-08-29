#Requirements:

* PHP 5
* GraphViz

#Usage:

	$c2g = new Class2GV("C:\\Program Files\\GraphViz\\bin\\"); //need trailing slash!
	$c2g->setVariables(false); //to don't show variables, default true
	$c2g->convert("someclass.php", ["neato"]); //default tool is 'dot'
