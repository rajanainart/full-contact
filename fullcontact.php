<?php

class CsvFile
{
	//list of fields to be considered in the uploaded csv file. order of the fields are mandatory. field id is taken from the same array
	static $FIELDS = [
						"111111" => "", "firstname" => "16", "lastname" => "17", "email" => "", "domain" => "19", 
						"city" => "22", "state" => "23", "country" => "24", "address" => "21", "phone" => "25", 
						"sender" => "12", "senderemail" => "13", "company" => "15", "job" => "14", "subject" => "26", 
						"field1" => "28", "field2" => "29", "field3" => "30", "field4" => "31", "field5" => "32",
						"body" => "27"
				  	 ];
	var $FileName;
	var $HasHeaders;
	var $Errors;

	private $file;

	function __construct($file, $hasHeaders)
	{
		$this->FileName   = $file;
		$this->HasHeaders = $hasHeaders;
		$this->file 	  = fopen($this->FileName, "r");
		$this->Errors	  = [];
	}

	function __destruct()
	{
		fclose($this->file);
	}

	//compiles all the rows of uploaded csv as an array 
	function GetAllRows()
	{
		$rows   = [];
		$rowIdx = 0;
		while (!feof($this->file))
		{
			$actual = fgetcsv($this->file);
			$row    = array();
			
			if ($this->HasHeaders && $rowIdx == 0)
			{
				$rowIdx++;
				continue;
			}
			
			$colIdx = 0;
			foreach (CsvFile::$FIELDS as $key => $val)
				$row[$key] = $actual[$colIdx++];
			
			if ($row["firstname"] == "" || $row["email"] == "")
				$this->Errors[count($this->Errors)] = $rowIdx;
			else
				$rows[$rowIdx-1] = $row;
			$rowIdx++;
		}
		return $rows;
	}

	function GetRecords($fields)
	{
		$rows   = [];
		$rowIdx = 0;
		while (!feof($this->file))
		{
			$actual = fgetcsv($this->file);
			$row    = array();
				
			if ($this->HasHeaders && $rowIdx == 0)
			{
				$rowIdx++;
				continue;
			}
			
			foreach ($fields as $colIdx => $field)
			{
				if ($field != "")
					$row[$field] = $actual[$colIdx];
			}
			
			$rows[$rowIdx++] = $row;
		}
		return $rows;
	}
}

class RestClient
{
	static $BASE_URL = "http://api.fullcontact.com/v2/person.json?email=";
	static $HEADERS  = [
			"Referer" 	 	  => "https://mail.google.com/mail/u/0/",
			"User-Agent" 	  => "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.97 Safari/537.36",
			//"Accept-Encoding" => "gzip, deflate, sdch",
			"Accept-Language" => "en-US,en;q=0.8",
			"Connection" 	  => "keep-alive",
			"Content-Type" 	  => "application/json; charset=UTF-8",
			"X-FullContact-Version" => "2.0.5"
	];
	static $AUTH_KEYS = [
			["X-FullContact-AccessToken" => "access-token1", "active" => "1"],//mine
			["X-FullContact-AccessToken" => "access-token2", "active" => "1"],
			["X-FullContact-AccessToken" => "access-token3", "active" => "1"],
			["X-FullContact-AccessToken" => "access-token4", "active" => "1"],
			["X-FullContact-AccessToken" => "access-token5", "active" => "1"] //mine			
	];
	static $CurrentAuth = -1;
	
	private $Url;
	var $EmailList;
	
	function __construct($emailList)
	{
		$this->EmailList = $emailList;
	}
	
	private function SetCurrentAuth()
	{
		RestClient::$CurrentAuth++;
		if (RestClient::$CurrentAuth >= count(RestClient::$AUTH_KEYS))
			RestClient::$CurrentAuth = 0;
	}
	
