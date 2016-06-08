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

  /*
   *
   *  Run after system dispatch
   *
   */
  public function __construct(&$subject, $config){
    parent::__construct($subject, $config);
  }

  /*
   *
   *  Dispatch Events
   *
   *
   */
  public function onBeforeCompileHead(){

    if ($this->params->get('compress_js', 1)) {
      $this->process_docScripts();
    }

    if ($this->params->get('compress_css', 1)) {
      $this->process_docStylesheets();
    }

  }

  public function onAfterRender(){
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
  }

  /**
   * [clear_docScriptsCache description]
   * @return [type] [description]
   */
  public function clear_docCache( $match ){

    // Stage
      $app = JFactory::getApplication();
      $cfg = JFactory::getConfig();
      $cacheTime = time() - ($cfg->get('cachetime') * 60);

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
            do {
              if( preg_match('/^\//',$url) ){
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
            $impFileData = file_get_contents($mainframe->getCfg('absolute_path') . $url);
            $_tmpPath = $_cssUrlReplace_path;
            $_cssUrlReplace_path = split('/',$url); array_pop($_cssUrlReplace_path);
            $impFileData =
              '/* import:'.preg_replace('/^.*\//','',$url).' */'
              . "\n"
              . preg_replace_callback('/url\(.*?\)/','_cssUrlReplace',$impFileData)
              . "\n";
            $_cssUrlReplace_path = $_tmpPath;

            return $impFileData;
          }
          function _cssUrlReplace($match){
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
            if (strpos($cssFile, '/') !== 0)
              $cssFile = '/' . $cssFile;
            if (is_file(JPATH_BASE . $cssFile) ){
              $styleSheetsProcess[ $cssFile ] = $cssParams;
            }
            else {
              $styleSheetsFinal[ $cssFile ] = $cssParams;
            }
          }

        // If files require processing
          if( $styleSheetsProcess ){

            // Calculate Cache Filename
              $cacheFile = JPATH_CACHE . '/' . ($template.'-'.md5(serialize(',',$styleSheetsProcess).date('Y-m-d H')).'.css');
              $cacheLink = substr($cacheFile,strlen(JPATH_BASE));

            // Clear Cache
              $this->clear_docCache('/'.$template.'\-.*\.css/');

            // Create / Store if needed
              if( !file_exists($cacheFile) ){

                // Collect JS Data
                  $cssContent = '';
                  $filesProcessed = 0;
                  foreach( $styleSheetsProcess AS $cssFile => $cssParams ){
                    $cssFileData = file_get_contents(JPATH_BASE . $cssFile);
                    $_cssUrlReplace_path = split('/',$cssFile); array_pop($_cssUrlReplace_path);
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
                      . "\n";
                    $filesProcessed++;
                  }

                // Write JS File
                  $fh = fopen( $cacheFile, 'w' );
                  fwrite( $fh, $cssContent );
                  fclose( $fh );

              }

            // Push Final
              $styleSheetsFinal[ $cacheLink ] = array(
                'mime'  => 'text/css',
                'media' => null,
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
  public function process_docScripts( $scripts ){

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

        // JS URL Replacement Callback
          global $_jsUrlReplace_path;
          function _jsUrlReplace($match){
            global $_jsUrlReplace_path;
            $path = $_jsUrlReplace_path;
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
          foreach( $scripts AS $jsFile => $jsParams ){
            if (strpos($jsFile, '/') !== 0)
              $jsFile = '/' . $jsFile;
            if (is_file(JPATH_BASE . $jsFile) ){
              $scriptsProcess[ $jsFile ] = $jsParams;
            }
            else {
              $scriptsFinal[ $jsFile ] = $jsParams;
            }
          }

        // If files require processing
          if( $scriptsProcess ){

            // Calculate Cache Filename
              $cacheFile = JPATH_CACHE . '/' . ($template.'-'.md5(serialize(',',$scriptsProcess).date('Y-m-d H')).'.js');
              $cacheLink = substr($cacheFile,strlen(JPATH_BASE));

            // Clear Cache
              $this->clear_docCache('/'.$template.'\-.*\.js/');

            // Create / Store if needed
              if( !file_exists($cacheFile) ){

                // Collect JS Data
                  $jsContent = '';
                  $filesProcessed = 0;
                  foreach( $scriptsProcess AS $jsFile => $jsParams ){
                    $_jsUrlReplace_path = split('/',$jsFile); array_pop($_jsUrlReplace_path);
                    $jsFileData = file_get_contents(JPATH_BASE . $jsFile);
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