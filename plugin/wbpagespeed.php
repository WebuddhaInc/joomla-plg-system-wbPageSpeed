<?php

/*
  wbPack - Javascript Package for Joomla
  https://docs.joomla.org/Plugin/Events
*/

// check that we have access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Load System
jimport( 'joomla.plugin.plugin' );

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

if( !function_exists('inspect') ){
  function inspect(){
    echo '<pre>' . print_r( func_get_args(), true ) . '</pre>';
  }
}

class plgSystemWbPageSpeed extends JPlugin {

  /**
   * Runtime
   */
  private $_uri_root     = null;
  private $_path_root    = null;
  private $_cache_time   = null;
  private $_cache_key    = null;
  private $_cache_active = false;

  /*
   *
   *  Run after system dispatch
   *
   */
  public function __construct(&$subject, $config){

    // Initialize
      parent::__construct($subject, $config);

    // Base Path
      $this->_uri         = JURI::root();
      $this->_uri_root    = JURI::root(true);
      $this->_path_root   = strlen($this->_uri_root) ? substr(JPATH_BASE, 0, -(strlen($this->_uri_root))) : JPATH_BASE;
      $this->_cache_time  = $this->params->get('cache_time', JFactory::getConfig()->get('cachetime'));

    // Testing Page Cache
      if ($this->_cache_active && $_SERVER['REQUEST_METHOD'] == 'GET') {
        $this->_cache = JFactory::getCache('plg_system_wbpagespeed', 'page');
        $this->_cache_key = md5(serialize(array($_SERVER['SCRIPT_URI'], $_SERVER['SCRIPT_FILENAME'], $_REQUEST)));
        if ($this->_cache->contains( $this->_cache_key, 'plg_system_wbpagespeed')){
          $data = $this->_cache->get( $this->_cache_key, 'plg_system_wbpagespeed');
          echo $data['body'];
          echo '<!-- '.$data['time'].' -->';
          exit;
        }
      }

  }

  /**
   * [getRequestCache description]
   * @param  [type] $key [description]
   * @return [type]      [description]
   */
  public function getRequestCache( $key ){
    return JFactory::getApplication()->getBody();
  }

  /*
   *
   *  Dispatch Events
   *
   *
   */
  public function onBeforeCompileHead(){

    if (JFactory::getApplication()->isAdmin())
      return;

    if ($this->params->get('compress_js', 1))
      $this->process_docScripts();

    if ($this->params->get('compress_css', 1))
      $this->process_docStylesheets();

  }

  public function onAfterRender(){

    if (JFactory::getApplication()->isAdmin())
      return;

    if ($this->params->get('compress_html', 1)) {
      $app = JFactory::getApplication();
      $body = $app->getBody();
      $script_blocks = array();
      $body = preg_replace_callback('/\<script.*?\<\/script>/s', function($match) use (&$script_blocks) {
        $script_blocks[] = reset($match);
        return '{script_block_'. count($script_blocks) .'}';
      }, $body);
      $body = preg_replace('/\s+/',' ',preg_replace('/[\r\n]+/',' ',$body));
      $body = preg_replace_callback('/\{script_block_(\d+)\}/', function($match) use (&$script_blocks) {
        return $script_blocks[ end($match)-1 ];
      }, $body);
      $app->setBody( $body );
    }

    // Cache
    if (isset($this->_cache))
      $this->_cache->store( array(
        'time' => time(),
        'body' => JFactory::getApplication()->getBody()
        ), $this->_cache_key, 'plg_system_wbpagespeed' );

  }

  /**
   * [clear_docScriptsCache description]
   * @return [type] [description]
   */
  public function clear_docCache( $match ){

    // Cache Time
      $cacheTime = time() - ($this->_cache_time * 60);

    // Clear Cache
      if($dir = opendir(JPATH_CACHE)) {
        while(false !== ($file = readdir($dir))) {
          $cacheFile = JPATH_CACHE.'/'.$file;
          if( preg_match($match, $file) )
            if( filemtime($cacheFile) < $cacheTime )
              @unlink($cacheFile);
        }
        closedir($dir);
      }

  }

