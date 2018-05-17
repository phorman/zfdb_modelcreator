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
        $this->table = ($_GET['table']) ? $_GET['table'] : 'user';

        $this->dbConnection = mysqli_connect($credentials['server'], $credentials['user'], $credentials['password'], $this->credentials['database']);

        // Check connection
        if (!$this->dbConnection) {
            die("Connection failed: " . mysqli_connect_error());
        }

        $this->pascalCaseName = $this->formatTableName();

        $this->fieldNames = $this->getFieldNames($dbConnection);

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
            $handle = fopen("php_templates/{$this->pascalCaseName}.phtml", 'r') or die('Cannot open file:  php_templates/'.$this->pascalCaseName); //implicitly creates file
            $template = file_get_contents($handle);
            fclose($handle);
        } catch (Exception $e) {
            echo "Unable to access file system";
        }
    
        foreach($data as $key => $field) {
            $template = str_replace("{{$key}}",$field,$template);
        }
            
        return $template;
    }

    /**
     * @todo Have the system write to the appropriate directory and appropriate file
     */
    function saveFile($data) {
        try {
            $handle = fopen($this->pascalCaseName, 'w') or die('Cannot open file:  ' . $this->pascalCaseName); //implicitly creates file
            fwrite($handle,$data);
            fclose($handle);
        } catch (Exception $e) {
            echo "Unable to save file: ", $this->pascalCaseName;
        }
    }


    function createModelFile() {
        if ($this->fieldNames === false) {
            exit ('Database contains no field names');
        }
        
        $modelProperties = "";
        
        foreach ($this->fieldNames as $value) {
            $modelProperties .= "    public \$". $value ."; \n";    
        }
    
        $data = ['table' => $this->pascalCaseName, 'properties' => $modelProperties];
    
        $output = $this->loadTemplate('model',$data);
    
        saveFile($this->pascalCaseName, $output);
    }

    function createTableFile() {
        $data = ['table' => $this->pascalCaseName . 'Table'];

        $output = $this->loadTemplate('table', $data);

        saveFile($this->pascalCaseName . 'Table.php', $output);
    }
    
    function createModuleFile($dbConnection) {
        $output = $this->loadTemplate('module', $output);

        $this->addUseStatement($table, $output);

        $this->addServiceModel($table, $output);

        $this->saveFile('module.phtml', $output);
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

$test = new buildModels();