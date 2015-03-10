<?php

namespace Bubo\Profiler\MenuProfiler;

use Tracy\Debugger;

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
    public static $totalTime = array();
    public static $timeArray = array();
    public static $round = array();
    public static $legendArray = array();
    static private $isRegistered = FALSE;

    /* --- Properties --- */

    /* --- Public Methods--- */

    public static function register()
    {
        if (!self::$isRegistered) {
            if (self::$barDump) {
                $debugBar = new DebugBarExtension();
                Debugger::getBar()->addPanel($debugBar, 0);
            }
            self::$isRegistered = TRUE;
        }
    }

    /**
     * List of charts
     * @depend on jpgraph
     * @param array $explode list of explodes
     * @return blob
     */
    public static function paintCharts(/* array */ $explode = NULL) {
        JpGraph::load();
        JpGraph::module('pie');
        JpGraph::module('pie3d');

        $ret = array();

        // time array has following structure
        // array(
        //     <labelname> => array(
        //                      0 => 'time0',
        //                      1 => 'time1',
        //     )
        // )
        if (is_array(self::$timeArray)) {
            // process each label separatelly
            foreach (self::$timeArray as $key => $data) {
                
                $totalTime = 0;
                foreach ($data as $item) {
                    $totalTime += $item;
                }
                $restTime = (microtime(TRUE) - Debugger::$time);
                $restTime = abs($restTime - $totalTime);
                $data[] = $restTime;

                $graph = new \PieGraph(630, 300);

                $theme_class = new \VividTheme;
                $graph->SetTheme($theme_class);

                // Set A title for the plot
                //$graph->title->Set("Profiler:)");
                // Create
                $p1 = new \PiePlot3D($data);

                $graph->Add($p1);

                if (isSet(self::$legendArray[$key]) && count(self::$legendArray[$key]) > 0) {
                    self::$legendArray[$key][] = 'Ostatni (' . number_format($restTime * 1000, 1, '.', ' ') . ' ms)';
                    $p1->SetLegends(self::$legendArray[$key]);
                }
                $p1->ShowBorder();
                $p1->SetShadow();
                $p1->SetColor('black');
                if (count($explode) > 0) {
                    $p1->Explode($explode);
                } else {
                    $p1->ExplodeAll();
                }
                $graph->legend->SetAbsPos(10, 10, 'right', 'top');

                // save graph to file
                $graph->Stroke(TEMP_DIR . '/pie_' . $key . '.png');
                $ret[] = TEMP_DIR . '/pie_' . $key . '.png';
            }
        }
        return $ret;
    }

    /**
     * Times in table
     * @return string
     */
    public static function printCharts() {
        $ret = '';
        foreach (self::$timeArray as $name => $timeArray) {
            $ret .= '<table>';
            foreach ($timeArray as $round => $time) {
                $ret .= '<tr><td>' . $round . '</td><td>' . number_format(round($time, 5) * 1000, 2, '.', ' ') . ' ms</td></tr>';
            }
            $ret .= '<tr><td>Total: </td><td>' . number_format(round(microtime(TRUE) - Debugger::$time, 5) * 1000, 2, '.', ' ') . '</td></tr>';
            $ret .= '</table>';
        }
        if (self::$barDump == TRUE) {
            Debugger::barDump($ret, 'MenuProfiler');
        } else {
            return $ret;
        }
    }

    /**
     * Start and stop timer
     * @param string $label
     * @param string $name
     * @return float
     */
    public static function advancedTimer($label = "", $name = NULL) {
        if (($r = Debugger::timer($name)) > 0) {
            self::$timeArray[$name][] = $r;
            self::$legendArray[$name][] = $label . ' (' . number_format($r * 1000, 1, '.', ' ') . ' ms)';
        }
        return $r;
    }
}
