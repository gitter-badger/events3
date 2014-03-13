<?php

/**
 * This module makes Idfix behave like an OTAP Environment
 * For every environm,ent we have a
 * - Configuration File
 * - Public Up- and Download directory
 * - Database table
 * 
 * There is also a user interface controlpanel for checking and controling
 * the environments.
 * 
 * To disable OTAP functionality just disable this module. Simple :-)
 * 
 * This Module uses the following file structure:
 * 
 * Events3::PublicPath/
 *   otap/
 *     dev/
 *       config/
 *          myconfig.idfix   The main idfix configuration file
 *          myconfig2.idfix
 *          myconfig3(ect.).idfix   
 *       files/
 *         myconfig/
 *         myconfig2/
 *         myconfig3/
 *     test/
 *       config/
 *       files/
 *     accept/
 *       config/
 *       files/
 *     prod/  
 *       config/
 *       files/
 * 
 * It does this by Managing the 
 * - IdfixParse module to look in the right directory
 * - Updating the table space froperty from the config to point to the right environment
 * - Adding a reference to the right upload directory
 * - Creating the directory structure 
 * - Always adding the right environment to the GET-string of the url
 * 
 */

class IdfixOtap extends Events3Module
{
    // List of environment strings
    const ENV_DEV = 'dev';
    const ENV_TEST = 'test';
    const ENV_ACC = 'accept';
    const ENV_PROD = 'prod';
    // List of environments in correct order
    private $aEnvList = array(
        self::ENV_DEV => 1,
        self::ENV_TEST => 2,
        self::ENV_ACC => 3,
        self::ENV_PROD => 4);
    // The GET variable to look for
    const GET_OTAP = 'otap';
    // Current and default environment if nothing is found in URL
    private $cCurrentEnvironment = IdfixOtap::ENV_PROD;

    /**
     * Read the correct environment from the url
     * 
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        // Is there an OTAP direction in the url??
        if (isset($_GET[IdfixOtap::GET_OTAP])) {
            // Get it
            $cEnv = $_GET[IdfixOtap::GET_OTAP];
            // .. and check if it is correct
            if (IdfixOtap::ENV_DEV == $cEnv or IdfixOtap::ENV_TEST == $cEnv or IdfixOtap::ENV_ACC == $cEnv or IdfixOtap::ENV_PROD == $cEnv) {
                // Set the current environment
                $this->cCurrentEnvironment = $cEnv;
            }
        }
    }

    public function Events3IdfixNavbar(&$data)
    {
        // Nothiong to show in production mode
        if ($this->cCurrentEnvironment == self::ENV_PROD) {
            return;
        }

        $data['right']['environment'] = array(
            'title' => "<span class=\"badge\">{$this->cCurrentEnvironment}</span>",
            'tooltip' => 'Agile PHP Cloud Development Platform',
            'href' => $this->Idfix->GetUrl($this->Idfix->cConfigName, '', '', 0, 0, 'Controlpanel'), // top level list
            'icon' => '',
            );
    }

    /**
     * Event handler to add the OTAP key to the querystring
     * 
     * @param mixed $aParams
     * @return void
     */
    public function Events3IdfixGetUrl(&$aParams)
    {
        // Maybe we constructed the UIRL manualy
        // In that case we might have specified an OTAP environment
        if (!isset($aParams[self::GET_OTAP])) {
            $aParams[self::GET_OTAP] = $this->cCurrentEnvironment;
        }
    }

    /**
     * Implement a hook in the IdfixParse module to give us a different place to look for the
     * configuration file.
     * 
     * @param mixed $aData
     * @return void
     */
    public function Events3IdfixGetConfigFileName(&$aData)
    {
        // Set it in the return package
        $aData['cFileName'] = $this->GetConfigFileName($this->cCurrentEnvironment, $aData['cConfigName']);
        //print_r($aData);
        //echo $cConfigFile;
    }

