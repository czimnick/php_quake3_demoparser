<?php
	// The parser only supports reading the header(gamestate)
	// first verion untestet !!! (experimental)
	$parser = new Q3_DemoParser("test.dm_68");
	print_r($parser->gameState->ConfigStrings);
