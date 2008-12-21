<?php
/**
 * Charter Plugin
 * 
 * Renders customized charts using the pChart library.
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <osd@foosel.net>
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_charter extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return array (
            'author' => 'Gina Haeussge',
            'email' => 'osd@foosel.net',
            'date' => @file_get_contents(DOKU_PLUGIN.'charter/VERSION'),
            'name' => 'Charter Plugin (syntax component)',
            'desc' => 'Renders customized charts using the pChart library',
            'url' => 'http://foosel.org/snippets/dokuwiki/charter',
        );
    }

    function getType() {
        return 'substition';
    }
    
    function getSort() {
        return 123;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<charter>.*?</charter>', $mode, 'plugin_charter');
    }
    
    function handle($match, $state, $pos, & $handler) {
    	global $ID;
    	
    	$match = trim($match);
    	$lines = explode("\n", substr($match, 9, -10));
    	if (trim($lines[0]) === '')
    		array_shift($lines);
    	if (trim($lines[-1]) === '')
    		array_pop($lines);
    	
    	$flags = array();
    	$data = array();
    	$indata = false;
    	foreach ($lines as $line) {
    		$line = trim($line);
    		if ($line === '') { // begin data field
    			$indata = true;
    		} else {
    			if ($indata) { // reading in data
    				array_push($data, $line);
    			} else { // reading in flags
    				list($name, $value) = explode('=', $line, 2);
    				$flags[trim($name)] = trim($value);
    			}
    		}
    	}
    	
    	if (isset($flags['title'])) {
	    	$mediaid = getNS($ID) . ':chart-' . cleanID($flags['title'], false, true) . '.png';
    	} else {
    		$mediaid = getNS($ID) . ':chart-' . cleanID('notitle_' . md5($match)) . '.png';
    	}
    	$filename = mediaFN($mediaid);
    	
    	$helper =& plugin_load('helper', 'charter');
    	$helper->setFlags($flags);
    	$helper->setData($data);
    	
    	io_makeFileDir($filename);
    	io_lock($filename);
    	$helper->render($filename);
    	io_unlock($filename);
    	
    	return array($mediaid, $helper->flags);
    }

    function render($mode, & $renderer, $indata) {
    	list($mediaid, $flags) = $indata;
    	
        if ($mode == 'xhtml') {
        	$renderer->doc .= '<img src="'.ml($mediaid, 'cache=nocache').'" alt="'.$flags['title'].'" width="'.$flags['size']['width'].'" height="'.$flags['size']['height'].'" class="media'.$flags['align'].'" />';
        	return true;
        }

        // unsupported $mode
        return false;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :