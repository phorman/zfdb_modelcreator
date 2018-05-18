<?php
require_once('credentials.php');
// Small program to quickly create ZendDBTable Models

class buildModels 
{
    var $dbConnection;
    var $database;
    var $table;
    var $pascalCaseName;
    var $fieldNames;

    function __construct($credentials)
    {
        $this->database = $credentials['database'];

        $this->table = (array_key_exists('table', $_GET)) ? $_GET['table'] : 'user';

        $this->dbConnection = mysqli_connect($credentials['server'], $credentials['user'], $credentials['password'], $credentials['database']);

        // Check connection
        if (!$this->dbConnection) {
            die("Connection failed: " . mysqli_connect_error());
        }

        foreach ($this->getTableNames() as $name) {
            $this->table = $name;

            $this->formatTableName()
                 ->getFieldNames()
                 ->createModelFile()
                 ->createTableFile()
                 ->createModuleFile();

            echo "-----------<br>\r\n";
        }

        $this->dbConnection->close();
    }

    function formatTableName() {
        $tableNameArray = explode("_", $this->table);
        
        $formattedTableName = '';
        foreach ($tableNameArray as $value) {
            $formattedTableName .= ucfirst($value);
        }

        $this->pascalCaseName = $formattedTableName;

        return $this;
    }

    function getFieldNames() {
        $query = "SHOW COLUMNS FROM ". $this->table;
        
        $fieldArray = array();
        
        if ($result = $this->dbConnection->query($query)) {
        
            /* fetch associative array */
            while ($row = $result->fetch_assoc()) {
                $fieldArray[] = $row['Field'];
            }
        
            /* free result set */
            $result->free();
            
            $this->fieldNames = $fieldArray;
        } else {
            echo "No Data";
            $fieldNames = false;
        }

        return $this;
    }

    function getTableNames() {
        $query = "SELECT `table_name` AS 'table' FROM information_schema.tables WHERE table_schema = '". $this->database ."'";

        $tableArray = array();
        
        if ($result = $this->dbConnection->query($query)) {
        
            /* fetch associative array */
            while ($row = $result->fetch_assoc()) {
                $tableArray[] = $row['table'];
            }
        
            /* free result set */
            $result->free();
            
            return $tableArray;
        } else {
            echo "No Data";
            return false;
        }
    }

    function loadTemplate($filename, $data) {
        $file = "php_templates/$filename.phtml";

        $file = (strstr($filename, 'Application')) ? $filename : $file;

        try {
            $template = file_get_contents($file);
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

        return $this;
    }

    function createTableFile() {
        $data = ['table' => $this->pascalCaseName . 'Table'];

        $output = $this->loadTemplate('table', $data);

        $this->saveFile('module/Application/src/model/'. $this->pascalCaseName . 'Table.php', $output);

        echo "Created Table: " .$this->pascalCaseName . "<br>\n";

        return $this;
    }
    
    function createModuleFile() {
        $filename = 'module';

        if (file_exists('module/Application/Module.php')) {
            $filename  = 'module/Application/Module.php';
        }

        $output = $this->loadTemplate($filename, []);

        $output = $this->addUseStatement($output);

        $output = $this->addServiceModel($output);

        $this->saveFile('module/Application/Module.php', $output);

        echo "Created Module: " .$this->pascalCaseName . "<br>\n";

        return $this;
    }   
    
    function addUseStatement($output) {
        $useFilter = '%[\r\n]*[/*]{4} START USE MODEL STATEMENTS [*/]{2}(.*?)[/*]{4} END USE MODEL STATEMENTS [*/]{2}[\r\n]*%sim';

        //store existing use statements
        $useStatements = (preg_match($useFilter, $output, $found)) ? trim($found[1]) : '';

        $newStatements   = "use Application\Model\\" . $this->pascalCaseName . ";\r\n";
        $newStatements  .= "use Application\Model\\" . $this->pascalCaseName . "Table;\r\n";

        $newUse  = "\r\n\r\n/*** START USE MODEL STATEMENTS */\r\n";
        $newUse .= $useStatements ."\r\n";
        $newUse .= $newStatements;
        $newUse .="/*** END USE MODEL STATEMENTS */\r\n\r\n";
        
        return preg_replace($useFilter, $newUse, $output);
    }

    function addServiceModel($output) {
        $serviceFilter = '%[\r\n]*[/*]{3} START MODELS SERVICE [*/]{2}(.*?)[/*]{3} END MODELS SERVICE [*/]{2}[\r\n]*%sim';

        //store existing use statements
        $currentServices = (preg_match($serviceFilter, $output, $found)) ? trim($found[1]) : '';

        $data = [
            'model' => $this->pascalCaseName,
            'model_table' => $this->pascalCaseName . "Table",
            'gateway' => strtolower($this->table)
        ];

        $newService = $this->loadTemplate('module-service', $data);
        
        $newOutput = "\r\n/** START MODELS SERVICE */\r\n";
        $newOutput .= $currentServices;
        $newOutput .= $newService;
        $newOutput .="\r\n/** END MODELS SERVICE */\r\n\r\n";
            
        return preg_replace($serviceFilter, $newOutput, $output);
    }
}


/**
 * INSTANCIATE THE CLASS
 */

$test = new buildModels($credentials);