<?php
/**
 * Helper class for the charter plugin, provides methods for rendering charts
 * using the pChart library.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Häußge <osd@foosel.net>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'charter/lib/pchart/pData.class.php');
require_once(DOKU_PLUGIN.'charter/lib/pchart/pChart.class.php');

class helper_plugin_charter extends DokuWiki_Plugin { // DokuWiki_Helper_Plugin

    function getInfo() {
        return array (
            'author' => 'Gina Haeussge',
            'email' => 'osd@foosel.net',
            'date' => @file_get_contents(DOKU_PLUGIN.'charter/VERSION'),
            'name' => 'Charter Plugin (helper component)',
            'desc' => 'Renders customized charts using the pChart library',
            'url' => 'http://foosel.org/snippets/dokuwiki/charter',
        );
    }

    function getMethods() {
        $result = array ();
        $result[] = array (
            'name' => 'setFlags',
            'desc' => 'sets the flags to use',
            'params' => array(
            	'flags' => 'array',
            ),
        );
        $result[] = array (
            'name' => 'setData',
            'desc' => 'sets the data to use',
            'params' => array (
                'data' => 'array',
            ),
        );
        $result[] = array (
            'name' => 'render',
            'desc' => 'renders the chart into the given file',
            'params' => array (
                'filename' => 'string',
            ),
            'return' => array (
                'success' => 'boolean'
            ),
        );
        return $result;
    }
    
    /** Flags */
	var $flags;
	
	/** Chart data */
	var $data;

	/** Valid flags */
	var $validFlags = array(
		'size', // size of the image, format 'width'x'height'
		'align', // alignment of image, valid values are 'left', 'right' and 'center'
		'type', // type of graph to generate, for valid values see below
		'title', // title of the graph
		
		'bgcolor', // background color
		'legendColor', // background color of the legend
		'graphColor', // background color of the graph area
		'titleColor', // color of the title
		'scaleColor', // color of the scale
		'shadowColor', // color of the shadow
		
		'bggradient', // background gradient, format '#RRGGBB@shades'
		'graphGradient', // graph area gradient, format '#RRGGBB@shades'
		
		'XAxisName', // name of X-axis
		'YAxisName', // name of Y-axis
		'XAxisFormat', // format of X-axis
		'YAxisFormat', // format of Y-axis
		'XAxisUnit', // unit of X-axis
		'YAxisUnit', // unit of Y-axis
		
		'fontTitle', // font for title, format 'fontfile@size'
		'fontDefault', // font for everything else, format 'formatfile@size'
		'fontLegend', // font for legend entries
		
		'labelSerie', // serie to use for labels
		'legendEntries', // entries for the legend
		'graphLabels', // special labels to be shown in the graph, comma-separated list of
		               // Serie-No.|X-Value|Description values
		
		'dots', // size of circles which plot the given data points, defaults to false (= not plotted)
		'legend', // whether to show the legend, defaults to true
		'shadow', // whether to use shadows, defaults to false
		'grid', // whether to show the grid, defaults to true
		'alpha', // alpha value to use for bargraphs, filled linegraphs or filled cubic curves, defaults to 50
		'ticks', // whether to show ticks on scale
		'decimals', // amount of decimals to display on scale
		
		'thresholds', // values at which to draw thresholds
		'palette', // color palette to use
		
		'pieLabels', // whether to show labels in the pie chart, defaults to true
		'piePercentage', // whether to show calculated percentages in pie chart, default to false
	);
	
	/** Valid chart types */
	var $validTypes = array(
		'line',
		'lineFilled',
		'cubic',
		'cubicFilled',
		'bar',
		'barStacked',
		'barOverlayed',
		'pie',
		'pie3d',
		'pieExploded',
	);
	
	/** Default values for flags */
	var $flagDefaults = array();
	
	/**
	 * Plugin constructor, initializes default settings and prepares
	 * default flags.
	 * 
	 * @author Gina Haeussge <osd@foosel.net>
	 */
	function helper_plugin_charter() {
		$this->flagDefaults = array(
			'size' => array(
				'width' => 600, 
				'height' => 300,
			),
			'align' => 'left',
			'type' => 'line',
			'fontDefault' => array(
				'name' => DOKU_PLUGIN.'charter/lib/fonts/Vera.ttf',
				'size' => 8,
			),
			'fontLegend' => array(
				'name' => DOKU_PLUGIN.'charter/lib/fonts/Vera.ttf',
				'size' => 8,
			),
			'fontTitle' => array(
				'name' => DOKU_PLUGIN.'charter/lib/fonts/VeraBd.ttf',
				'size' => 10,
			),
			'legend' => true,
			'grid' => true,
			'alpha' => 50,
			'dots' => false,
			'shadow' => false,
			'ticks' => true,
			'decimals' => 0,
			
			'bgcolor' => array(250, 250, 250),
			'graphColor' => array(255, 255, 255),
			'legendColor' => array(250, 250, 250),
			'titleColor' => array(0, 0, 0),
			'scaleColor' => array(150, 150, 150),
			'shadowColor' => array(200, 200, 200),
			
			'pieLabels' => false,
			'piePercentages' => true,
		);
	}

	/**
	 * Sets the flags from the given array. While doing so also validates and
	 * postprocesses them, making sure to fall back to usable default values
	 * where necessary.
	 * 
	 * @param flags flags to set
	 * 
	 * @author Gina Häußge <osd@foosel.net>
	 */
	function setFlags($flags = array()) {
		foreach ($flags as $key => $val) {
			if (!in_array($key, $this->validFlags))
				unset($flags[$key]);
			
			if (($key == 'fontTitle') || ($key == 'fontDefault') || ($key == 'fontLegend')) { 
				// validate fontdefinitions
				list($fontname, $fontsize) = explode('@', $val, 2);
				$flags[$key] = array(
					'name' => DOKU_PLUGIN.'charter/lib/fonts/' . $fontname,
					'size' => $fontsize,
				);
				if (!file_exists($flags[$key]['name']))
					unset($flags[$key]);
			} else if ($key == 'size') { 
				// validate and process size
				list($w, $h) = $this->_trimArray(explode('x', $val, 2));
				$flags[$key] = array(
					'width' => $w,
					'height' => $h,
				);

				if ($w < 0 || $h < 0)
					unset($flags[$key]);
			} else if ($key == 'align') { 
				// validate and process alignment
				if (!in_array($val, array('left', 'right', 'center')))
					unset($flags[$key]);
			} else if ($key == 'grid' || $key == 'legend' || $key == 'shadow' || $key == 'ticks' || $key == 'pieLabels' || $key == 'piePercentages') {
				// validate and process boolean settings
				if ($val == 'true' || $val == '1' || $val == 'on')
					$flags[$key] = true;
				else if ($val == 'false' || $val == '0' || $val == 'off')
					$flags[$key] = false;
				else
					unset($flags[$key]);
			} else if ($key == 'legendEntries' || $key == 'thresholds') { 
				// process legend entries and thresholds
				$flags[$key] = $this->_trimArray(explode(',', $flags[$key]));
			} else if ($key == 'type') {
				// validate graph type
				if (!in_array($val, $this->validTypes))
					unset($flags[$key]);
			} else if ($key == 'alpha') {
				// validate alpha setting
				if (!is_numeric($val) || $val < 0 || $val > 100)
					unset($flags[$key]);
			} else if ($key == 'dots' || $key == 'decimals') {
				// validate dot and decimals setting
				if (!is_numeric($val) || $val < 0)
					unset($flags[$key]);
			} else if ($key == 'bgcolor' || $key == 'legendColor' || $key == 'graphColor' || $key == 'titleColor' || $key == 'scaleColor' || $key == 'shadowColor') {
				// validate and process color definitions
				$flags[$key] = $this->_parseRGB($val);
				if (!$flags[$key])
					unset($flags[$key]);
			} else if ($key == 'palette') {
				// validate and process palette settings
				$flags[$key] = DOKU_PLUGIN.'charter/lib/palettes/' . $val . '.txt';
				if (!file_exists($flags[$key]))
					unset($flags[$key]);
			} else if ($key == 'graphLabels') {
				// validate and process graph labels
				$flags[$key] = $this->_trimArray(explode(',', $flags[$key]));
				for ($i = 0; $i < count($flags[$key]); $i++) {
					$flags[$key][$i] = $this->_trimArray(explode('|', $flags[$key][$i]));
					if (count($flags[$key][$i]) != 3) {
						unset($flags[$key]);
						break;
					}
					if (!is_numeric($flags[$key][$i][0]) || $flags[$key][$i][0] < 0) {
						unset($flags[$key]);
						break;
					}
				}
			} else if ($key == 'XAxisFormat' || $key == 'YAxisFormat') {
				// validate axis format settings
				if (!in_array($val, array('number', 'time', 'date', 'metric', 'currency')))
					unset($flags[$key]);
			} else if ($key == 'bggradient' || $key == 'graphGradient') {
				// validate and process background and graph gradients
				list($color, $shades) = $this->_trimArray(explode('@', $val, 2));
				$rgb = $this->_parseRGB($color);
				if (!$rgb || !is_numeric($shades) || $shades < 0)
					unset($flags[$key]);
				
				$flags[$key] = array(
					'color' => $rgb,
					'shades' => $shades,
				); 
			}
		}
		
		foreach ($this->flagDefaults as $key => $val) {
			if (!isset($flags[$key]))
				$flags[$key] = $val;
		}
		
		$this->flags = $flags;			
	}
	
	/**
	 * Sets the data to use.
	 * 
	 * @param data the data
	 * 
	 * @author Gina Häußge <osd@foosel.net> 
	 */
	function setData($data = array()) {
		$this->data = $data;
	}
	
	/**
	 * Renders the graph into given file.
	 * 
	 * @param filename the file to render to
	 * @return true on success, false on failure
	 * 
	 * @author Gina Häußge <osd@foosel.net>
	 */
	function render($filename) {
		if (!$filename)
			return false;
		
		// parse input data
		$csv = $this->_parseCsv($this->data);
		if (!$csv)
			return false;
		
		// create pData instance
		$pdata = $this->_createGraphData($csv);
		if (!$pdata)
			return false;
		
		// prepare pChart instance based on graph type
		switch ($this->flags['type']) {
			case 'line':
			case 'lineFilled':
			case 'cubic':
			case 'cubicFilled':
			case 'bar':
			case 'barStacked':
			case 'barOverlayed':
			default:
				$chart = $this->_createLineGraph($pdata);
				break;
			case 'pie':
			case 'pie3d':
			case 'pieExploded':
				$chart = $this->_createPieGraph($pdata);
				break;
		}
		if (!$chart)
			return false;
		
		// render graph into file
		if (!$chart->Render($filename))
			return false;
		
		return true;
	}
	
	/**
	 * Creates a pData instance from flags and data.
	 * 
	 * @param csv 2d array containing the data series
	 * @return object pData object containing both data and data descriptions to use
	 * 
	 * @author Gina Häußge <osd@foosel.net>
	 */
	function _createGraphData($csv) {
		$pdata = new pData();

		// set axis names
		if (isset($this->flags['XAxisName']))
			$pdata->SetXAxisName($this->flags['XAxisName']);
		if (isset($this->flags['YAxisName']))
			$pdata->SetYAxisName($this->flags['YAxisName']);
		
		// set axis units
		if (isset($this->flags['XAxisUnit']))
			$pdata->SetXAxisUnit($this->flags['XAxisUnit']);
		if (isset($this->flags['YAxisUnit']))
			$pdata->SetYAxisUnit($this->flags['YAxisUnit']);
		
		// set axis formats
		if (isset($this->flags['XAxisFormat']))
			$pdata->SetXAxisFormat($this->flags['XAxisFormat']);
		if (isset($this->flags['YAxisFormat']))
			$pdata->SetYAxisFormat($this->flags['YAxisFormat']);

		// add series to graph data
		$serie = 1;
		foreach ($csv as $row) {
			$pdata->AddPoint($row, 'Serie' . $serie);
			if (isset($this->flags['legendEntries'][$serie-1]))
				$pdata->SetSerieName($this->flags['legendEntries'][$serie-1], 'Serie' . $serie);			
			$serie++;
		}
		$pdata->AddAllSeries();
		
		// if label serie is defined, mark it as such
		if (isset($this->flags['labelSerie'])) {
			$labelSerie = 'Serie' . $this->flags['labelSerie'];
			$pdata->RemoveSerie($labelSerie);
			$pdata->SetAbsciseLabelSerie($labelSerie);
		}
		
		return $pdata;
	}
	
	/**
	 * Creates the pChart instance used to render the line/curve/bar graph.
	 * 
	 * @param pdata the pData instance containing the data to plot
	 * @return object a renderable pChart object
	 * 
	 * @author Gina Häußge <osd@foosel.net>
	 */
	function _createLineGraph($pdata) {
		$pchart = new pChart($this->flags['size']['width'], $this->flags['size']['height']);
		$pchart->drawBackground($this->flags['bgcolor'][0], $this->flags['bgcolor'][1], $this->flags['bgcolor'][2]);
		
		// draw background gradient
		if (isset($this->flags['bggradient']))
			$pchart->drawGraphAreaGradient($this->flags['bggradient']['color'][0], $this->flags['bggradient']['color'][1], $this->flags['bggradient']['color'][2], $this->flags['bggradient']['shades'], TARGET_BACKGROUND);

		// set palette
		if (isset($this->flags['palette']))
			$pchart->loadColorPalette($this->flags['palette']);

		// get legend size
		$pchart->setFontProperties($this->flags['fontLegend']['name'], $this->flags['fontLegend']['size']);
		$legendSize = array(0, 0);
		if ($this->flags['legend'])
			$legendSize = $pchart->getLegendBoxSize($pdata->GetDataDescription());

		// draw graph area
		$pchart->setFontProperties($this->flags['fontDefault']['name'], $this->flags['fontDefault']['size']);
		$pchart->setGraphArea(50, 30, $this->flags['size']['width'] - $legendSize[0] - 40, $this->flags['size']['height'] - 50);
		$pchart->drawGraphArea($this->flags['graphColor'][0], $this->flags['graphColor'][1], $this->flags['graphColor'][2], true);
		
		// draw graph area gradient
		if (isset($this->flags['graphGradient']))
			$pchart->drawGraphAreaGradient($this->flags['graphGradient']['color'][0], $this->flags['graphGradient']['color'][1], $this->flags['graphGradient']['color'][2], $this->flags['graphGradient']['shades']);

		// draw legend
		if ($this->flags['legend']) {
			$pchart->setFontProperties($this->flags['fontLegend']['name'], $this->flags['fontLegend']['size']);
			$pchart->drawLegend($this->flags['size']['width'] - $legendSize[0] - 15, 30, $pdata->GetDataDescription(), $this->flags['legendColor'][0], $this->flags['legendColor'][1], $this->flags['legendColor'][2]);
			$pchart->setFontProperties($this->flags['fontDefault']['name'], $this->flags['fontDefault']['size']);
		}

		// draw scale
		switch ($this->flags['type']) {
			case 'bar':
			case 'barOverlayed':
				$pchart->drawScale($pdata->GetData(), $pdata->GetDataDescription(), SCALE_START0, $this->flags['scaleColor'][0], $this->flags['scaleColor'][1], $this->flags['scaleColor'][2], $this->flags['ticks'], 0, $this->flags['decimals'], true);
				break;
			case 'barStacked':
				$pchart->drawScale($pdata->GetData(), $pdata->GetDataDescription(), SCALE_ADDALLSTART0, $this->flags['scaleColor'][0], $this->flags['scaleColor'][1], $this->flags['scaleColor'][2], $this->flags['ticks'], 0, $this->flags['decimals'], true);
				break;
			default:
				$pchart->drawScale($pdata->GetData(), $pdata->GetDataDescription(), SCALE_START0, $this->flags['scaleColor'][0], $this->flags['scaleColor'][1], $this->flags['scaleColor'][2], $this->flags['ticks'], 0, $this->flags['decimals'], false);
				break;
		}
		
		// draw grid
		if ($this->flags['grid'])
			$pchart->drawGrid(4, true, 230, 230, 230, $this->flags['alpha']);
		
		// draw thresholds
		if (isset($this->flags['thresholds'])) {
			foreach ($this->flags['thresholds'] as $threshold) {
				$pchart->drawTreshold($threshold, 143, 55, 72, true, true);
			}
		}
		
		// draw graph
		if ($this->flags['dots'])
			$pchart->drawPlotGraph($pdata->GetData(), $pdata->GetDataDescription(), $this->flags['dots']);
		if ($this->flags['shadow'] && in_array($this->flags['type'], array('line', 'lineFilled', 'cubic', 'cubicFilled')))
			$pchart->setShadowProperties(3,3,$this->flags['shadowColor'][0],$this->flags['shadowColor'][1],$this->flags['shadowColor'][2],30,4);
		switch ($this->flags['type']) {
			case 'line':
				$pchart->drawLineGraph($pdata->GetData(), $pdata->GetDataDescription());
				break;
			case 'lineFilled':
				$pchart->drawFilledLineGraph($pdata->GetData(), $pdata->GetDataDescription(), $this->flags['alpha']);
				break;
			case 'cubic':
				$pchart->drawCubicCurve($pdata->GetData(), $pdata->GetDataDescription());
				break;
			case 'cubicFilled':
				$pchart->drawFilledCubicCurve($pdata->GetData(), $pdata->GetDataDescription(), 0.1, $this->flags['alpha']);
				break;
			case 'bar':
				$pchart->drawBarGraph($pdata->GetData(), $pdata->GetDataDescription(), $this->flags['shadow'], $this->flags['alpha']);
				break;
			case 'barStacked':
				$pchart->drawStackedBarGraph($pdata->GetData(), $pdata->GetDataDescription(), $this->flags['alpha']);
				break;
			case 'barOverlayed':
				$pchart->drawOverlayBarGraph($pdata->GetData(), $pdata->GetDataDescription(), $this->flags['alpha']);
				break;
		}
		$pchart->clearShadow();
		
		// draw graph labels
		if (isset($this->flags['graphLabels'])) {
			$pchart->setFontProperties($this->flags['fontLegend']['name'], $this->flags['fontLegend']['size']);	
			foreach($this->flags['graphLabels'] as $label) {
				$pchart->setLabel($pdata->GetData(), $pdata->GetDataDescription(), 'Serie' . $label[0], $label[1], $label[2]);
			}
		}
		
		// draw title
		if (isset($this->flags['title'])) {
			$pchart->setFontProperties($this->flags['fontTitle']['name'], $this->flags['fontTitle']['size']);
			$pchart->drawTitle(50, 20, $this->flags['title'], $this->flags['titleColor'][0], $this->flags['titleColor'][1], $this->flags['titleColor'][2], $this->flags['size']['width'] - $legendSize[0] - 40);	
		}
		
		return $pchart;
	}
	
	/**
	 * Creates the pChart instance used to render the pie graph.
	 * 
	 * @param pdata the pData instance containing the data to plot
	 * @return object a renderable pChart object
	 * 
	 * @author Gina Häußge <osd@foosel.net>
	 */
	function _createPieGraph($pdata) {
		$pchart = new pChart($this->flags['size']['width'], $this->flags['size']['height']);
		$pchart->drawBackground($this->flags['bgcolor'][0], $this->flags['bgcolor'][1], $this->flags['bgcolor'][2]);
		
		// set palette
		if (isset($this->flags['palette']))
			$pchart->loadColorPalette($this->flags['palette']);

		// get legend size
		$pchart->setFontProperties($this->flags['fontLegend']['name'], $this->flags['fontLegend']['size']);
		$legendSize = array(0, 0);
		if ($this->flags['legend'])
			$legendSize = $pchart->getPieLegendBoxSize($pdata->GetData(), $pdata->GetDataDescription());
		
		// calculate center positiong and radius of pie chart
		$center = array(
			(int)(($this->flags['size']['width'] - $legendSize[0] - 20) / 2),
			(int)($this->flags['size']['height'] / 2),
		);
		$radius = min($center[0], $center[1]) - 40;
		if ($this->flags['type'] == 'pieExploded')
			$radius -= 20;

		// draw legend
		$pchart->setFontProperties($this->flags['fontDefault']['name'], $this->flags['fontDefault']['size']);
		if ($this->flags['legend']) {
			$pchart->setFontProperties($this->flags['fontLegend']['name'], $this->flags['fontLegend']['size']);
			$pchart->drawPieLegend($this->flags['size']['width'] - $legendSize[0] - 15, 30, $pdata->GetData(), $pdata->GetDataDescription(), $this->flags['legendColor'][0], $this->flags['legendColor'][1], $this->flags['legendColor'][2]);
			$pchart->setFontProperties($this->flags['fontDefault']['name'], $this->flags['fontDefault']['size']);
		}

		// draw graph
		$labeltype = PIE_NO_LABEL;
		if ($this->flags['pieLabels'] && $this->flags['piePercentages'])
			$labeltype = PIE_PERCENTAGE_LABEL;
		else if ($this->flags['pieLabels'])
			$labeltype = PIE_LABELS;
		else if ($this->flags['piePercentages'])
			$labeltype = PIE_PERCENTAGE;
		switch ($this->flags['type']) {
			case 'pie':
				$pchart->drawBasicPieGraph($pdata->GetData(), $pdata->GetDataDescription(), $center[0], $center[1], $radius, $labeltype);
				break;
			case 'pie3d':
				$pchart->drawPieGraph($pdata->GetData(), $pdata->GetDataDescription(), $center[0], $center[1], $radius, $labeltype);
				break;
			case 'pieExploded':
				$pchart->setShadowProperties(3,3,$this->flags['shadowColor'][0],$this->flags['shadowColor'][1],$this->flags['shadowColor'][2], 30, 4);
				$pchart->drawFlatPieGraphWithShadow($pdata->GetData(), $pdata->GetDataDescription(), $center[0], $center[1], $radius, $labeltype, 10, $this->flags['decimals']);
				$pchart->clearShadow();
				break;
		}
		
		// draw title
		if (isset($this->flags['title'])) {
			$pchart->setFontProperties($this->flags['fontTitle']['name'], $this->flags['fontTitle']['size']);
			$pchart->drawTitle(50, 20, $this->flags['title'], $this->flags['titleColor'][0], $this->flags['titleColor'][1], $this->flags['titleColor'][2], $this->flags['size']['width'] - $legendSize[0] - 40);	
			$pchart->setFontProperties($this->flags['fontLegend']['name'], $this->flags['fontLegend']['size']);	
		}
		
		return $pchart;
	}
	
	/**
	 * Parses the given CSV string or line array into a
	 * two-dimensional array. Very simplistic approach which
	 * does not support quoted strings and such, but should
	 * be sufficient for basic graph data.
	 * 
	 * @param data the data to parse
	 * @return array two-dimensional array containing the parsed data
	 * 
	 * @author Gina Häußge <osd@foosel.net>
	 */
	function _parseCsv($data) {
		if (!is_array($data))
			$data = explode("\n", $data);
		
		$output = array();
		foreach($data as $row) {
			$values = $this->_trimArray(explode(',', $row));
			array_push($output, $values);
		}
		
		return $output;
	}
	
	/**
	 * Trims all items in a given array.
	 * 
	 * @param a the array to trim
	 * @return array trimmed array
	 * 
	 * @author Gina Häußge <osd@foosel.net>
	 */
	function _trimArray($a) {
		if (!is_array($a))
			return $a;
		
		for ($i = 0; $i < count($a); $i++) {
			$a[$i] = trim($a[$i]);
		}
		
		return $a;
	}
	
	/**
	 * Parses a given HTML RGB color into three 8 bit sized
	 * integers representing the color.
	 * 
	 * Leading # is optional. Both six and three character wide
	 * color definitions are supported.
	 * 
	 * @param input a string containing a RGB color
	 * @param array 3d array containing integer representations of red, green and blue
	 * 
	 * @author Gina Häußge <osd@foosel.net>
	 */
	function _parseRGB($input) {
		if ($input[0] == '#')
			$input = substr($input, 1);
		
		if (strlen($input) == 6) { 
			// #RRGGBB
			$r = hexdec(substr($input, 0, 2));
			$g = hexdec(substr($input, 2, 2));
			$b = hexdec(substr($input, 4, 2));
		} else if (strlen($input) == 3) { 
			// #RGB
			$r = hexdec($input[0]);
			$g = hexdec($input[1]);
			$b = hexdec($input[2]);
			
			$r = $r * 16 + $r;
			$g = $g * 16 + $g;
			$b = $b * 16 + $b;
		} else {
			return false;
		}
		
		return array($r, $g, $b);
	}

}