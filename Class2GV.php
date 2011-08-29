<?php

class Class2GV {

    private $toolPath;
    private $fileName;
    private $showVariables;

    private $classCode;
    private $methodsList;
    private $methodsType;
    private $variableList;
    private $moduleName;
    private $graphData;

    public function __construct($toolPath)
    {
        $this->toolPath = $toolPath;
        $this->showVariables = true;
    }

    public function convert($phpClassFile, $tool = 'dot')
    {
        if (!file_exists($phpClassFile))
            throw new Exception("File not found");

        $this->fileName = $phpClassFile;
        $this->moduleName = basename($this->fileName, ".php");
		$this->tool = $tool;

        $this->parseClass();
        $this->makeGraphData();

        $svgFile = $this->createSVG();
        return $svgFile;
    }

    public function showVariables($showVariables)
    {
        $this->showVariables = $showVariables;
    }

    private function parseClass()
    {
        $this->classCode = file_get_contents($this->fileName);

        $this->decomposeClass();
    }

    private function makeGraphData()
    {
        $this->graphData = array();
        foreach ($this->methodsList as $method => $methodCode)
        {
            $calls = $methodCode;
            $this->graphData[$method] = $calls;
        }
    }

    private function findOpenMethodBrace()
    {
        do
        {
            $token = next($this->tokens);
        } while ($token != '{');
    }

    private function isToken($token, $tokenId)
    {
        if (!is_array($token))
            return false;
        if (is_array($tokenId))
            return (in_array($token[0], $tokenId));
        else
            return ($token[0] == $tokenId);
    }

    private function parseMethod($methodName)
    {
        $braceBalance = 0;
        $findCallStatus = 0;
        $token = current($this->tokens);
        do
        {
            if ($this->isToken($token, T_WHITESPACE))
            {
                $token = next($this->tokens);
                continue;
            }

            if ($token == "{")
                $braceBalance++;
            if ($this->isToken($token, array(T_DOLLAR_OPEN_CURLY_BRACES, T_CURLY_OPEN)))
                $braceBalance++;
            if ($token == "}")
                $braceBalance--;

            if (($findCallStatus == 0) && $this->isToken($token, T_VARIABLE) && ($token[1] == '$this'))
                $findCallStatus = 1;
            elseif (($findCallStatus == 1) && $this->isToken($token, T_OBJECT_OPERATOR))
                $findCallStatus = 2;
            elseif (($findCallStatus == 2) && $this->isToken($token, T_STRING))
            {
                $findCallStatus = 3;
                $methodOrVariable = $token[1];
            }
            elseif ($findCallStatus == 3)
            {
                $findCallStatus = 0;
                if ($token == "(")
				{
                    $this->methodsList[$methodName][] = $methodOrVariable;
					if (!isset($this->methodsType[$methodOrVariable]))
						$this->methodsType[$methodOrVariable] = "inherited";
				}
                else
                {
                    if (!isset($this->variableList[$methodOrVariable]))
                        $this->variableList[$methodOrVariable] = array();
                    if (!in_array($methodName, $this->variableList[$methodOrVariable]))
                        $this->variableList[$methodOrVariable][] = $methodName;
                }
            }
            else
                $findCallStatus = 0;

            $token = next($this->tokens);
        } while (($braceBalance > 0) && ($token));

        if ($braceBalance != 0)
            throw new Exception("Bad brace balance!");
    }

    private function getMethod($methodType)
    {
        do
        {
            $token = next($this->tokens);
        } while ($this->isToken($token, T_WHITESPACE));

        $methodName = $token[1];
        $this->methodsList[$methodName] = array();
        $this->methodsType[$methodName] = $methodType;

        $this->findOpenMethodBrace();
        $this->parseMethod($methodName);
    }

    private function decomposeClass()
    {
        $this->methodsType = array();
        $this->variableList = array();

        $this->tokens = token_get_all($this->classCode);

        $nextMethodType = false;
        do
        {
            $token = current($this->tokens);
            if ($this->isToken($token, array(T_PRIVATE, T_PROTECTED, T_PUBLIC)))
                $nextMethodType = $token[1];
            if ($this->isToken($token, T_FUNCTION))
            {
                $this->getMethod($nextMethodType);
                $nextMethodType = false;
            }
        } while (next($this->tokens));
    }

    private function setupNodeShapes()
    {
        $privateMethods = $protectedMethods = $publicMethods = $inheritedMethods = array();
        foreach ($this->methodsType as $methodName => $methodType)
        {
            $arrayName = $methodType . "Methods";
            $methodName = '"'.$methodName.'()"';
            array_push($$arrayName, $methodName);
        }

        if (!empty($privateMethods))
            $this->gv .= "  node [shape=ellipse,style=filled,color=lightpink]; " . implode("; ", $privateMethods) . ";\n";

        if (!empty($protectedMethods))
            $this->gv .= "  node [shape=diamond,style=filled,color=lightyellow]; " . implode("; ", $protectedMethods) . ";\n";

        if (!empty($publicMethods))
            $this->gv .= "  node [shape=box,style=filled,color=lightgreen]; " . implode("; ", $publicMethods) . ";\n";
			
        if (!empty($inheritedMethods))
            $this->gv .= "  node [shape=box,style=filled,color=lightblue]; " . implode("; ", $inheritedMethods) . ";\n";

		if ($this->showVariables)
			if (!empty($this->variableList))
				$this->gv .= "  node [shape=box,style=filled,color=lightgrey,fontsize=9]; " . implode("; ", array_keys($this->variableList)) . ";\n";
    }

    private function formatNodes()
    {
        foreach ($this->graphData as $method => $calls)
            foreach ($calls as $call)
                $this->gv .= "  \"$method()\"->\"$call()\"\n";
    }

    private function formatVariables()
    {
        foreach ($this->variableList as $variable => $calls)
            foreach ($calls as $call)
                $this->gv .= "  \"$call()\"->$variable\n";
    }

    private function makeGV()
    {
        $graphTitle = str_replace(".", "_", $this->moduleName);
        $this->gv = "digraph $graphTitle {\n";

        $this->setupNodeShapes();
        $this->formatNodes();
        if ($this->showVariables)
            $this->formatVariables();

        $this->gv .= "  fontsize=12;\n  overlap=false;\n}";
    }

    private function createSVG()
    {
        $svgFile = $this->moduleName . ".svg";
        $graphFile = $this->moduleName . ".gv";

        $this->makeGV();
        file_put_contents($graphFile, $this->gv);

        $command = '"' . $this->toolPath . $this->tool . "\" -Tsvg -o$svgFile $graphFile";
        header("X-Render-Command: $command");
        system($command);

        return $svgFile;
    }

};