<?php
require_once('credentials.php');
// Small program to quickly create ZendDBTable Models

class buildModels 
{
    var $dbConnection;
    var $table;
    var $pascalCaseName;
    var $fieldNames;

    function __construct($credentials)
    {
        $this->table = (array_key_exists('table', $_GET)) ? $_GET['table'] : 'user';

        $this->dbConnection = mysqli_connect($credentials['server'], $credentials['user'], $credentials['password'], $credentials['database']);

        // Check connection
        if (!$this->dbConnection) {
            die("Connection failed: " . mysqli_connect_error());
        }

        $this->formatTableName();

        $this->fieldNames = $this->getFieldNames($this->dbConnection);

        $this->createModelFile();

        $this->createTableFile();

        $this->createModuleFile();

        $this->dbConnection->close();
    }

    function formatTableName() {
        $tableNameArray = explode("_", $this->table);
        
        $formattedTableName = '';
        foreach ($tableNameArray as $value) {
            $formattedTableName .= ucfirst($value);
        }

        $this->pascalCaseName = $formattedTableName;
    }

    function getFieldNames($conn) {
        $query = "SHOW COLUMNS FROM ". $this->table;
        
        $fieldArray = array();
        
        if ($result = $this->dbConnection->query($query)) {
        
            /* fetch associative array */
            while ($row = $result->fetch_assoc()) {
                $fieldArray[] = $row['Field'];
            }
        
            /* free result set */
            $result->free();
            
            return $fieldArray;
        } else {
            echo "No Data";
            return false;
        }
    }

    function loadTemplate($filename, $data) {
        try {
            $template = file_get_contents("php_templates/$filename.phtml");
        } catch (Exception $e) {
            echo "Unable to access file system";
        }
    
        foreach($data as $key => $field) {
            $template = preg_replace("/\{\{$key\}\}/sim",$field,$template);
        }
            
        return $template;
    }

    /**
     * @todo Have the system write to the appropriate directory and appropriate file
     */
    function saveFile($filename, $data) {
        try {
            $handle = fopen($filename, 'w') or die('Cannot open file:  ' . $filename); //implicitly creates file
            fwrite($handle, $data);
            fclose($handle);
        } catch (Exception $e) {
            echo "Unable to save file: ", $filename;
        }
    }


    function createModelFile() {
        if ($this->fieldNames === false) {
            exit ('Database contains no field names');
        }
        
        $modelProperties = "";
        
        foreach ($this->fieldNames as $value) {
            $modelProperties .= "    public \$". $value ."; \r\n";    
        }
    
        $data = ['table' => $this->pascalCaseName, 'properties' => $modelProperties];
    
        $output = $this->loadTemplate('model',$data);
    
        $this->saveFile('module/Application/src/model/'.$this->pascalCaseName . ".php", $output);

        echo "Created Model: " .$this->pascalCaseName . "<br>\n";
    }

    function createTableFile() {
        $data = ['table' => $this->pascalCaseName . 'Table'];

        $output = $this->loadTemplate('table', $data);

        $this->saveFile('module/Application/src/model/'. $this->pascalCaseName . 'Table.php', $output);

        echo "Created Table: " .$this->pascalCaseName . "<br>\n";
    }
    
    function createModuleFile() {
        $output = $this->loadTemplate('module', []);

        $output = $this->addUseStatement($output);

        $output = $this->addServiceModel($output);

        $this->saveFile('module/Application/Module.php', $output);

        echo "Created Module: " .$this->pascalCaseName . "<br>\n";
    }   
    
    function addUseStatement($output) {
        $useFilter = '%[/*]{4} START USE MODEL STATEMENTS [*/]{2}(.*?)[/*]{4} END USE MODEL STATEMENTS [*/]{2}%sim';

        //store existing use statements
        $useStatements = (preg_match($useFilter, $output, $found)) ? $found[1] : '';

        $newStatements   = "use Application\Model\\" . $this->pascalCaseName . ";\n";
        $newStatements  .= "use Application\Model\\" . $this->pascalCaseName . "Table;\n";

        $newUse  = "/*** START USE MODEL STATEMENTS */\n";
        $newUse .= $useStatements ."\n";
        $newUse .= $newStatements;
        $newUse .="/*** END USE MODEL STATEMENTS */\n";
        
        return preg_replace($useFilter, $newUse, $output);
    }

    function addServiceModel($output) {
        $serviceFilter = '%[/*]{4} START MODELS SERVICE [*/]{2}(.*?)[/*]{4} END MODELS SERVICE [*/]{2}%sim'; 

        //store existing use statements
        $currentServices = (preg_match($serviceFilter, $output, $found)) ? $found[1] : '';

        $data = [
            'model' => $this->pascalCaseName,
            'model_table' => $this->pascalCaseName . "Table"
        ];

        $newService = $this->loadTemplate('module-service', $data);
        
        $newOutput = "//** START MODELS SERVICE */\n";
        $newOutput .= $currentServices ."\n";
        $newOutput .= $newService;
        $newOutput .="/*** END MODELS SERVICE */\n";
            
        return preg_replace($serviceFilter, $newOutput, $output);
    }
}


/**
 * INSTANCIATE THE CLASS
 */

$test = new buildModels($credentials);