	function ProcessEmailList()
	{
		$actualOutput = [];
		foreach ($this->EmailList as $key => $record)
		{
			try 
			{
				$this->Url = RestClient::$BASE_URL.$record["email"];
				$result    = $this->MakeApiCall();
				$profiles  = $this->GetData($result, "socialProfiles");
			
				if (array_key_exists("app_http_code", $result) == "403")
				{
					$rest     = new RestClient($record["email"]);
					$result   = $this->MakeApiCall();
					$profiles = $this->GetData($result, "socialProfiles");
				}
				$list = '';
				if (array_key_exists("custom_error1", $result) && array_key_exists("custom_error1", $result))
					$list = $list.$result["custom_error1"]."-".$result["custom_error2"];
				else
				{
					foreach($profiles as $p => $profile)
						$list = $list.$profile["typeName"]."[".$profile["url"]."],";
				}
				$actualOutput[count($actualOutput)] = [ "email" => $record["email"], "profiles" => $list ];
			}
			catch (Exception $e)
			{
				$actualOutput[count($actualOutput)] = [ "custom_error1" => "exception", "custom_error2" => $e->getMessage() ];
			}
		}
		return $actualOutput;
	}
	
	private function MakeApiCall()
	{
		$this->SetCurrentAuth();
		$auth    = RestClient::$AUTH_KEYS[RestClient::$CurrentAuth];
		$headers = [];
		foreach (RestClient::$HEADERS as $key => $value)
			$headers[count($headers)] = $key.": ".$value;
		//$headers[count($headers)] = "Cookie: ".$auth["Cookie"];
		$headers[count($headers)] = "X-FullContact-AccessToken: ".$auth["X-FullContact-AccessToken"];
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL			 , $this->Url);
		curl_setopt($curl, CURLOPT_HTTPGET		 , true);
		curl_setopt($curl, CURLOPT_HTTPHEADER	 , $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($curl);

		$result = [];
		if ($output == false)
			$result = [ "custom_error1" => var_export(curl_getinfo($curl)), "custom_error2" => curl_error($curl) ];
		else
		{
			$result = json_decode($output, true);
			$info   = curl_getinfo($curl);
			$result["app_http_code"] = $info["http_code"];
		}
		curl_close($curl);
		
		return $result;
	}
	
	private function GetData($array, $key)
	{
		$result = [];
		if (array_key_exists($key, $array))
			$result = $array[$key];
		return $result;
	}
}

$message = "";
$name    = "";

if (isset ($_POST["submit"]))
{
	if ($_FILES["file"]["error"] == 0)
	{
		$name = $_FILES["file"]["tmp_name"];
		$type = $_FILES["file"]["type"];
		$ext  = end(explode(".", $_FILES["file"]["name"]));

		if (strtoupper($ext) != "CSV" || $type != "application/vnd.ms-excel")
			$message = "Only csv format is supported";
	}
	else
		$message = "Please choose a file to upload";
}

$actualOutput = [];
if ($name != "")
{
	$file = new CsvFile($name, true);
	$rest = new RestClient($file->GetRecords(["email"]));
	$actualOutput = $rest->ProcessEmailList();
}
?>

<html>
    <head>
        <title>Upload Email List</title>
    </head>
    <body>
        <form method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <h1> Upload Email List </h1>
            <table border="1">
                <tr>
                    <td> Choose a file to upload : </td>
                    <td> 
                        <input type="file" name="file" /> 
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:center"> <input type="submit" value="Upload" name="submit" /> </td>
                </tr>
                <?php if ($message != "") { ?>
                <tr>
                    <td colspan="2"> 
                        <span style="color:red"> <?php if ($message != "") echo $message; ?> </span> 
                    </td>
                </tr>
                <?php } ?>
            </table>
            
            <br>
            <?php if (count($actualOutput) > 0) { ?>
            	<table border="1">
            		<tr>
            			<th> Email </th>
            			<th> Social Profiles </th>
            		</tr>
            		<?php 
            			foreach ($actualOutput as $key => $profile) { 
            				echo "<tr>";
            				echo "<td>".$profile["email"]."</td>";
            				echo "<td>".$profile["profiles"]."</td>";
            				echo "</tr>";
            			}
            		?>
            	</table>
            <?php } ?>
        </form>
    </body>
</html>