  /**
   * [process_docStylesheets description]
   * @return [type] [description]
   */
  public function process_docStylesheets(){

    // Stage
      $app = JFactory::getApplication();
      $cfg = JFactory::getConfig();
      $doc = $app->getDocument();
      $template = $app->getTemplate();
      $styleSheets = $doc->_styleSheets;
      $styleSheetsFinal = array();
      $styleSheetsProcess = array();

    // Parse
      if( $styleSheets ){

        // CSS URL Replacement Callback
          global $_cssUrlReplace_path;
          function _cssImportReplace($match){
            global $_cssUrlReplace_path, $mainframe;
            $path = $_cssUrlReplace_path;
            $url = $match[0];
            $url = preg_replace('/^.*url\(|\)\;*$/','',$url);
            $url = preg_replace('/^[\'\"]+|[\'\"]+$/','',$url);
            if (preg_match('/^(http|https)\:/', $url))
              $impFileData = file_get_contents($url);
            else {
              do {
                if( preg_match('/^\//',$url) ){
                  break;
                } elseif(preg_match('/^(http|https)\:/', $url)){
                  break;
                } elseif( preg_match('/^\.\.\//',$url) ){
                  $url = preg_replace('/^\.\.\//','',$url);
                  array_pop($path);
                } elseif( preg_match('/^\.\//',$url) ){
                  $url = preg_replace('/^\.\//','',$url);
                } else {
                  $url = implode('/',$path).'/'.$url;
                }
              } while($limit-- > 0);
              $impFileData = file_get_contents(JPATH_BASE . $url);
            }
            $_tmpPath = $_cssUrlReplace_path;
            $_cssUrlReplace_path = explode('/',$url); array_pop($_cssUrlReplace_path);
            $impFileData =
              '/* import:'.preg_replace('/^.*\//','',$url).' */'
              . "\n"
              . preg_replace_callback('/url\(.*?\)/','_cssUrlReplace',$impFileData)
              . "\n";
            $_cssUrlReplace_path = $_tmpPath;

            return $impFileData;
          }
          function _cssUrlReplace($match){
            if (preg_match('/^url\([\'\"]*(data|http|https)\:/', $match[0]))
              return $match[0];
            global $_cssUrlReplace_path;
            $path = $_cssUrlReplace_path;
            $url = $match[0];
            $url = preg_replace('/^url\(|\)$/','',$url);
            $url = preg_replace('/^[\'\"]+|[\'\"]+$/','',$url);
            $limit = 10;
            do {
              if( preg_match('/^\//',$url) ){
                return 'url('.$url.')';
              } elseif( preg_match('/^\.\.\//',$url) ){
                $url = preg_replace('/^\.\.\//','',$url);
                array_pop($path);
              } elseif( preg_match('/^\.\//',$url) ){
                $url = preg_replace('/^\.\//','',$url);
              } else {
                $url = implode('/',$path).'/'.$url;
              }
            } while($limit-- > 0);
            return $match[0];
          }

        // Collect Files for Processing
          foreach( $styleSheets AS $cssFile => $cssParams ){
            if (strpos($cssFile, $this->_uri) === 0){
              $cssFile = substr($cssFile, strlen($this->_uri)-1);
            }
            if (preg_match('/[a-z]+\:/',$cssFile)) {
              $styleSheetsFinal[ $cssFile ] = $cssParams;
            }
            else {
              if (strpos($cssFile, '/') !== 0)
                $cssFile = $this->_uri_root . '/' . $cssFile;
              if (strpos($cssFile, '?') !== false)
                $cssFile = substr($cssFile, 0, strpos($cssFile, '?'));
              if (is_file($this->_path_root . $cssFile)){
                $styleSheetsProcess[ $cssFile ] = $cssParams;
              }
              else {
                $styleSheetsFinal[ $cssFile ] = $cssParams;
              }
            }
          }

        // If files require processing
          if( $styleSheetsProcess ){

            // Calculate Cache Filename
              $cacheFile = JPATH_CACHE . '/' . ($template.'-'.md5(serialize($styleSheetsProcess).date('Y-m-d H')).'.css');
              $cacheLink = substr($cacheFile,strlen($this->_path_root));

            // Clear Cache
              $this->clear_docCache('/'.$template.'\-.*\.css/');

            // Create / Store if needed
              if( !file_exists($cacheFile) ){

                // Collect JS Data
                  $cssContent = '';
                  $filesProcessed = 0;

                  foreach( $styleSheetsProcess AS $cssFile => $cssParams ){
                    $cssFileData = file_get_contents($this->_path_root . $cssFile);
                    $_cssUrlReplace_path = explode('/',$cssFile); array_pop($_cssUrlReplace_path);
                    $cssFileData = preg_replace_callback('/url\(.*?\)/','_cssUrlReplace',$cssFileData);
                    $cssFileData = preg_replace_callback('/\@import\s+url\(.*?\)\;*/','_cssImportReplace',$cssFileData);
                    $cssFileData = preg_replace('/\n[\t\s]+/',"\n",$cssFileData);
                    $cssFileData = preg_replace('/[\n\r]+/',"\r\n",$cssFileData);
                    /**
                      // Not working perfectly
                      $cssFileData = preg_replace('/\/[\*]+.*?[\*]+\//s','',$cssFileData);
                      $cssFileData = preg_replace('/\/\/.*?[\r\n]+/','',$cssFileData);
                    **/
                    $cssFileData = preg_replace_callback('/[\r\n\s]*\{.*?\}/s', function($match){
                      return preg_replace('/[\r\n]/','',reset($match));
                      }, $cssFileData);
                      $cssContent
                      .= '/* inc:'.preg_replace('/^.*\//','',$cssFile).' */'
                      . "\n"
                      . $cssFileData
                      . "\n\n";
                    $filesProcessed++;
                  }

                // Write JS File
                  $fh = fopen( $cacheFile, 'w' );
                  fwrite( $fh, $cssContent );
                  fclose( $fh );

              }

            // Push Final
              $styleSheetsFinal[ $cacheLink ] = array(
                'mime'    => 'text/css',
                'media'   => "all",
                'attribs' => array(
                  )
                );

          }

        // Complete
          $doc->_styleSheets = $styleSheetsFinal;

      }

  }