    /**
     * This hook is called by the IdfixParse module directly after
     * reading the configuration from disk.
     * We use it to set a tablespace and filespace specific for
     * this otap environment.
     * 
     * @param mixed $aConfig
     * @return void
     */
    public function Events3IdfixAfterParse(&$aConfig)
    {
        $cCurrentConfig = $this->Idfix->cConfigName;
        $aConfig['tablespace'] = $this->GetTableSpaceName($this->cCurrentEnvironment, $cCurrentConfig);
        $aConfig['filespace'] = $this->GetFilesDirConfig($this->cCurrentEnvironment, $cCurrentConfig);

    }

    public function Events3IdfixActionRemoveconfig(&$output)
    {
        // Everything we need :-)
        $cConfigName = $this->Idfix->cConfigName;
        $cEnv = $this->Idfix->cTableName;

        $this->DeleteConfigFile($cEnv, $cConfigName);
        $this->DeleteFileSystem($cEnv, $cConfigName);
        $this->DeleteTableSpace($cEnv, $cConfigName);

        $this->RedirectToControlPanel();
    }

    /**
     * Remove the configuration file from the filesystem
     * 
     * @param mixed $cEnv
     * @param mixed $cConfigName
     * @return void
     */
    private function DeleteConfigFile($cEnv, $cConfigName)
    {
        $cConfigFile = $this->GetConfigFileName($cEnv, $cConfigName);
        unlink($cConfigFile);
    }
    /**
     * Delete the upload file structure
     * 
     * @param mixed $cEnv
     * @param mixed $cConfigName
     * @return void
     */
    private function DeleteFileSystem($cEnv, $cConfigName)
    {
        $cFilesPath = $this->GetFilesDirConfig($cEnv, $cConfigName);
        $this->RecurseDelete($cFilesPath);
    }

    /**
     * Recursively delete a file structure
     * 
     * @param mixed $cDir
     * @return void
     */
    private function RecurseDelete($cDir)
    {
        foreach (glob($cDir . '/*') as $cFile) {
            if (is_dir($cFile))
                $this->RecurseDelete($cFile);
            else
                unlink($cFile);
        }
        rmdir($cDir);

    }
    private function DeleteTableSpace($cEnv, $cConfigName)
    {
        $cTablename = $this->GetTableSpaceName($cEnv, $cConfigName);
        $this->Database->Query('DROP TABLE ' . $cTablename);
    }

    /**
     * IdfixOtap::Events3IdfixDeploy()
     * 
     * @param mixed $output We will not use this, use a header redirect to the contyrol panel
     * @return void
     */
    public function Events3IdfixActionDeploy(&$output)
    {
        // Everything we need :-)
        $cConfigName = $this->Idfix->cConfigName;
        $cSourceEnv = $this->Idfix->cTableName;
        $cTargetEnv = $this->Idfix->cFieldName;

        // Are we doing up or downstream deployments?????
        // Upstream deployments should only deploy the configuration
        // downstream it is: data only!!
        $bUpstream = (boolean)($this->aEnvList[$cSourceEnv] < $this->aEnvList[$cTargetEnv]);

        if ($bUpstream) {
            $this->CopyConfigFile($cSourceEnv, $cTargetEnv, $cConfigName);
        }
        else {
            $this->CopyFileSystem($cSourceEnv, $cTargetEnv, $cConfigName);
            $this->CopyTableSpace($cSourceEnv, $cTargetEnv, $cConfigName);
        }


        $this->RedirectToControlPanel();
    }

    private function CopyConfigFile($cSourceEnv, $cTargetEnv, $cConfigName)
    {
        $cSourceConfigFile = $this->GetConfigFileName($cSourceEnv, $cConfigName);
        $cTargetConfigFile = $this->GetConfigFileName($cTargetEnv, $cConfigName);
        unlink($cTargetConfigFile);
        copy($cSourceConfigFile, $cTargetConfigFile);
    }

