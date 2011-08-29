#Requirements:

* PHP 5
* GraphViz

#Usage:

	$c2g = new Class2GV(*<path_to_graphviz_tool>*);
	$c2g->setVariables(false); //to don't show variables
	$c2g->convert(*<path_to_class_file>*);