  /**
   * [process_docScripts description]
   * @param  [type] $scripts [description]
   * @return [type]          [description]
   */
  public function process_docScripts(){

    // Stage
      $app = JFactory::getApplication();
      $cfg = JFactory::getConfig();
      $doc = $app->getDocument();
      $template = $app->getTemplate();
      $scripts = $doc->_scripts;
      $scriptsFinal = array();
      $scriptsProcess = array();

    // Parse
      if( $scripts ){

        // Collect Files for Processing
          foreach( $scripts AS $jsFile => $jsParams ){
            if (strpos($jsFile, $this->_uri) === 0){
              $jsFile = substr($jsFile, strlen($this->_uri)-1);
            }
            if (preg_match('/[a-z]+\:/',$jsFile)) {
              $scriptsFinal[ $jsFile ] = $jsParams;
            }
            else {
              if (strpos($jsFile, '/') !== 0)
                $jsFile = $this->_uri_root . '/' . $jsFile;
              if (strpos($jsFile, '?') !== false)
                $jsFile = substr($jsFile, 0, strpos($jsFile, '?'));
              if (is_file($this->_path_root . $jsFile) ){
                $scriptsProcess[ $jsFile ] = $jsParams;
              }
              else {
                $scriptsFinal[ $jsFile ] = $jsParams;
              }
            }
          }

        // If files require processing
          if( $scriptsProcess ){

            // Calculate Cache Filename
              $cacheFile = JPATH_CACHE . '/' . ($template.'-'.md5(serialize($scriptsProcess).date('Y-m-d H')).'.js');
              $cacheLink = substr($cacheFile,strlen($this->_path_root));

            // Clear Cache
              $this->clear_docCache('/'.$template.'\-.*\.js/');

            // Create / Store if needed
              if( !file_exists($cacheFile) ){

                // Collect JS Data
                  $jsContent = '';
                  $filesProcessed = 0;
                  foreach( $scriptsProcess AS $jsFile => $jsParams ){
                    $jsFileData = file_get_contents($this->_path_root . $jsFile);
                    $jsFileData = preg_replace('/\n[\t\s]+/',"\n",$jsFileData);
                    $jsFileData = preg_replace('/[\n\r]+/',"\r\n",$jsFileData);
                    /**
                      // Not working perfectly
                      $jsFileData = preg_replace('/\/[\*]+.*?[\*]+\//s','',$jsFileData);
                      $jsFileData = preg_replace('/\/\/.*?[\r\n]+/','',$jsFileData);
                    **/
                    $jsContent
                      .= '/* inc:'.preg_replace('/^.*\//','',$jsFile).' */'
                      . "\n"
                      . $jsFileData
                      . "\n";
                    $filesProcessed++;
                  }

                // Write JS File
                  $fh = fopen( $cacheFile, 'w' );
                  fwrite( $fh, $jsContent );
                  fclose( $fh );

              }

            // Push Final
              $scriptsFinal[ $cacheLink ] = array(
                'mime'  => 'text/javascript',
                'defer' => null,
                'async' => null
                );

          }

        // Complete
          $doc->_scripts = $scriptsFinal;

      }

  }

}