    private function CopyFileSystem($cSourceEnv, $cTargetEnv, $cConfigName)
    {
        // First delete the old files
        $this->DeleteFileSystem($cTargetEnv, $cConfigName);
        // Get references to the two filestructures...
        $cSourceFilesPath = $this->GetFilesDirConfig($cSourceEnv, $cConfigName);
        $cTargetFilesPath = $this->GetFilesDirConfig($cTargetEnv, $cConfigName);
        // And do the recursdive magic
        $this->rcopy($cSourceFilesPath, $cTargetFilesPath);

    }


    /**
     * Recursive function
     * Found on internet.... looks nice...
     * Let's try it ...
     * 
     * @param mixed $src
     * @param mixed $dest
     * @return
     */
    private function rcopy($src, $dest)
    {
        // If source is not a directory stop processing
        if (!is_dir($src))
            return false;

        // If the destination directory does not exist create it
        if (!is_dir($dest)) {
            if (!mkdir($dest)) {
                // If the destination directory could not be created stop processing
                return false;
            }
        }

        // Open the source directory to read in files
        $i = new DirectoryIterator($src);
        foreach ($i as $f) {
            if ($f->isFile()) {
                copy($f->getRealPath(), "$dest/" . $f->getFilename());
            }
            else
                if (!$f->isDot() && $f->isDir()) {
                    $this->rcopy($f->getRealPath(), "$dest/$f");
                }
        }
    }

    private function CopyTableSpace($cSourceEnv, $cTargetEnv, $cConfigName)
    {
        // First delete the old table
        $this->DeleteTableSpace($cTargetEnv, $cConfigName);
        // Get the names for source and target tables
        $cSource = $this->GetTableSpaceName($cSourceEnv, $cConfigName);
        $cTarget = $this->GetTableSpaceName($cTargetEnv, $cConfigName);
        // .. and do the magic
        $this->Database->Query("CREATE TABLE {$cTarget} LIKE {$cSource}");
        $this->Database->Query("INSERT INTO {$cTarget} SELECT * FROM {$cSource}");
    }

    private function RedirectToControlPanel()
    {
        $cUrl = $this->Idfix->GetUrl($this->Idfix->cConfigName, '', '', 0, 0, 'Controlpanel');
        header('location: ' . $cUrl);
        //exit();
    }

    /**
     * Show a grid with information for every environment from this configuration
     * Also show actions to perform.
     * 
     * @param mixed $output
     * @return void
     */
    public function Events3IdfixActionControlpanel(&$output)
    {
        $this->IdfixDebug->Profiler(__method__, 'start');

        $aTemplateVars = array(
            self::ENV_DEV => $this->RenderInfoPanel(self::ENV_DEV, 1),
            self::ENV_TEST => $this->RenderInfoPanel(self::ENV_TEST, 2),
            self::ENV_ACC => $this->RenderInfoPanel(self::ENV_ACC, 3),
            self::ENV_PROD => $this->RenderInfoPanel(self::ENV_PROD, 4),
            );

        $output = $this->RenderTemplate('ControlPanel', $aTemplateVars);

        $this->IdfixDebug->Profiler(__method__, 'stop');
    }

