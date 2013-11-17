<?php

namespace Bubo\Profiler\MenuProfiler;

use JpGraph\JpGraph;

/**
 * Description of SimpleProfiler
 *
 */
final class MenuProfiler {

    /**
     * @var bool print to Nette bar
     */
    public static $barDump = TRUE;

    public static $time = array();

    public static $currentRound = array();

    public static $code = array();

    public static $delta = array();

    public static $execTime = 11;

    public static $totalTime = array();

    public static $timeArray = array();

    public static $round = array();

    public static $legendArray = array();


    static private $isRegistered = FALSE;

    /* --- Properties --- */

    /* --- Public Methods--- */

    public static function register() {
            if (!self::$isRegistered) {
                    self::$time = isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(TRUE);
                    self::$execTime = self::$execTime > 0 ? self::$execTime : ini_get('max_execution_time');
                    if(self::$barDump){
                        $debugBar = new DebugBarExtension();

                        \Nette\Diagnostics\Debugger::$bar->addPanel($debugBar);
                    }
                    self::$isRegistered = TRUE;
            }
    }
    /**
     *
     */
    public static function __init(){

    }


    /**
     *
     * @param string $code
     * @param string $name
     */
    public static function code($code, $name = NULL){
        self::code($code[$name]);
    }

    /**
     * Test code
     * @param string $code
     * @param string $name
     */
    public static function testCode($code, $name = NULL){
        self::code($code[$name]);
        while(self::test()){
            self::evaluate($code);
        }
    }

    /**
     * Testing part of code:
     *
     * while(Profiler::test()){
     *      //an interesting code
     * }
     *
     * Code will run many times and round times are saved.
     *
     * @param string $name
     * @return boolean
     */
    public static function test($name = NULL){
        if(!isSet(self::$currentRound[$name])) self::$currentRound[$name] = 1;
        if(!isSet(self::$totalTime[$name])) self::$totalTime[$name] = 0;
        if(!isSet(self::$round[$name])) self::$round[$name] = 1;
        if(!isSet(self::$totalTime[$name])) self::$totalTime[$name] = 0;
        if(!isSet(self::$timeArray[$name])) self::$timeArray[$name] = array();
        $delta = self::timer(self::$currentRound[$name]);
        if($delta) self::$timeArray[$name][self::$round[$name]] = $delta;
        if(self::$totalTime[$name] + 10 >= self::$execTime){
            return false;
        }
        self::$round[$name]++;
        self::$totalTime[$name] += $delta;
        return true;
    }

    /**
     * List of charts
     * @depend on jpgraph
     * @param array $explode list of explodes
     * @return blob
     */
    public static function paintCharts(/*array*/ $explode = NULL){
        JpGraph::load();
        JpGraph::module('pie');
        JpGraph::module('pie3d');

        $ret = array();

        if (is_array(self::$timeArray)) {

        foreach(self::$timeArray as $key => $data){
            // Some data
           // $data = self::$timeArray[NULL];
            // Create the Pie Graph.
            //calculate rest:


            $totalTime = 0;
            foreach($data as $item){
                $totalTime += $item;
            }
            $restTime = (microtime(TRUE) - \Nette\Diagnostics\Debugger::$time);
            $restTime = abs($restTime - $totalTime);
            $data[] = $restTime;

            $graph = new \PieGraph(650,350);

            $theme_class = new \VividTheme;
            $graph->SetTheme($theme_class);

            // Set A title for the plot
            //$graph->title->Set("Profiler:)");


            // Create
            $p1 = new \PiePlot3D($data);
            $graph->Add($p1);

            if(isSet(self::$legendArray[$key]) && count(self::$legendArray[$key]) > 0){
                self::$legendArray[$key][] = 'Ostatni ('.number_format($restTime*1000,1,'.',' ').' ms)';
                $p1->SetLegends(self::$legendArray[$key]);
            }
            /*if(isSet(self::$legendArray[$key]) && count(self::$legendArray[$key]) > 0){
                foreach(self::$legendArray[$key] as $k => $label){
                    self::$legendArray[$key][$k] = $label .'\n%.1f%%';
                }
                self::$legendArray[$key][] = 'Ostatni ('.number_format($restTime*1000,1,'.',' ').')'.'\n%.1f%%';
                $p1->SetLabelType(PIE_VALUE_PER);
                $p1->SetLabels(self::$legendArray[$key]);
            }*/
            $p1->ShowBorder();
            $p1->SetShadow();
            $p1->SetColor('black');
            if(count($explode) > 0){

                    $p1->Explode($explode);
            }else{
                $p1->ExplodeAll();
            }
            $graph->legend->SetAbsPos(10,10,'right','top');
            $graph->Stroke(TEMP_DIR.'/pie_'.$key.'.png');
            $ret[] = TEMP_DIR.'/pie_'.$key.'.png';
        }
        }
        return $ret;
    }

