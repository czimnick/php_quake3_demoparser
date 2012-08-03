<!DOCTYPE html>
<html>
	<head>
		<title>Demoparser Test</title>
		<style type="text/css">
			pre, body { font-family: "Courier New"; font-size: 10pt; }
		</style>
	</head>
	<body>
		<form action="webexample.php" method="post" enctype="multipart/form-data">
			<a href="https://github.com/czimnick/php_quake3_demoparser">https://github.com/czimnick/php_quake3_demoparser</a><br /><br />
			DemoFile: <input name="file" type="file" /><br />
			<input name="btnUpload" type="submit" value="Upload" />
			<br />
			<hr />
		</form>
		<?php
			require_once("Q3/DemoParser.php");

			function createKeyValue(&$configString) {
				// TODO: fix it to safe code...
				// quick&dirty no error handling...
				$tmp = array();
				$arr = explode("\\", $configString);
				for($i = 0; $i<count($arr); $i++) {
					$check = trim($arr[$i]);
					if(empty($check))
						continue;

					$tmp[$arr[$i]] = $arr[$i+1];
					$i++;
				}
				return $tmp;
			}

			if(isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
				// some more in file error check...
				if(substr($_FILES['file']['name'], strlen($_FILES['file']['name'])-5) == "dm_68") {
					try {
						$parser = new Q3_DemoParser($_FILES['file']['tmp_name']);

						while( ($state = $parser->nextFrame()) !== false) {
							switch($state->PacketType) {
								case Q3_DEMOPARSER_SVC_GAMESTATE:
									// if this more than once a mapchange in demo was triggered...
									// currentGameState finished...
									$config = $parser->currentGameState->ConfigStrings;
									foreach($config as $k => $v) {
										if($k == 0 || $k == 1 || $k >= 544 && $k < 608)
											$config[$k] = createKeyValue($config[$k]);
									}

									echo "<b>GameVersion:</b> ".$config[20]."<br />";
									echo "<b>CurrentPlayerNum:</b> ".$parser->currentGameState->ClientNum."<br />";
									echo "<h3>PlayerOfDemo:</h3><br />";
									echo "<pre>";
									print_r($config[544+$parser->currentGameState->ClientNum]); // player that has recorded this demo
									echo "</pre><br /><br />";


									echo "<h3>ConfigStrings:</h3><br /><pre>";
									print_r($config);
									echo "</pre><br /><br />";
									break;

								case Q3_DEMOPARSER_SVC_SERVERCOMMAND:
									echo "<h3>ServerCommand received:</h3><br />";
									echo "<pre>";
									print_r($state->Packet); // class Q3_ServerCommand
									echo "</pre>";
									break;

								case Q3_DEMOPARSER_SVC_SNAPSHOT:
									//echo "Snapshot received (ServerTime: ".$state->Packet->ServerTime.")...<br />";
									// $state->Packet; // class Q3_Snapshot
									break;
							}
						}
					}catch(Q3_Exception $ex) {
						echo "ParserException: ".$ex->getMessage()."<br />";
					}
				}

				@unlink($_FILES['file']['tmp_name']);
			}
		?>
	</body>
</html>