    private function RenderInfoPanel($cEnv, $iVolgorde)
    {
        static $aEnvironmentNames = array(
            self::ENV_DEV => array('name' => 'Development', 'class' => 'panel-info'),
            self::ENV_TEST => array('name' => 'Test', 'class' => 'panel-info'),
            self::ENV_ACC => array('name' => 'Acceptation', 'class' => 'panel-info'),
            self::ENV_PROD => array('name' => 'Production', 'class' => 'panel-info'),
            );

        $cConfigFile = $this->GetConfigFileName($cEnv, $this->Idfix->cConfigName);
        $bConfigPresent = file_exists($cConfigFile);

        // Create 0-indexed array of the environments
        $aEnvList = array_keys($this->aEnvList);
        $cDeploy = '';
        if ($iVolgorde < 4 and $bConfigPresent) {
            $cIcon = $this->Idfix->GetIconHTML('arrow-right');
            $cNextEnv = $aEnvList[$iVolgorde];
            $cNextEnvName = $aEnvironmentNames[$cNextEnv]['name'];
            $cDeploy .= $this->GetDeployButton($cEnv, $cNextEnv, "Deploy Configuration to <em>{$cNextEnvName}</em> environment {$cIcon}");
        }
        if ($iVolgorde > 1 and $bConfigPresent) {
            $cIcon = $this->Idfix->GetIconHTML('arrow-left');
            $cPrevEnv = $aEnvList[$iVolgorde - 2];
            $cPrevEnvName = $aEnvironmentNames[$cPrevEnv]['name'];
            $cDeploy .= $this->GetDeployButton($cEnv, $cPrevEnv, "{$cIcon} Copy data & files to <em>{$cPrevEnvName}</em> environment");
        }

        // Create the delete button

        if ($bConfigPresent) {
            $cDeploy .= $this->GetDeleteButton($cEnv);
        }

        // Create url to the right environment
        $cIcon = $this->Idfix->GetIconHTML('log-in');
        $cUrl = $this->Idfix->GetUrl('', '', '', 1, 0, 'list', array(self::GET_OTAP => $cEnv));
        $cEnvName = $aEnvironmentNames[$cEnv]['name'];
        $cHref = "<a href=\"{$cUrl}\">{$cIcon} {$cEnvName}</a>";
        if (!$bConfigPresent) {
            $cHref = $cEnvName;
        }
        $aTemplateVars = array(
            'title' => $cHref,
            'class' => $aEnvironmentNames[$cEnv]['class'],
            'deploy' => $cDeploy,
            'fileinfo' => $this->RenderInfoFileSystem($cEnv),
            'password' => $this->RenderTemplate('Password'),
            );

        return $this->RenderTemplate('ControlPanelItem', $aTemplateVars);
    }

    /**
     * Render a deploy Button
     * 
     * @param mixed $cEnvFrom
     * @param mixed $cEnvTo
     * @return void
     */
    private function GetDeployButton($cEnvFrom, $cEnvTo, $cName)
    {
        $cUrl = $this->Idfix->GetUrl($this->Idfix->cConfigName, $cEnvFrom, $cEnvTo, 0, 0, 'deploy');
        $cButton = "<a  onclick=\"confirm('Are you sure you want to proceed? The target configuration and/or dataset will be destroyed before deployment!')\" href=\"{$cUrl}\" class=\"btn btn-primary btn-block\" role=\"button\">{$cName}</a>";
        return $cButton;
    }
    private function GetDeleteButton($cEnv)
    {
        $cIcon = $this->Idfix->GetIconHTML('remove');
        $cUrl = $this->Idfix->GetUrl($this->Idfix->cConfigName, $cEnv, '', 0, 0, 'removeconfig');
        $cButton = "<a onclick=\"confirm('Are you sure you want to delete thuis configuration and all of it\'s data?')\" href=\"{$cUrl}\" class=\"btn btn-warning btn-block\" role=\"button\">{$cIcon} Delete Environment (config & data & files)</a>";
        return $cButton;
    }
    /**
     * Create a key/Value table with filesystem information
     * 
     * @param mixed $cEnv
     * @return void
     */
    private function RenderInfoFileSystem($cEnv)
    {

        $cConfigName = $this->Idfix->cConfigName;
        $aTable = array();


        $cPublicPath = $this->ev3->PublicPath;
        $aTable[] = array(
            'title' => 'Public File System',
            'class' => (is_dir($cPublicPath) ? 'success' : 'danger'),
            'info' => $cPublicPath,
            'description' => '',
            );

        $cFilesPath = $this->GetFilesDirConfig($cEnv, $cConfigName);
        $aTable[] = array(
            'title' => 'Upload directory',
            'class' => (is_dir($cFilesPath) ? 'success' : 'danger'),
            'info' => str_ireplace($cPublicPath, '', $cFilesPath),
            'description' => '',
            );

        $cConfigFile = $this->GetConfigFileName($cEnv, $cConfigName);
        $cHashCode = file_exists($cConfigFile) ? md5_file($cConfigFile) : 'File not found';
        $aTable[] = array(
            'title' => 'Configuration File',
            'class' => (file_exists($cConfigFile) ? 'success' : 'danger'),
            'info' => str_ireplace($cPublicPath, '', $cConfigFile) . '<br />' . $cHashCode,
            'description' => '',
            );

        $cTableSpace = $this->GetTableSpaceName($cEnv, $cConfigName);
        $iRecords = $this->Database->CountRecords($cTableSpace);
        $aTable[] = array(
            'title' => 'Data Table',
            'class' => count($this->Database->ShowTables($cTableSpace)) > 0 ? 'success' : 'danger',
            'info' => $cTableSpace . " (#{$iRecords} objects)",
            'description' => '',
            );
        //print_r($this->Database->ShowTables($cTableSpace));

        $aTemplateVars = compact('aTable');
        return $this->RenderTemplate('ControlPanelTable', $aTemplateVars);
    }