    /**
     * Times in table
     * @return string
     */
    public static function printCharts(){
        $ret = '';
        foreach(self::$timeArray as $name => $timeArray){
            if(isSet(self::$code[$name])){
                $ret .= "Interesting code called '".($name?:'unnamed')."'<br />".  htmlspecialchars($code[$name])." <br /><br />Time review:<br />";
            }
            $ret .= '<table>';
            foreach($timeArray as $round => $time){
                $ret .= '<tr><td>'.$round.'</td><td>'. number_format(round($time,5) * 1000, 2, '.', ' ').' ms</td></tr>';
            }
            $ret .= '<tr><td>Total: </td><td>'. number_format(round(microtime(TRUE) - \Nette\Diagnostics\Debugger::$time,5) * 1000, 2, '.', ' ').'</td></tr>';
            $ret .= '</table>';
        }
        if(self::$barDump == TRUE){
            //$debugBar = new DebugBarExtension();
            //$debugBar->setData($ret);
            //\Nette\Diagnostics\Debugger::$bar->addPanel($debugBar);

            \Nette\Diagnostics\Debugger::barDump($ret, 'SimpleProfiler');
        }else{
            return $ret;
        }
    }

    /**
     *
     * @param string $name - timer name
     */
    public static function advancedTimerStart($name = NULL){
        self::timer($name);
    }

    /**
     *
     * @param string $label label to chart
     * @param string $name - timer name !same as start name!
     * @return float time
     */
    public static function advancedTimerStop($label = "", $name = NULL){
        if(($r = self::timer($name)) > 0){
            self::$timeArray[$name][] = $r;
            self::$legendArray[$name][] = $label;
        }
        return $r;
    }

    /**
     * Start and stop timer
     * @param string $label
     * @param string $name
     * @return float
     */
    public static function advancedTimer($label = "",$name = NULL){
        if(($r = self::timer($name)) > 0){
            self::$timeArray[$name][] = $r;
            self::$legendArray[$name][] = $label.' ('.number_format($r * 1000,1,'.',' ').' ms)';
        }
        return $r;
    }

    //prevzato z Nette

    /**
        * Starts/stops stopwatch.
        * @param  string  name
        * @return float   elapsed seconds
        */
    public static function timer($name = NULL){
            static $time = array();
            $now = microtime(TRUE);
            $delta = isset($time[$name]) ? $now - $time[$name] : 0;
            $time[$name] = $now;
            if($delta > 0) unset($time[$name]);
            return $delta;
    }


    /**
        * Evaluates code in limited scope.
        * @param  string  PHP code
        * @param  array   local variables
        * @return mixed   the return value of the evaluated code
        */
    public static function evaluate(/*$code, array $vars = NULL*/){
            if (func_num_args() > 1) {
                    self::$vars = func_get_arg(1);
                    extract(self::$vars);
            }
            $res = eval('?>' . func_get_arg(0));
            if ($res === FALSE && ($error = error_get_last()) && $error['type'] === E_PARSE) {
                    throw new \Exception($error['message'], 0, $error['type'], $error['file'], $error['line'], NULL);
            }
            return $res;
    }

}