    /**
     * Render an Idfix template
     * 
     * @param string $cTemplateName Name of the template without path and extention
     * @param array $aVars Template variables (@see Template Module)
     * @return string Rendered template
     */
    private function RenderTemplate($cTemplateName, $aVars = array())
    {
        $cTemplateFile = dirname(__file__) . "/templates/{$cTemplateName}.php";
        $return = $this->Template->Render($cTemplateFile, $aVars);
        return $return;
    }


    /**
     * Create the full directory structure for
     * the otap system.
     * It is called and check form the unittest
     * So no need for runtime performance issues.
     * 
     * @return void
     */
    public function SetupDirectoryStructure()
    {
        $aEnvironments = array(
            self::ENV_ACC,
            self::ENV_DEV,
            self::ENV_PROD,
            self::ENV_TEST);

        $cDir2Check = $this->GetBaseDir();
        $this->CheckOrCreateDirectory($cDir2Check);

        foreach ($aEnvironments as $cEnvironment) {
            $cDirEnv = $cDir2Check . '/' . $cEnvironment;
            $this->CheckOrCreateDirectory($cDirEnv);
            $this->CheckOrCreateDirectory($cDirEnv . '/config');
            $this->CheckOrCreateDirectory($cDirEnv . '/files');
        }
    }
    private function CheckOrCreateDirectory($cDir)
    {
        if (!is_dir($cDir)) {
            mkdir($cDir);
        }
    }

    private function GetBaseDir()
    {
        return $this->ev3->PublicPath . '/otap';
    }
    private function GetConfigDir($cEnv = null)
    {
        $cEnv = ($cEnv) ? $cEnv : $this->cCurrentEnvironment;
        return $this->GetBaseDir() . '/' . $cEnv . '/config';
    }
    private function GetFilesDir($cEnv = null)
    {
        $cEnv = ($cEnv) ? $cEnv : $this->cCurrentEnvironment;
        return $this->GetBaseDir() . '/' . $cEnv . '/files';
    }
    private function GetTableSpaceName($cEnv, $cConfigName)
    {
        $cConfigName = $this->Idfix->ValidIdentifier($cConfigName);
        return 'idfix_otap_' . $cEnv . '_' . $cConfigName;
    }
    private function GetConfigFileName($cEnv, $cConfigName)
    {
        $cConfigName = $this->Idfix->ValidIdentifier($cConfigName);
        return $this->GetConfigDir($cEnv) . '/' . $cConfigName . '.idfix';
    }
    private function GetFilesDirConfig($cEnv, $cConfigName)
    {
        $cConfigName = $this->Idfix->ValidIdentifier($cConfigName);
        return $this->GetFilesDir($cEnv) . '/' . $cConfigName;
    }
}